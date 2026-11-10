<?php

namespace App\Tests\Controller\admin;

use App\Controller\admin\AdminContentController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestão de usuários e configurações exige o administrador do NEPE: editores e revisores
 * também têm ROLE_ADMIN (o da classe) e não podem criar usuários nem mudar as aprovações.
 */
class NepeAdminAccessTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function managementActions(): iterable
    {
        yield 'listar usuários' => ['editorIndex'];
        yield 'criar usuário' => ['editorNew'];
        yield 'excluir usuário' => ['editorDelete'];
        yield 'configurações' => ['settings'];
    }

    /** @dataProvider managementActions */
    public function testActionRequiresNepeAdmin(string $method): void
    {
        $roles = array_map(
            static fn (\ReflectionAttribute $attribute): mixed => $attribute->newInstance()->attribute,
            (new \ReflectionMethod(AdminContentController::class, $method))->getAttributes(IsGranted::class),
        );

        $this->assertContains('ROLE_NEPE_ADMIN', $roles);
    }
}
