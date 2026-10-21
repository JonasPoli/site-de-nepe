<?php

namespace App\Controller\superadmin;

use App\Service\TenantImport\TenantImporter;
use App\Service\TenantImport\TenantImportParser;
use App\Service\TenantImport\TenantImportRow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cadastro de tenants com o administrador: importação do CSV do Google Forms
 * (o mesmo do app:tenant:import) e cadastro avulso, com o e-mail para definir a senha.
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/superadmin/tenant', name: 'superadmin_tenant_')]
class TenantImportController extends AbstractController
{
    /** Domínio do formulário NEPE Brasil, que pede só o subdomínio */
    private const DEFAULT_BASE_DOMAIN = 'nepebrasil.org';
    private const MAX_CSV_BYTES = 2 * 1024 * 1024;
    /** CSVs enviados e não confirmados são apagados depois disso */
    private const CSV_LIFETIME = 86400;

    public function __construct(
        private readonly TenantImportParser $parser,
        private readonly TenantImporter $importer,
        #[Autowire('%kernel.project_dir%/var/tenant-import')]
        private readonly string $uploadDir,
    ) {}

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function upload(Request $request): Response
    {
        $options = $this->readOptions($request->request->all() + ['baseDomain' => self::DEFAULT_BASE_DOMAIN, 'email' => '1']);
        $error = null;

        if ($request->isMethod('POST')) {
            $error = $this->validateUpload($request, $options);

            if ($error === null) {
                /** @var UploadedFile $file */
                $file = $request->files->get('csv');
                $this->removeExpiredUploads();

                $token = bin2hex(random_bytes(16));
                $file->move($this->uploadDir, $token . '.csv');

                return $this->redirectToRoute('superadmin_tenant_import_preview', ['token' => $token] + $this->optionsQuery($options));
            }
        }

        return $this->render('superadmin/tenant/import.html.twig', [
            'options' => $options,
            'error'   => $error,
        ]);
    }

    #[Route('/import/{token}', name: 'import_preview', requirements: ['token' => '[0-9a-f]{32}'], methods: ['GET'])]
    public function preview(string $token, Request $request): Response
    {
        $options = $this->readOptions($request->query->all());
        $rows = $this->parseUpload($token, $options);
        if ($rows instanceof Response) {
            return $rows;
        }

        $resendTo = $this->importer->checkExistingRecords($rows, $options['resend']);
        $toCreate = array_filter($rows, static fn (TenantImportRow $row): bool => $row->isValid() && !isset($resendTo[$row->line]));
        $alreadyImported = count(array_filter($rows, static fn (TenantImportRow $row): bool => $row->alreadyImported)) - count($resendTo);

        return $this->render('superadmin/tenant/import_preview.html.twig', [
            'token'        => $token,
            'options'      => $options,
            'rows'         => $rows,
            'resendLines'  => array_keys($resendTo),
            'createCount'  => count($toCreate),
            'resendCount'  => count($resendTo),
            'doneCount'    => $alreadyImported,
            'skippedCount' => count($rows) - count($toCreate) - count($resendTo) - $alreadyImported,
        ]);
    }

