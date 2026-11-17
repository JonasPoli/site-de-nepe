<?php

namespace App\Tests\Service\TenantImport;

use App\Service\TenantImport\PublicHostChecker;
use PHPUnit\Framework\TestCase;

class PublicHostCheckerTest extends TestCase
{
    /** O DNS real não é consultado: o resolvedor é injetado para o teste não depender da rede */
    private function checker(array $dns = [], bool $enabled = true): PublicHostChecker
    {
        return new PublicHostChecker($enabled, static fn (string $host): array => $dns[$host] ?? []);
    }

    public function testAceitaEnderecoPublico(): void
    {
        $checker = $this->checker(['exemplo.org' => ['200.160.2.3']]);

        $this->assertNull($checker->rejectionReason('https://exemplo.org/logo.png'));
        $this->assertTrue($checker->isAllowed('https://exemplo.org/logo.png'));
    }

    /** @dataProvider enderecosInternos */
    public function testRecusaEnderecoInterno(string $url): void
    {
        $checker = $this->checker([
            'interno.exemplo.org' => ['10.1.2.3'],
            'metadados.exemplo.org' => ['169.254.169.254'],
        ]);

        $motivo = $checker->rejectionReason($url);

        $this->assertNotNull($motivo, sprintf('%s deveria ser recusado', $url));
        $this->assertStringContainsString('rede interna', $motivo);
        $this->assertFalse($checker->isAllowed($url));
    }

    public static function enderecosInternos(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/logo.png'];
        yield 'loopback por nome' => ['http://interno.exemplo.org/logo.png'];
        yield 'rede privada 10/8' => ['http://10.0.0.1/logo.png'];
        yield 'rede privada 192.168/16' => ['http://192.168.0.1/logo.png'];
        yield 'metadados 169.254' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'metadados por nome' => ['http://metadados.exemplo.org/'];
        yield 'loopback IPv6' => ['http://[::1]/logo.png'];
    }

    public function testRecusaHostQueNaoResolve(): void
    {
        $motivo = $this->checker()->rejectionReason('https://nao-existe.exemplo.org/logo.png');

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('DNS', $motivo);
    }

    public function testRecusaEsquemaQueNaoEHttp(): void
    {
        $motivo = $this->checker()->rejectionReason('file:///etc/passwd');

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('http', $motivo);
    }

    /** Um host com IPv4 público e IPv6 interno não pode passar pelo IPv4 */
    public function testRecusaQuandoUmDosEnderecosEInterno(): void
    {
        $checker = $this->checker(['misto.exemplo.org' => ['200.160.2.3', 'fe80::1']]);

        $this->assertNotNull($checker->rejectionReason('https://misto.exemplo.org/logo.png'));
    }

    public function testDesligadoAceitaLocalhost(): void
    {
        $checker = $this->checker(enabled: false);

        $this->assertNull($checker->rejectionReason('http://127.0.0.1:8000/uploads/logo.png'));
        $this->assertFalse($checker->isEnabled());
    }
}
