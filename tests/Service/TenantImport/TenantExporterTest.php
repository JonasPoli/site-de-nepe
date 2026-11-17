<?php

namespace App\Tests\Service\TenantImport;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Service\TenantImport\TenantExporter;
use App\Service\TenantImport\TenantImportParser;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;

class TenantExporterTest extends TestCase
{
    use EntityIds;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tenant(int $id, string $nome, string $dominio, array $extra = []): Tenant
    {
        $tenant = (new Tenant())
            ->setName($nome)
            ->setDomain($dominio)
            ->setTheme($extra['theme'] ?? 'nepe')
            ->setPrimaryColor($extra['primaryColor'] ?? '#0044cc')
            ->setSecondaryColor($extra['secondaryColor'] ?? '#ffaa00');

        if (isset($extra['logo'])) {
            $tenant->setLogo($extra['logo']);
        }
        if (isset($extra['darkLogo'])) {
            $tenant->setDarkLogo($extra['darkLogo']);
        }
        if (isset($extra['phone'])) {
            $tenant->setPhone($extra['phone']);
        }

        return self::withId($tenant, $id);
    }

    private function admin(int $id, string $nome, string $email, ?Tenant $tenant): User
    {
        $user = (new User())
            ->setName($nome)
            ->setUsername($email)
            ->setEmail($email)
            ->setWorkGroup(0)
            ->setTenant($tenant);

        return self::withId($user, $id);
    }

    /**
     * @param list<Tenant> $tenants
     * @param list<User>   $admins
     */
    private function exporter(array $tenants, array $admins = []): TenantExporter
    {
        $tenantRepo = $this->createStub(TenantRepository::class);
        $tenantRepo->method('findBy')->willReturn($tenants);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findBy')->willReturn($admins);

        return new TenantExporter($tenantRepo, $userRepo);
    }

    /**
     * O contrato da ida e volta: todo título precisa ser reconhecido pelo parser,
     * que casa as colunas pelo início do texto, sem acento e sem maiúsculas.
     */
    public function testTodosOsTitulosSaoReconhecidosPeloParser(): void
    {
        foreach (TenantExporter::COLUMN_TITLES as $campo => $titulo) {
            $this->assertArrayHasKey($campo, TenantImportParser::COLUMNS, sprintf('campo "%s" não existe na importação', $campo));

            $prefixos = TenantImportParser::COLUMNS[$campo];
            $normalizado = TenantImportParser::normalizeLabel($titulo);

            $casou = array_filter($prefixos, static fn (string $p): bool => str_starts_with($normalizado, $p));

            $this->assertNotEmpty($casou, sprintf(
                'o título "%s" não começa por nenhum prefixo aceito para "%s" (%s)',
                $titulo, $campo, implode(', ', $prefixos),
            ));
        }
    }

    public function testExportaUmaLinhaPorNepeComOsDadosDoAdministrador(): void
    {
        $alcione = $this->tenant(1, 'NEPE Alcíone', 'nepealcione.nepebrasil.org', ['phone' => '(19) 99999-0000']);

        $exporter = $this->exporter(
            [$alcione],
            [$this->admin(10, 'Silvia Andrea', 'silvia@exemplo.org', $alcione)],
        );

        $rows = $exporter->rows();

        $this->assertCount(1, $rows);
        $this->assertSame('NEPE Alcíone', $rows[0][0]);
        $this->assertSame('nepealcione.nepebrasil.org', $rows[0][1]);
        $this->assertSame('Silvia Andrea', $rows[0][7]);
        $this->assertSame('silvia@exemplo.org', $rows[0][8]);
        $this->assertSame('(19) 99999-0000', $rows[0][9]);
    }

    public function testNepeComVariosAdministradoresExportaOMaisAntigo(): void
    {
        $tenant = $this->tenant(1, 'NEPE Dois Admins', 'dois.nepebrasil.org');

        $exporter = $this->exporter([$tenant], [
            $this->admin(10, 'Primeiro', 'primeiro@exemplo.org', $tenant),
            $this->admin(20, 'Segundo', 'segundo@exemplo.org', $tenant),
        ]);

        $rows = $exporter->rows();

        $this->assertCount(1, $rows, 'o NEPE sai numa linha só');
        $this->assertSame('Primeiro', $rows[0][7]);
    }

    public function testNepeSemAdministradorSaiComAsColunasDoAdminVazias(): void
    {
        $exporter = $this->exporter([$this->tenant(1, 'NEPE Sem Admin', 'sem.nepebrasil.org')]);

        $rows = $exporter->rows();

        $this->assertSame('', $rows[0][7]);
        $this->assertSame('', $rows[0][8]);
    }

    public function testLogoSaiComoEnderecoPublicoDoProprioNepe(): void
    {
        $exporter = $this->exporter([
            $this->tenant(1, 'NEPE Com Logo', 'comlogo.nepebrasil.org', ['logo' => 'logo-abc.png']),
        ]);

        $rows = $exporter->rows();

        $this->assertSame('https://comlogo.nepebrasil.org/uploads/tenant/logo/logo-abc.png', $rows[0][5]);
        $this->assertSame('', $rows[0][6], 'sem logo escura, a coluna fica vazia');
    }

    public function testArquivoSemNepeTemSoOCabecalho(): void
    {
        $csv = $this->exporter([])->toCsv();

        $linhas = array_values(array_filter(explode("\n", trim($csv))));

        $this->assertCount(1, $linhas);
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $csv, 'o arquivo não deve ter BOM');
    }

    public function testNomeDoArquivoTrazADataDoDia(): void
    {
        $nome = $this->exporter([])->filename(new \DateTimeImmutable('2026-11-17'));

        $this->assertSame('nepes-2026-11-17.csv', $nome);
    }

    /** O teste de ida e volta de verdade: exporta, passa ao parser e confere que volta igual */
    public function testOArquivoExportadoVoltaPelaImportacao(): void
    {
        $alcione = $this->tenant(1, 'NEPE Alcíone, Araraquara', 'nepealcione.nepebrasil.org', [
            'theme' => 'moderno',
            'primaryColor' => '#112233',
            'secondaryColor' => '#445566',
            'logo' => 'logo-abc.png',
            'phone' => '(19) 99999-0000',
        ]);

        $csv = $this->exporter(
            [$alcione],
            [$this->admin(10, 'Silvia Andrea', 'silvia@exemplo.org', $alcione)],
        )->toCsv();

        $caminho = tempnam(sys_get_temp_dir(), 'tenant-export-');
        $this->files[] = $caminho;
        file_put_contents($caminho, $csv);

        $rows = (new TenantImportParser())->parseFile($caminho);

        $this->assertCount(1, $rows);
        $row = $rows[0];

        $this->assertSame([], $row->errors, 'a linha exportada precisa ser válida na importação');
        $this->assertSame('NEPE Alcíone, Araraquara', $row->tenantName, 'o nome com vírgula precisa voltar inteiro');
        $this->assertSame('nepealcione.nepebrasil.org', $row->domain);
        $this->assertSame('moderno', $row->theme);
        $this->assertSame('#112233', $row->primaryColor);
        $this->assertSame('#445566', $row->secondaryColor);
        $this->assertSame('https://nepealcione.nepebrasil.org/uploads/tenant/logo/logo-abc.png', $row->logoUrl);
        $this->assertSame('Silvia Andrea', $row->adminName);
        $this->assertSame('silvia@exemplo.org', $row->adminEmail);
        $this->assertSame('(19) 99999-0000', $row->phone);
    }
}
