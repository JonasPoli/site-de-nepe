<?php

namespace App\Tests\Service\TenantImport;

use App\Service\TenantImport\TenantImportParser;
use PHPUnit\Framework\TestCase;

class TenantImportParserTest extends TestCase
{
    /** Header exported by Google Sheets for the questions suggested for the form */
    private const GOOGLE_FORMS_HEADER = 'Carimbo de data/hora,Nome da instituição,"Domínio do site (ex.: nepe.org.br, sem http e sem www)",Tema visual,Cor principal (#RRGGBB),Cor secundária,Logo (fundo claro),Logo (fundo escuro),Nome completo,E-mail,WhatsApp,Autorizo o uso destes dados para criar o site e receber e-mails de acesso.';

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

    public function testParsesGoogleFormsExport(): void
    {
        $rows = (new TenantImportParser())->parseFile($this->csv(
            self::GOOGLE_FORMS_HEADER,
            '13/09/2026 10:00:00,  NEPE   Campinas ,https://WWW.Nepe-Campinas.org.br/,"Moderno (dark, vibrante)",0af,#FFAA00,https://drive.google.com/open?id=abc_123-X,,Maria Souza,Maria@Example.com,(19) 99999-0000,Autorizo',
            ',,,,,,,,,,,',
        ));

        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame([], $row->errors);
        $this->assertSame(2, $row->line);
        $this->assertSame('NEPE Campinas', $row->tenantName);
        $this->assertSame('nepe-campinas.org.br', $row->domain);
        $this->assertSame('moderno', $row->theme);
        $this->assertSame('#00aaff', $row->primaryColor);
        $this->assertSame('#ffaa00', $row->secondaryColor);
        $this->assertSame('https://drive.google.com/uc?export=download&id=abc_123-X', $row->logoUrl);
        $this->assertNull($row->darkLogoUrl);
        $this->assertSame('Maria Souza', $row->adminName);
        $this->assertSame('maria@example.com', $row->adminEmail);
        $this->assertSame(['O "www." foi removido do domínio.'], $row->warnings);
    }

    public function testReadsSemicolonSeparatedFileWithOnlyRequiredColumns(): void
    {
        $rows = (new TenantImportParser())->parseFile($this->csv(
            'Nome da instituição;Domínio;Nome completo;E-mail',
            'Instituto Alfa;alfa.org;João Silva;joao@alfa.org',
        ));

        $this->assertCount(1, $rows);
        $this->assertSame([], $rows[0]->errors);
        $this->assertSame('alfa.org', $rows[0]->domain);
        $this->assertSame('nepe', $rows[0]->theme);
        $this->assertNull($rows[0]->primaryColor);
    }

    public function testMissingRequiredColumnsThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Colunas obrigatórias não encontradas: "nome completo…", "e-mail…"');

