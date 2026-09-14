<?php

namespace App\Command;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use App\Service\TenantImport\TenantImportParser;
use App\Service\TenantImport\TenantImportRow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

#[AsCommand(
    name: 'app:tenant:import',
    description: 'Cadastra tenants e seus administradores a partir do CSV de respostas do Google Forms',
)]
class ImportTenantsCommand extends Command
{
    private const MAX_LOGO_BYTES = 5 * 1024 * 1024;
    private const LOGO_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /** @var list<string> Pontos de atenção exibidos no final */
    private array $problems = [];

    public function __construct(
        private readonly TenantImportParser $parser,
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordResetMailer $passwordResetMailer,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'CSV da planilha de respostas (Arquivo > Fazer download > CSV)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Só valida e mostra o que seria feito, sem gravar nem enviar e-mails')
            ->addOption('no-email', null, InputOption::VALUE_NONE, 'Cadastra sem enviar o e-mail para definir a senha')
            ->addOption('resend', null, InputOption::VALUE_NONE, 'Reenvia o e-mail aos administradores de tenants já importados')
            ->addOption('base-domain', null, InputOption::VALUE_REQUIRED, 'Usa <nome-da-instituicao>.<base-domain> para quem respondeu sem domínio')
            ->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'Protocolo dos links enviados por e-mail (http ou https)', 'https')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta dos links enviados por e-mail (ex.: 8000 em desenvolvimento)')
            ->setHelp(<<<'HELP'
                Lê o CSV exportado da planilha do Google Forms e, para cada resposta, cria o
                Tenant e um usuário administrador (usuário = e-mail, senha aleatória). Depois
                envia um e-mail com o link para o administrador definir a senha (válido por 7 dias).

                Colunas lidas (o título da pergunta precisa começar com este texto; acentos e
                maiúsculas são ignorados; as outras colunas são ignoradas):
                  Nome da instituição*  Domínio*  Tema  Cor principal  Cor secundária
                  Logo (fundo claro)  Logo (fundo escuro)  Nome completo*  E-mail*  Autorizo…

                Uso sugerido:
                  <info>php bin/console %command.name% respostas.csv --dry-run</info>
                  <info>php bin/console %command.name% respostas.csv</info>

                Pode rodar de novo com o mesmo arquivo: respostas já importadas são ignoradas
                (ou recebem o e-mail de novo, com --resend).

                As logos enviadas pelo Forms ficam no seu Google Drive. Para importá-las,
                compartilhe a pasta de uploads como "Qualquer pessoa com o link" antes de rodar.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->problems = [];

        $dryRun = (bool) $input->getOption('dry-run');
        $sendEmail = !$input->getOption('no-email');
        $resend = (bool) $input->getOption('resend');
        $scheme = strtolower((string) $input->getOption('scheme'));
        $port = $input->getOption('port');

        if ($resend && !$sendEmail) {
            $io->error('--resend e --no-email não podem ser usados juntos.');
            return Command::INVALID;
        }
        if (!in_array($scheme, ['http', 'https'], true)) {
            $io->error('--scheme precisa ser http ou https.');
            return Command::INVALID;
        }
        if ($port !== null && (!ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535)) {
            $io->error('--port precisa ser um número entre 1 e 65535.');
            return Command::INVALID;
        }

        $baseDomain = null;
        if (null !== $rawBaseDomain = $input->getOption('base-domain')) {
            $baseDomain = TenantImportParser::normalizeDomain((string) $rawBaseDomain);
            if ($baseDomain === null) {
                $io->error(sprintf('--base-domain inválido: "%s".', $rawBaseDomain));
                return Command::INVALID;
            }
        }

        $file = (string) $input->getArgument('file');
        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('Arquivo não encontrado: %s', $file));
            return Command::FAILURE;
        }

        try {
            $rows = $this->parser->parseFile($file, $baseDomain);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        /** @var array<int, User> $resendTo linha => administrador já cadastrado */
        $resendTo = [];
        foreach ($rows as $row) {
            if ($row->isValid()) {
                $this->checkExistingRecords($row, $resend, $resendTo);
            }
        }

        $toCreate = array_values(array_filter($rows, static fn (TenantImportRow $row): bool => $row->isValid() && !isset($resendTo[$row->line])));
        $skipped = count($rows) - count($toCreate) - count($resendTo);

        $io->title('Importação de tenants');
        $this->renderPlan($io, $rows, $resendTo);
        $io->text(sprintf(
            '%d resposta(s): <info>%d para criar</info>, %d para reenviar e-mail, %d ignorada(s).',
            count($rows), count($toCreate), count($resendTo), $skipped,
        ));

        if ($dryRun) {
            $io->note('Dry-run: nada foi gravado e nenhum e-mail foi enviado.');
            return Command::SUCCESS;
        }

        if ($toCreate === [] && $resendTo === []) {
            $io->warning('Nada para importar.');
            return Command::SUCCESS;
        }

        $question = $sendEmail
            ? sprintf('Criar %d tenant(s) e enviar %d e-mail(s)?', count($toCreate), count($toCreate) + count($resendTo))
            : sprintf('Criar %d tenant(s) sem enviar e-mails?', count($toCreate));
        if (!$io->confirm($question)) {
            return Command::SUCCESS;
        }

        $baseUrlPattern = $scheme . '://%s' . ($port !== null ? ':' . $port : '');
        $created = 0;
        $emailed = 0;
        $emailFailures = 0;
        $aborted = false;

        foreach ($toCreate as $row) {
            try {
                $admin = $this->createTenantAndAdmin($row);
            } catch (\Throwable $e) {
                // Depois de um erro no flush o EntityManager fica fechado: não dá para seguir
                $this->problems[] = sprintf('Linha %d: erro ao gravar (%s). As linhas seguintes não foram processadas.', $row->line, $e->getMessage());
                $aborted = true;
                break;
            }

            $created++;
            if ($sendEmail) {
                $this->sendWelcomeEmail($admin, $row->line, $baseUrlPattern) ? $emailed++ : $emailFailures++;
            }
        }

        if (!$aborted) {
            foreach ($resendTo as $line => $admin) {
                $this->sendWelcomeEmail($admin, $line, $baseUrlPattern) ? $emailed++ : $emailFailures++;
            }
        }

        $summary = sprintf('%d tenant(s) criado(s), %d e-mail(s) enviado(s).', $created, $emailed);
        $aborted ? $io->error($summary) : $io->success($summary);

        if ($this->problems !== []) {
            $io->section('Pontos de atenção');
            $io->listing(array_map(OutputFormatter::escape(...), $this->problems));
        }

        return $aborted || $emailFailures > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @param array<int, User> $resendTo */
    private function checkExistingRecords(TenantImportRow $row, bool $resend, array &$resendTo): void
    {
        $tenant = $this->tenants->findByDomain($row->domain);
        $user = $this->users->findOneBy(['email' => $row->adminEmail])
            ?? $this->users->findOneBy(['username' => $row->adminEmail]);

        if ($tenant === null && $user === null) {
            return;
        }

        if ($tenant !== null && $user !== null && $user->getTenant()?->getId() === $tenant->getId()) {
            if ($resend) {
                $resendTo[$row->line] = $user;
            } else {
                $row->errors[] = 'Já importado (use --resend para reenviar o e-mail).';
            }
            return;
        }

        if ($tenant !== null) {
            $row->errors[] = sprintf('Domínio já cadastrado no tenant "%s".', $tenant->getName());
        }
        if ($user !== null) {
            $row->errors[] = $user->getTenant()
                ? sprintf('E-mail já cadastrado para o usuário "%s" do tenant "%s".', $user->getUsername(), $user->getTenant()->getName())
                : sprintf('E-mail já cadastrado para o usuário "%s" (super admin).', $user->getUsername());
        }
    }

    /**
     * @param list<TenantImportRow> $rows
     * @param array<int, User>      $resendTo
     */
    private function renderPlan(SymfonyStyle $io, array $rows, array $resendTo): void
    {
        $table = $io->createTable()
            ->setHeaders(['Linha', 'Instituição', 'Domínio', 'Administrador', 'Situação'])
            ->setColumnMaxWidth(1, 30);

        foreach ($rows as $row) {
            [$status, $messages] = match (true) {
                !$row->isValid()             => ['<fg=red>Ignorar</>', $row->errors],
                isset($resendTo[$row->line]) => ['<fg=yellow>Reenviar e-mail</>', []],
                default                      => ['<fg=green>Criar</>', []],
            };

            // Uma mensagem por linha, quebrada por palavra (a tabela corta palavras no meio)
            foreach ([...$messages, ...$row->warnings] as $message) {
                $status .= "\n" . OutputFormatter::escape(wordwrap($message, 60, "\n", true));
            }

            $table->addRow([
                $row->line,
                OutputFormatter::escape($row->tenantName),
                $row->domain,
                OutputFormatter::escape($row->adminEmail),
                $status,
            ]);
        }

        $table->render();
        $io->newLine();
    }

    private function createTenantAndAdmin(TenantImportRow $row): User
    {
        $tenant = (new Tenant())
            ->setName($row->tenantName)
            ->setDomain($row->domain)
            ->setTheme($row->theme);

        if ($row->primaryColor !== null) {
            $tenant->setPrimaryColor($row->primaryColor);
        }
        if ($row->secondaryColor !== null) {
            $tenant->setSecondaryColor($row->secondaryColor);
        }

        $tempFiles = [];
        if ($row->logoUrl !== null) {
            $file = $this->downloadLogo($row->logoUrl, $row->line, 'Logo (fundo claro)');
            if ($file !== null) {
                $tenant->setLogoFile(new ReplacingFile($file));
                $tempFiles[] = $file;
            }
        }
        if ($row->darkLogoUrl !== null) {
            $file = $this->downloadLogo($row->darkLogoUrl, $row->line, 'Logo (fundo escuro)');
            if ($file !== null) {
                $tenant->setDarkLogoFile(new ReplacingFile($file));
                $tempFiles[] = $file;
            }
        }

        $admin = (new User())
            ->setUsername($row->adminEmail)
            ->setName($row->adminName)
            ->setEmail($row->adminEmail)
            ->setWorkGroup(0)
            ->setTenant($tenant);

        // Senha aleatória que ninguém conhece: o acesso é liberado pelo link enviado por e-mail
        $admin->setPassword($this->passwordHasher->hashPassword($admin, bin2hex(random_bytes(32))));

        try {
            $this->em->persist($tenant);
            $this->em->persist($admin);
            $this->em->flush();
        } finally {
            // O Vich copia as logos para public/uploads durante o flush
            foreach ($tempFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        return $admin;
    }

    /** Baixa a logo para um arquivo temporário; devolve null (e registra o motivo) quando não dá para usar */
    private function downloadLogo(string $url, int $line, string $label): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 30]);

            if ((int) ($response->getHeaders()['content-length'][0] ?? 0) > self::MAX_LOGO_BYTES) {
                $response->cancel();
                $this->problems[] = sprintf('Linha %d: %s não importada (arquivo maior que 5 MB).', $line, $label);
                return null;
            }

            $content = $response->getContent();
        } catch (HttpClientException $e) {
            $this->problems[] = sprintf('Linha %d: %s não importada (falha no download: %s).', $line, $label, $e->getMessage());
            return null;
        }

        if (strlen($content) > self::MAX_LOGO_BYTES) {
            $this->problems[] = sprintf('Linha %d: %s não importada (arquivo maior que 5 MB).', $line, $label);
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'tenant-logo-');
        file_put_contents($path, $content);

        // Confere o conteúdo, não o cabeçalho: o Drive devolve uma página HTML de login quando o arquivo não está compartilhado
        $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        if (!in_array($mimeType, self::LOGO_MIME_TYPES, true)) {
            unlink($path);
            $this->problems[] = sprintf(
                'Linha %d: %s não importada (o link não retornou PNG, JPG, WebP ou GIF, e sim %s). Se for do Google Drive, compartilhe a pasta como "Qualquer pessoa com o link".',
                $line, $label, $mimeType ?? 'um tipo desconhecido',
            );
            return null;
        }

        return $path;
    }

    private function sendWelcomeEmail(User $admin, int $line, string $baseUrlPattern): bool
    {
        try {
            $this->passwordResetMailer->send($admin, sprintf($baseUrlPattern, $admin->getTenant()?->getDomain()), welcome: true);
            return true;
        } catch (TooManyPasswordRequestsException $e) {
            $this->problems[] = sprintf(
                'Linha %d: e-mail não enviado para %s porque um link foi enviado há pouco. Tente de novo em %d minuto(s).',
                $line, $admin->getEmail(), max(1, (int) ceil($e->getRetryAfter() / 60)),
            );
        } catch (TransportExceptionInterface $e) {
            $this->problems[] = sprintf(
                'Linha %d: falha ao enviar e-mail para %s (%s). Rode de novo com --resend.',
                $line, $admin->getEmail(), $e->getMessage(),
            );
        }

        return false;
    }
}
