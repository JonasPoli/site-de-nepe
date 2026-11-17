<?php

namespace App\Tests\Security;

use App\Security\ProfilePolicy;
use PHPUnit\Framework\TestCase;

/**
 * A foto enviada pelo super admin na edição de um usuário segue a mesma regra
 * do perfil do próprio usuário: um arquivo recusado não altera a foto gravada.
 */
class SuperAdminPhotoPolicyTest extends TestCase
{
    public function testAceitaOsTiposPermitidos(): void
    {
        foreach (['image/jpeg', 'image/png', 'image/webp'] as $mime) {
            $this->assertNull(
                ProfilePolicy::photoError($mime, 1024 * 1024),
                sprintf('%s deveria ser aceito', $mime),
            );
        }
    }

    public function testRecusaArquivoQueNaoEImagem(): void
    {
        $erro = ProfilePolicy::photoError('application/pdf', 1024);

        $this->assertNotNull($erro);
        $this->assertStringContainsString('JPEG, PNG ou WebP', $erro);
    }

    public function testRecusaGifPorqueNaoEstaNaLista(): void
    {
        $this->assertNotNull(ProfilePolicy::photoError('image/gif', 1024));
    }

    public function testRecusaArquivoMaiorQueOLimite(): void
    {
        $erro = ProfilePolicy::photoError('image/png', 6 * 1024 * 1024);

        $this->assertNotNull($erro);
        $this->assertStringContainsString('4 MB', $erro);
    }

    public function testAceitaArquivoNoLimite(): void
    {
        $this->assertNull(ProfilePolicy::photoError('image/png', 4 * 1024 * 1024));
    }

    public function testRecusaQuandoOTipoNaoEConhecido(): void
    {
        $this->assertNotNull(ProfilePolicy::photoError(null, 1024));
    }
}
