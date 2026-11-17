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
use Symfony\Contracts\HttpClient\ResponseInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Creates the tenants and admins parsed by TenantImportParser and sends each
 * admin the e-mail to define the password. Used by app:tenant:import and by
 * the super admin panel (CSV upload and single registration).
 */
class TenantImporter
{
    public const MAX_LOGO_BYTES = 5 * 1024 * 1024;
    /** Redirecionamentos seguidos à mão, para conferir o destino de cada salto */
    private const MAX_REDIRECTS = 5;
    public const LOGO_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordResetMailer $passwordResetMailer,
        private readonly HttpClientInterface $httpClient,
        private readonly PublicHostChecker $publicHost,
        private readonly StorageInterface $storage,
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

        /** @var array<int, User> linha => administrador criado */
        $criados = [];
        /** @var list<Tenant> tenants do lote, para limpar as logos se a transação cair */
        $tenants = [];
        /** @var list<string> arquivos temporários das logos baixadas */
        $tempFiles = [];
        $linhaAtual = 0;

        try {
            // O lote inteiro de uma vez: se qualquer linha falhar, nenhum NEPE é criado.
            // Antes, cada linha tinha o seu flush e uma falha no meio deixava o lote pela metade.
            $this->em->wrapInTransaction(function () use ($rows, $resendTo, $result, &$criados, &$tenants, &$tempFiles, &$linhaAtual): void {
                foreach ($rows as $row) {
                    if (!$row->isValid() || isset($resendTo[$row->line])) {
                        continue;
                    }

                    $linhaAtual = $row->line;
                    [$admin, $tenant] = $this->createTenantAndAdmin($row, $result, $tempFiles);
                    $criados[$row->line] = $admin;
                    $tenants[] = $tenant;
                }

                $this->em->flush();
            });
        } catch (\Throwable $e) {
            // A transação já foi desfeita; o Vich, porém, copia os arquivos fora do banco
            $this->removeStoredLogos($tenants);
            $this->removeTempFiles($tempFiles);

            $result->addProblem($linhaAtual, sprintf(
                'erro ao gravar (%s). Nenhum NEPE deste arquivo foi criado.',
                $e->getMessage(),
            ));
            $result->aborted = true;

            return $result;
        }

        $this->removeTempFiles($tempFiles);
        $result->created = count($criados);

        // Só depois do commit: um envio antes mandaria convite para NEPE que o rollback apagou
        if ($baseUrlPattern !== null) {
            foreach ($criados as $line => $admin) {
                $this->sendWelcomeEmail($admin, $line, $baseUrlPattern, $result);
            }

            foreach ($resendTo as $line => $admin) {
                $this->sendWelcomeEmail($admin, $line, $baseUrlPattern, $result);
            }
        }

