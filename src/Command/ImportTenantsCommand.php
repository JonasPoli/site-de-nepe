<?php

namespace App\Command;

use App\Entity\User;
use App\Service\TenantImport\TenantImporter;
use App\Service\TenantImport\TenantImportParser;
use App\Service\TenantImport\TenantImportRow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:import',
    description: 'Cadastra tenants e seus administradores a partir do CSV de respostas do Google Forms',
)]
class ImportTenantsCommand extends Command
{
    public function __construct(
        private readonly TenantImportParser $parser,
        private readonly TenantImporter $importer,
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
            ->addOption('base-domain', null, InputOption::VALUE_REQUIRED, 'Completa quem respondeu só o subdomínio e usa <nome-da-instituicao>.<base-domain> para quem respondeu sem domínio')
            ->addOption('scheme', null, InputOption::VALUE_REQUIRED, 'Protocolo dos links enviados por e-mail (http ou https)', 'https')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Porta dos links enviados por e-mail (ex.: 8000 em desenvolvimento)')
            ->setHelp(<<<'HELP'
                Lê o CSV exportado da planilha do Google Forms e, para cada resposta, cria o
                Tenant e um usuário administrador (usuário = e-mail, senha aleatória). Depois
                envia um e-mail com o link para o administrador definir a senha (válido por 7 dias).

                Colunas lidas (o título da pergunta precisa começar com este texto; acentos e
                maiúsculas são ignorados; as outras colunas são ignoradas):
                  Nome da instituição*  Domínio*  Tema  Cor principal  Cor secundária
                  Logo (fundo claro)  Logo (fundo escuro)  Nome completo*  E-mail*  WhatsApp  Autorizo…
                "Nome do NEPE" vale como nome da instituição e "SubDomínio" como domínio. Quem
                respondeu só o subdomínio (ex.: renovandoconsciencias) precisa de --base-domain.

                Uso sugerido:
                  <info>php bin/console %command.name% respostas.csv --dry-run</info>
                  <info>php bin/console %command.name% respostas.csv</info>

                Pode rodar de novo com o mesmo arquivo: respostas já importadas são ignoradas
                (ou recebem o e-mail de novo, com --resend).

                A mesma importação está no painel do super admin: /superadmin/tenant/import.

                As logos enviadas pelo Forms ficam no seu Google Drive. Para importá-las,
                compartilhe a pasta de uploads como "Qualquer pessoa com o link" antes de rodar.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        $resendTo = $this->importer->checkExistingRecords($rows, $resend);

        $toCreate = array_filter($rows, static fn (TenantImportRow $row): bool => $row->isValid() && !isset($resendTo[$row->line]));
        $alreadyImported = count(array_filter($rows, static fn (TenantImportRow $row): bool => $row->alreadyImported)) - count($resendTo);
        $skipped = count($rows) - count($toCreate) - count($resendTo) - $alreadyImported;

        $io->title('Importação de tenants');
        $this->renderPlan($io, $rows, $resendTo);
        $io->text(sprintf(
            '%d resposta(s): <info>%d para criar</info>, %d já importada(s), %d para reenviar e-mail, %d ignorada(s) por erro.',
            count($rows), count($toCreate), $alreadyImported, count($resendTo), $skipped,
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

        $result = $this->importer->import(
            $rows,
            $resendTo,
            $sendEmail ? $scheme . '://%s' . ($port !== null ? ':' . $port : '') : null,
        );

        $summary = sprintf('%d tenant(s) criado(s), %d e-mail(s) enviado(s).', $result->created, $result->emailed);
        $result->aborted ? $io->error($summary) : $io->success($summary);

        if ($result->problems !== []) {
            $io->section('Pontos de atenção');
            $io->listing(array_map(OutputFormatter::escape(...), $result->problemMessages()));
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
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
                isset($resendTo[$row->line]) => ['<fg=yellow>Reenviar e-mail</>', []],
                $row->alreadyImported        => ['<fg=gray>Já importado</>', []],
                !$row->isValid()             => ['<fg=red>Ignorar</>', $row->errors],
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
}
