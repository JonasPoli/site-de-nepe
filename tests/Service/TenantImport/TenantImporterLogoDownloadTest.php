<?php

namespace App\Tests\Service\TenantImport;

use App\Service\TenantImport\PublicHostChecker;
use App\Service\TenantImport\TenantImporter;
use App\Service\TenantImport\TenantImportResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Cobre só o download da logo: o método é privado, então é exercido por reflexão,
 * sem banco, sem e-mail e sem rede.
 */
class TenantImporterLogoDownloadTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function download(MockHttpClient $client, string $url, array $dns = []): array
    {
        $checker = new PublicHostChecker(true, static fn (string $host): array => $dns[$host] ?? ['200.160.2.3']);

        $importer = new TenantImporter(
            $this->createStub(\App\Repository\TenantRepository::class),
            $this->createStub(\App\Repository\UserRepository::class),
            $this->createStub(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createStub(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class),
            $this->createStub(\App\Service\PasswordResetMailer::class),
            $client,
            $checker,
            $this->createStub(\Vich\UploaderBundle\Storage\StorageInterface::class),
        );

        $result = new TenantImportResult();

        $metodo = new \ReflectionMethod($importer, 'downloadLogo');
        $path = $metodo->invoke($importer, $url, 2, 'Logo (fundo claro)', $result);

        if (is_string($path)) {
            $this->temp[] = $path;
        }

        return [$path, $result];
    }

    private function png(int $bytes = 0): string
    {
        // PNG mínimo de 1x1 que o MimeTypes reconhece, com enchimento opcional
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return $png . str_repeat("\0", max(0, $bytes - strlen($png)));
    }

    public function testBaixaLogoValida(): void
    {
        $client = new MockHttpClient(new MockResponse($this->png(), ['response_headers' => ['content-type' => 'image/png']]));

        [$path, $result] = $this->download($client, 'https://exemplo.org/logo.png');

        $this->assertIsString($path);
        $this->assertSame([], $result->problemMessages(withLine: false));
        $this->assertSame('image/png', mime_content_type($path));
    }

    /**
     * O caso que motivou a correção: sem content-length, o arquivo inteiro era
     * carregado na memória antes da conferência de 5 MB.
     */
    public function testInterrompeArquivoGrandeSemContentLength(): void
    {
        // Corpo em pedaços: o tamanho não é conhecido de antemão, então não há content-length
        // e o corte só pode vir da leitura em pedaços — que é o caminho em teste aqui.
        $pedacos = (static function (): \Generator {
            for ($i = 0; $i < 60; ++$i) {
                yield str_repeat('x', 128 * 1024);
            }
        })();

        $client = new MockHttpClient(new MockResponse($pedacos, ['response_headers' => ['content-type' => 'image/png']]));

        [$path, $result] = $this->download($client, 'https://exemplo.org/logo.png');

        $this->assertNull($path);
        $this->assertStringContainsString('maior que 5 MB', implode(' ', $result->problemMessages(withLine: false)));
    }

    public function testRecusaArquivoGrandeAnunciadoNoContentLength(): void
    {
        $grande = str_repeat('x', 6 * 1024 * 1024);
        $client = new MockHttpClient(new MockResponse($grande, [
            'response_headers' => ['content-length' => (string) strlen($grande)],
        ]));

        [$path, $result] = $this->download($client, 'https://exemplo.org/logo.png');

        $this->assertNull($path);
        $this->assertStringContainsString('maior que 5 MB', implode(' ', $result->problemMessages(withLine: false)));
    }

    public function testRecusaEnderecoInternoSemFazerRequisicao(): void
    {
        $pedidos = 0;
        $client = new MockHttpClient(function () use (&$pedidos): MockResponse {
            ++$pedidos;

            return new MockResponse($this->png());
        });

        [$path, $result] = $this->download($client, 'http://169.254.169.254/latest/meta-data/');

        $this->assertNull($path);
        $this->assertSame(0, $pedidos, 'nenhuma requisição deveria ser feita a endereço interno');
        $this->assertStringContainsString('rede interna', implode(' ', $result->problemMessages(withLine: false)));
    }

    public function testRecusaRedirecionamentoParaEnderecoInterno(): void
    {
        $pedidos = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$pedidos): MockResponse {
            $pedidos[] = $url;

            if (str_contains($url, 'publico.exemplo.org')) {
                return new MockResponse('', [
                    'http_code' => 302,
                    'response_headers' => ['location' => 'http://interno.exemplo.org/logo.png'],
                ]);
            }

            return new MockResponse($this->png());
        });

        [$path, $result] = $this->download($client, 'https://publico.exemplo.org/logo.png', [
            'publico.exemplo.org' => ['200.160.2.3'],
            'interno.exemplo.org' => ['10.0.0.5'],
        ]);

        $this->assertNull($path);
        $this->assertSame(['https://publico.exemplo.org/logo.png'], $pedidos, 'o salto interno não deveria ser requisitado');
        $this->assertStringContainsString('rede interna', implode(' ', $result->problemMessages(withLine: false)));
    }

    public function testSegueRedirecionamentoParaEnderecoPublico(): void
    {
        $client = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (str_contains($url, 'primeiro')) {
                return new MockResponse('', [
                    'http_code' => 302,
                    'response_headers' => ['location' => 'https://cdn.exemplo.org/logo.png'],
                ]);
            }

            return new MockResponse($this->png(), ['response_headers' => ['content-type' => 'image/png']]);
        });

        [$path, $result] = $this->download($client, 'https://primeiro.exemplo.org/logo.png', [
            'primeiro.exemplo.org' => ['200.160.2.3'],
            'cdn.exemplo.org' => ['200.160.2.4'],
        ]);

        $this->assertIsString($path);
        $this->assertSame([], $result->problemMessages(withLine: false));
    }

    /** A página de login do Drive volta como HTML: o conteúdo é conferido, não o cabeçalho */
    public function testRecusaConteudoQueNaoEImagem(): void
    {
        $client = new MockHttpClient(new MockResponse('<!doctype html><html><body>Entrar</body></html>', [
            'response_headers' => ['content-type' => 'image/png'],
        ]));

        [$path, $result] = $this->download($client, 'https://drive.google.com/uc?export=download&id=abc');

        $this->assertNull($path);
        $this->assertStringContainsString('Qualquer pessoa com o link', implode(' ', $result->problemMessages(withLine: false)));
    }
}
