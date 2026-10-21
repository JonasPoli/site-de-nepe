<?php

namespace App\Service\TenantImport;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Creates the tenants and admins parsed by TenantImportParser and sends each
 * admin the e-mail to define the password. Used by app:tenant:import and by
 * the super admin panel (CSV upload and single registration).
 */
class TenantImporter
{
    public const MAX_LOGO_BYTES = 5 * 1024 * 1024;
    public const LOGO_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordResetMailer $passwordResetMailer,
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Checks the valid rows against tenants and users already in the database:
     * conflicts become row errors and rows already imported are skipped, or
     * get the e-mail again when $resend is true.
     *
     * @param list<TenantImportRow> $rows
     *
     * @return array<int, User> line => admin already imported that gets the e-mail again
     */
    public function checkExistingRecords(array $rows, bool $resend): array
    {
        $resendTo = [];

        foreach ($rows as $row) {
            if (!$row->isValid()) {
                continue;
            }

            $tenant = $this->tenants->findByDomain($row->domain);
            $user = $this->users->findOneBy(['email' => $row->adminEmail])
                ?? $this->users->findOneBy(['username' => $row->adminEmail]);

            if ($tenant === null && $user === null) {
                continue;
            }

            if ($tenant !== null && $user !== null && $user->getTenant()?->getId() === $tenant->getId()) {
                $row->alreadyImported = true;
                if ($resend) {
                    $resendTo[$row->line] = $user;
                } else {
                    $row->errors[] = 'Já importado (escolha reenviar o e-mail para mandar o link de novo).';
                }
                continue;
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

        return $resendTo;
    }

    /**
     * @param list<TenantImportRow> $rows     Rows already checked by checkExistingRecords(): only the valid ones are created
     * @param array<int, User>      $resendTo Result of checkExistingRecords()
     * @param string|null           $baseUrlPattern Pattern of the links sent by e-mail, e.g. "https://%s" (%s = tenant domain); null doesn't send e-mails
     */
    public function import(array $rows, array $resendTo, ?string $baseUrlPattern): TenantImportResult
    {
        $result = new TenantImportResult();

        foreach ($rows as $row) {
            if (!$row->isValid() || isset($resendTo[$row->line])) {
                continue;
            }

            try {
                $admin = $this->createTenantAndAdmin($row, $result);
            } catch (\Throwable $e) {
                // Depois de um erro no flush o EntityManager fica fechado: não dá para seguir
                $result->addProblem($row->line, sprintf('erro ao gravar (%s). As linhas seguintes não foram processadas.', $e->getMessage()));
                $result->aborted = true;
                return $result;
            }

            $result->created++;
            if ($baseUrlPattern !== null) {
                $this->sendWelcomeEmail($admin, $row->line, $baseUrlPattern, $result);
            }
        }

        if ($baseUrlPattern !== null) {
            foreach ($resendTo as $line => $admin) {
                $this->sendWelcomeEmail($admin, $line, $baseUrlPattern, $result);
            }
        }

        return $result;
    }

    private function createTenantAndAdmin(TenantImportRow $row, TenantImportResult $result): User
    {
        $tenant = (new Tenant())
            ->setName($row->tenantName)
            ->setDomain($row->domain)
            ->setTheme($row->theme)
            ->setPhone($row->phone)
            ->setWhatsappLink($row->whatsappLink);

        if ($row->primaryColor !== null) {
            $tenant->setPrimaryColor($row->primaryColor);
        }
        if ($row->secondaryColor !== null) {
            $tenant->setSecondaryColor($row->secondaryColor);
        }

        $tempFiles = [];
        foreach ([
            'Logo (fundo claro)'  => [$row->logoFile, $row->logoUrl, $tenant->setLogoFile(...)],
            'Logo (fundo escuro)' => [$row->darkLogoFile, $row->darkLogoUrl, $tenant->setDarkLogoFile(...)],
        ] as $label => [$file, $url, $setFile]) {
            if ($file !== null) {
                if ($this->isValidLogo($file->getPathname(), $row->line, $label, $result)) {
                    $setFile($file);
                }
            } elseif ($url !== null && null !== $path = $this->downloadLogo($url, $row->line, $label, $result)) {
                $setFile(new ReplacingFile($path));
                $tempFiles[] = $path;
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
            foreach ($tempFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        return $admin;
    }

    /** Baixa a logo para um arquivo temporário; devolve null (e registra o motivo) quando não dá para usar */
    private function downloadLogo(string $url, int $line, string $label, TenantImportResult $result): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 30]);

            if ((int) ($response->getHeaders()['content-length'][0] ?? 0) > self::MAX_LOGO_BYTES) {
                $response->cancel();
                $result->addProblem($line, sprintf('%s não importada (arquivo maior que 5 MB).', $label));
                return null;
            }

            $content = $response->getContent();
        } catch (HttpClientException $e) {
            $result->addProblem($line, sprintf('%s não importada (falha no download: %s).', $label, $e->getMessage()));
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'tenant-logo-');
        file_put_contents($path, $content);

        if (!$this->isValidLogo($path, $line, $label, $result, fromDrive: true)) {
            unlink($path);
            return null;
        }

        return $path;
    }

    private function isValidLogo(string $path, int $line, string $label, TenantImportResult $result, bool $fromDrive = false): bool
    {
        if (filesize($path) > self::MAX_LOGO_BYTES) {
            $result->addProblem($line, sprintf('%s não importada (arquivo maior que 5 MB).', $label));
            return false;
        }

        // Confere o conteúdo, não o cabeçalho: o Drive devolve uma página HTML de login quando o arquivo não está compartilhado
        $mimeType = MimeTypes::getDefault()->guessMimeType($path);
        if (!in_array($mimeType, self::LOGO_MIME_TYPES, true)) {
            $result->addProblem($line, sprintf(
                $fromDrive
                    ? '%s não importada (o link não retornou PNG, JPG, WebP ou GIF, e sim %s). Se for do Google Drive, compartilhe a pasta como "Qualquer pessoa com o link".'
                    : '%s não importada (o arquivo não é PNG, JPG, WebP ou GIF, e sim %s).',
                $label, $mimeType ?? 'um tipo desconhecido',
            ));
            return false;
        }

        return true;
    }

    private function sendWelcomeEmail(User $admin, int $line, string $baseUrlPattern, TenantImportResult $result): void
    {
        try {
            $this->passwordResetMailer->send($admin, sprintf($baseUrlPattern, $admin->getTenant()?->getDomain()), welcome: true);
            $result->emailed++;
            return;
        } catch (TooManyPasswordRequestsException $e) {
            $result->addProblem($line, sprintf(
                'e-mail não enviado para %s porque um link foi enviado há pouco. Tente de novo em %d minuto(s).',
                $admin->getEmail(), max(1, (int) ceil($e->getRetryAfter() / 60)),
            ));
        } catch (TransportExceptionInterface $e) {
            $result->addProblem($line, sprintf(
                'falha ao enviar e-mail para %s (%s). Importe de novo escolhendo reenviar o e-mail.',
                $admin->getEmail(), $e->getMessage(),
            ));
        }

        $result->emailFailures++;
    }
}