        return $result;
    }

    /** @param list<Tenant> $tenants */
    private function removeStoredLogos(array $tenants): void
    {
        foreach ($tenants as $tenant) {
            foreach (['logoFile', 'darkLogoFile'] as $campo) {
                try {
                    $caminho = $this->storage->resolvePath($tenant, $campo);
                } catch (\Throwable) {
                    continue;
                }

                if (is_string($caminho) && is_file($caminho)) {
                    unlink($caminho);
                }
            }
        }
    }

    /** @param list<string> $paths */
    private function removeTempFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Persiste o tenant e o administrador sem gravar: o flush é único, no fim do lote.
     *
     * @param list<string> $tempFiles Arquivos temporários das logos, apagados depois do flush
     *
     * @return array{User, Tenant}
     */
    private function createTenantAndAdmin(TenantImportRow $row, TenantImportResult $result, array &$tempFiles): array
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

        $this->em->persist($tenant);
        $this->em->persist($admin);

        return [$admin, $tenant];
    }

    /** Baixa a logo para um arquivo temporário; devolve null (e registra o motivo) quando não dá para usar */
    private function downloadLogo(string $url, int $line, string $label, TenantImportResult $result): ?string
    {
        $response = $this->requestLogo($url, $line, $label, $result);
        if ($response === null) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'tenant-logo-');

        try {
            $this->writeLimited($response, $path);
        } catch (TooLargeException) {
            $response->cancel();
            unlink($path);
            $result->addProblem($line, sprintf('%s não importada (arquivo maior que 5 MB).', $label));
            return null;
        } catch (HttpClientException $e) {
            unlink($path);
            $result->addProblem($line, sprintf('%s não importada (falha no download: %s).', $label, $e->getMessage()));
            return null;
        }

        if (!$this->isValidLogo($path, $line, $label, $result, fromDrive: true)) {
            unlink($path);
            return null;
        }

        return $path;
    }

    /**
     * Pede a logo seguindo os redirecionamentos à mão: cada salto passa pela conferência
     * de destino público, senão um link público que redireciona para a rede interna passaria.
     */
    private function requestLogo(string $url, int $line, string $label, TenantImportResult $result): ?ResponseInterface
    {
        for ($salto = 0; $salto <= self::MAX_REDIRECTS; $salto++) {
            if (null !== $motivo = $this->publicHost->rejectionReason($url)) {
                $result->addProblem($line, sprintf('%s não importada (%s).', $label, $motivo));
                return null;
            }

            try {
                $response = $this->httpClient->request('GET', $url, ['timeout' => 30, 'max_redirects' => 0]);
                $status = $response->getStatusCode();

                if ($status < 300 || $status >= 400) {
                    if ($status !== 200) {
                        $response->cancel();
                        $result->addProblem($line, sprintf('%s não importada (o endereço respondeu %d).', $label, $status));
                        return null;
                    }

                    // O content-length, quando vem, evita começar um download que já se sabe grande
                    if ((int) ($response->getHeaders(false)['content-length'][0] ?? 0) > self::MAX_LOGO_BYTES) {
                        $response->cancel();
                        $result->addProblem($line, sprintf('%s não importada (arquivo maior que 5 MB).', $label));
                        return null;
                    }

                    return $response;
                }

                $destino = $response->getHeaders(false)['location'][0] ?? null;
                $response->cancel();

                if ($destino === null) {
                    $result->addProblem($line, sprintf('%s não importada (redirecionamento sem destino).', $label));
                    return null;
                }

                $url = self::absoluteUrl($url, $destino);
            } catch (HttpClientException $e) {
                $result->addProblem($line, sprintf('%s não importada (falha no download: %s).', $label, $e->getMessage()));
                return null;
            }
        }

        $result->addProblem($line, sprintf('%s não importada (redirecionamentos demais).', $label));
        return null;
    }

    /**
     * Grava a resposta em pedaços, parando assim que passa do limite: sem isso, uma resposta
     * sem content-length seria carregada inteira na memória antes da conferência.
     *
     * @throws TooLargeException quando o conteúdo passa de MAX_LOGO_BYTES
     */
    private function writeLimited(ResponseInterface $response, string $path): void
    {
        $handle = fopen($path, 'wb');
        $recebido = 0;

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isLast()) {
                    break;
                }

                $conteudo = $chunk->getContent();
                $recebido += strlen($conteudo);

                if ($recebido > self::MAX_LOGO_BYTES) {
                    throw new TooLargeException();
                }

                fwrite($handle, $conteudo);
            }
        } finally {
            fclose($handle);
        }
    }

    /** Resolve o destino de um redirecionamento, que pode vir relativo */
    private static function absoluteUrl(string $atual, string $destino): string
    {
        if (preg_match('~^https?://~i', $destino)) {
            return $destino;
        }

        $parts = parse_url($atual);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($destino, '/')) {
            return $base . $destino;
        }

        $caminho = $parts['path'] ?? '/';

        return $base . substr($caminho, 0, strrpos($caminho, '/') + 1) . $destino;
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