    #[Route('/import/{token}', name: 'import_run', requirements: ['token' => '[0-9a-f]{32}'], methods: ['POST'])]
    public function run(string $token, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tenant_import_' . $token, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada. Confira a prévia e confirme de novo.');
            return $this->redirectToRoute('superadmin_tenant_import_preview', ['token' => $token] + $this->optionsQuery($this->readOptions($request->request->all())));
        }

        $options = $this->readOptions($request->request->all());
        $rows = $this->parseUpload($token, $options);
        if ($rows instanceof Response) {
            return $rows;
        }

        // Baixar as logos do Drive e enviar os e-mails pode passar do limite padrão de 30 s
        set_time_limit(300);

        $resendTo = $this->importer->checkExistingRecords($rows, $options['resend']);
        $result = $this->importer->import($rows, $resendTo, $options['email'] ? $this->baseUrlPattern($request) : null);

        // Rodar de novo com o mesmo arquivo é seguro (o que já foi importado é ignorado), mas não é preciso guardar dados pessoais
        $this->removeUpload($token);

        return $this->render('superadmin/tenant/import_result.html.twig', [
            'result' => $result,
        ]);
    }

    #[Route('/onboard', name: 'onboard', methods: ['GET', 'POST'])]
    public function onboard(Request $request): Response
    {
        $values = [
            'tenantName'     => '',
            'domain'         => '',
            'baseDomain'     => self::DEFAULT_BASE_DOMAIN,
            'theme'          => 'nepe',
            'primaryColor'   => '#0044cc',
            'secondaryColor' => '#ffaa00',
            'adminName'      => '',
            'adminEmail'     => '',
            'whatsapp'       => '',
            'sendEmail'      => true,
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            foreach (array_keys($values) as $field) {
                $values[$field] = $field === 'sendEmail'
                    ? $request->request->getBoolean($field)
                    : trim((string) $request->request->get($field, ''));
            }

            if (!$this->isCsrfTokenValid('tenant_onboard', (string) $request->request->get('_token'))) {
                $errors[] = 'Sessão expirada. Envie o formulário de novo.';
            }

            $baseDomain = null;
            if ($values['baseDomain'] !== '') {
                $baseDomain = TenantImportParser::normalizeDomain($values['baseDomain']);
                if ($baseDomain === null) {
                    $errors[] = sprintf('Domínio base inválido: "%s".', $values['baseDomain']);
                }
            }

            $row = $this->parser->parseValues([
                'tenantName'     => $values['tenantName'],
                'domain'         => $values['domain'],
                'theme'          => $values['theme'],
                'primaryColor'   => $values['primaryColor'],
                'secondaryColor' => $values['secondaryColor'],
                'adminName'      => $values['adminName'],
                'adminEmail'     => $values['adminEmail'],
                'whatsapp'       => $values['whatsapp'],
            ], $baseDomain);

            foreach (['logoFile' => 'Logo (fundo claro)', 'darkLogoFile' => 'Logo (fundo escuro)'] as $field => $label) {
                $file = $request->files->get($field);
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                if (!$file->isValid()) {
                    $errors[] = sprintf('%s: %s', $label, $file->getErrorMessage());
                    continue;
                }
                $row->$field = $file;
            }

            if ($errors === [] && $row->isValid()) {
                $this->importer->checkExistingRecords([$row], resend: false);
            }

            if ($errors === [] && $row->isValid()) {
                $result = $this->importer->import([$row], [], $values['sendEmail'] ? $this->baseUrlPattern($request) : null);

                if ($result->created === 1) {
                    $this->addFlash('success', sprintf(
                        'NEPE "%s" cadastrado em %s com o administrador %s.%s',
                        $row->tenantName, $row->domain, $row->adminEmail,
                        $result->emailed === 1 ? ' O e-mail para definir a senha foi enviado.' : '',
                    ));
                }
                foreach ([...$row->warnings, ...$result->problemMessages(withLine: false)] as $message) {
                    $this->addFlash($result->created === 1 ? 'warning' : 'error', $message);
                }

                return $result->created === 1
                    ? $this->redirectToRoute('superadmin_dash')
                    : $this->redirectToRoute('superadmin_tenant_onboard');
            }

            $errors = [...$errors, ...$row->errors];
        }

        return $this->render('superadmin/tenant/onboard.html.twig', [
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{baseDomain: string, email: bool, resend: bool}
     */
    private function readOptions(array $input): array
    {
        $email = filter_var($input['email'] ?? false, FILTER_VALIDATE_BOOL);

        return [
            'baseDomain' => trim((string) ($input['baseDomain'] ?? '')),
            'email'      => $email,
            // Reenviar sem enviar e-mail não faz sentido
            'resend'     => $email && filter_var($input['resend'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param array{baseDomain: string, email: bool, resend: bool} $options
     *
     * @return array<string, string>
     */
    private function optionsQuery(array $options): array
    {
        return [
            'baseDomain' => $options['baseDomain'],
            'email'      => $options['email'] ? '1' : '0',
            'resend'     => $options['resend'] ? '1' : '0',
        ];
    }

    /** @param array{baseDomain: string, email: bool, resend: bool} $options */
    private function validateUpload(Request $request, array $options): ?string
    {
        if (!$this->isCsrfTokenValid('tenant_import_upload', (string) $request->request->get('_token'))) {
            return 'Sessão expirada. Envie o arquivo de novo.';
        }

        $file = $request->files->get('csv');
        if (!$file instanceof UploadedFile) {
            return 'Escolha o arquivo CSV.';
        }
        if (!$file->isValid()) {
            return $file->getErrorMessage();
        }
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return 'O arquivo precisa ser CSV (na planilha: Arquivo > Fazer download > Valores separados por vírgula).';
        }
        if ($file->getSize() > self::MAX_CSV_BYTES) {
            return 'O arquivo passa de 2 MB.';
        }

        if ($options['baseDomain'] !== '' && TenantImportParser::normalizeDomain($options['baseDomain']) === null) {
            return sprintf('Domínio base inválido: "%s".', $options['baseDomain']);
        }

        // Cabeçalho errado aparece já aqui, antes de guardar o arquivo
        try {
            $this->parser->parseFile($file->getPathname(), TenantImportParser::normalizeDomain($options['baseDomain']));
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @param array{baseDomain: string, email: bool, resend: bool} $options
     *
     * @return list<TenantImportRow>|Response
     */
    private function parseUpload(string $token, array $options): array|Response
    {
        $path = $this->uploadPath($token);
        if (!is_file($path)) {
            $this->addFlash('error', 'O arquivo enviado não está mais disponível. Envie o CSV de novo.');
            return $this->redirectToRoute('superadmin_tenant_import');
        }

        $baseDomain = $options['baseDomain'] !== '' ? TenantImportParser::normalizeDomain($options['baseDomain']) : null;

        try {
            return $this->parser->parseFile($path, $baseDomain);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('superadmin_tenant_import');
        }
    }

    /** Links do e-mail no mesmo protocolo e porta do painel (ex.: http e :8000 em desenvolvimento) */
    private function baseUrlPattern(Request $request): string
    {
        $port = $request->getPort();
        $defaultPort = $request->isSecure() ? 443 : 80;

        return $request->getScheme() . '://%s' . ($port !== null && (int) $port !== $defaultPort ? ':' . $port : '');
    }

    private function uploadPath(string $token): string
    {
        return $this->uploadDir . '/' . $token . '.csv';
    }

    private function removeUpload(string $token): void
    {
        if (is_file($path = $this->uploadPath($token))) {
            unlink($path);
        }
    }

    private function removeExpiredUploads(): void
    {
        foreach (glob($this->uploadDir . '/*.csv') ?: [] as $path) {
            if (filemtime($path) < time() - self::CSV_LIFETIME) {
                unlink($path);
            }
        }
    }
}