        (new TenantImportParser())->parseFile($this->csv('Nome da instituição,Domínio', 'Alfa,alfa.org'));
    }

    public function testAmbiguousColumnsThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mais de uma coluna começa com "e-mail"');

        (new TenantImportParser())->parseFile($this->csv(
            'Nome da instituição,Domínio,Nome completo,E-mail,E-mail de contato da instituição',
            'Alfa,alfa.org,Ana,ana@alfa.org,contato@alfa.org',
        ));
    }

    public function testBaseDomainGeneratesSubdomainForAnswersWithoutDomain(): void
    {
        $file = $this->csv('Nome da instituição,Domínio,Nome completo,E-mail', 'Núcleo São José,,Ana,ana@x.org');

        $rows = (new TenantImportParser())->parseFile($file, 'nepe.org.br');
        $this->assertSame([], $rows[0]->errors);
        $this->assertSame('nucleo-sao-jose.nepe.org.br', $rows[0]->domain);
        $this->assertSame(['Sem domínio: usando nucleo-sao-jose.nepe.org.br.'], $rows[0]->warnings);

        $rows = (new TenantImportParser())->parseFile($file);
        $this->assertSame(['Domínio não informado (use --base-domain para gerar um subdomínio).'], $rows[0]->errors);
    }

    public function testReportsInvalidValues(): void
    {
        $rows = (new TenantImportParser())->parseFile($this->csv(
            'Nome da instituição,Domínio,Tema,Cor principal,Nome completo,E-mail,Autorizo o uso dos dados',
            ',nepe org,Clássico,azul,,not-an-email,',
        ));

        $this->assertSame([
            'Nome da instituição não informado.',
            'Domínio inválido: "nepe org".',
            'Nome do administrador não informado.',
            'E-mail do administrador inválido: "not-an-email".',
            'Sem autorização para uso dos dados (LGPD).',
        ], $rows[0]->errors);
        $this->assertSame([
            'Tema "Clássico" não reconhecido: usando Nepe.',
            'Cor principal inválida ("azul"): mantida a cor padrão.',
        ], $rows[0]->warnings);
    }

    public function testRepeatedDomainKeepsTheLastAnswer(): void
    {
        $rows = (new TenantImportParser())->parseFile($this->csv(
            'Nome da instituição,Domínio,Nome completo,E-mail',
            'Alfa,alfa.org,Ana,ana@alfa.org',
            'Beta,beta.org,Bia,ana@alfa.org',
            'Alfa,www.alfa.org,Ana Paula,anapaula@alfa.org',
        ));

        $this->assertSame(['Domínio repetido: substituído pela resposta da linha 4.'], $rows[0]->errors);
        // Line 2 was replaced, so its e-mail is free for line 3
        $this->assertSame([], $rows[1]->errors);
        $this->assertSame([], $rows[2]->errors);
    }

    public function testRepeatedEmailAcrossTenantsIsRejected(): void
    {
        $rows = (new TenantImportParser())->parseFile($this->csv(
            'Nome da instituição,Domínio,Nome completo,E-mail',
            'Alfa,alfa.org,Ana,ana@alfa.org',
            'Beta,beta.org,Ana,ANA@alfa.org',
        ));

        $this->assertSame([], $rows[0]->errors);
        $this->assertSame(['E-mail já usado pelo administrador da linha 2.'], $rows[1]->errors);
    }

    /** @dataProvider domainProvider */
    public function testNormalizeDomain(string $input, ?string $expected): void
    {
        $this->assertSame($expected, TenantImportParser::normalizeDomain($input));
    }

    public static function domainProvider(): iterable
    {
        yield 'plain' => ['nepe.org.br', 'nepe.org.br'];
        yield 'scheme, www, path and query' => [' HTTPS://www.Nepe.org.br/contato?x=1 ', 'nepe.org.br'];
        yield 'port' => ['nepe.org.br:8080', 'nepe.org.br'];
        yield 'subdomain with trailing dot' => ['sub.nepe.org.br.', 'sub.nepe.org.br'];
        yield 'no dot' => ['nepe', null];
        yield 'space' => ['nepe org.br', null];
        yield 'e-mail' => ['joao@nepe.org.br', null];
        yield 'leading hyphen' => ['-nepe.org', null];
    }

    /** @dataProvider colorProvider */
    public function testNormalizeColor(string $input, ?string $expected): void
    {
        $this->assertSame($expected, TenantImportParser::normalizeColor($input));
    }

    public static function colorProvider(): iterable
    {
        yield ['#FFAA00', '#ffaa00'];
        yield ['ffaa00', '#ffaa00'];
        yield ['#0af', '#00aaff'];
        yield ['azul', null];
        yield ['#12345', null];
    }

    /** @dataProvider driveUrlProvider */
    public function testDriveDownloadUrl(string $input, ?string $expected): void
    {
        $this->assertSame($expected, TenantImportParser::driveDownloadUrl($input));
    }

    public static function driveUrlProvider(): iterable
    {
        yield 'forms upload' => ['https://drive.google.com/open?id=1AbC-d_E', 'https://drive.google.com/uc?export=download&id=1AbC-d_E'];
        yield 'share link' => ['https://drive.google.com/file/d/1AbC-d_E/view?usp=sharing', 'https://drive.google.com/uc?export=download&id=1AbC-d_E'];
        yield 'other host' => ['https://example.com/logo.png', null];
    }

    private function csv(string ...$lines): string
    {
        $file = tempnam(sys_get_temp_dir(), 'tenant-import-test-');
        file_put_contents($file, implode("\n", $lines) . "\n");
        $this->files[] = $file;

        return $file;
    }
}
