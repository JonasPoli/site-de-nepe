<?php

namespace App\Tests\Controller\admin;

use App\Controller\admin\ProfileController;
use App\Security\ProfilePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * O perfil abre para todo papel do painel (ROLE_ADMIN, não ROLE_NEPE_ADMIN) e sempre
 * sobre o usuário da sessão: nenhum id na rota, nenhum papel ou tenant gravado.
 */
class ProfileRouteTest extends TestCase
{
    public function testRouteIsAdminPerfilWithoutAnyParameter(): void
    {
        $routes = array_map(
            static fn (\ReflectionAttribute $attribute): Route => $attribute->newInstance(),
            (new \ReflectionClass(ProfileController::class))->getAttributes(Route::class),
        );

        $this->assertCount(1, $routes);
        $this->assertSame('/admin/perfil', $routes[0]->getPath());
        $this->assertSame('admin_profile', $routes[0]->getName());
        $this->assertStringNotContainsString('{', $routes[0]->getPath(), 'sem id de usuário na rota');
    }

    public function testEveryPanelRoleReachesTheProfile(): void
    {
        $roles = array_map(
            static fn (\ReflectionAttribute $attribute): mixed => $attribute->newInstance()->attribute,
            (new \ReflectionClass(ProfileController::class))->getAttributes(IsGranted::class),
        );

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertNotContains('ROLE_NEPE_ADMIN', $roles, 'editor e revisor também editam o próprio perfil');
    }

    public function testControllerTakesTheUserFromTheSessionOnly(): void
    {
        $code = (string) file_get_contents((new \ReflectionClass(ProfileController::class))->getFileName());

        $this->assertStringContainsString('$this->getUser()', $code);
        // Nada de buscar usuário por id/username vindo do request
        $this->assertStringNotContainsString('$users->find(', $code);
        $this->assertStringNotContainsString("request->get('id')", $code);
        $this->assertStringNotContainsString("request->get('user')", $code);
    }

    public function testControllerNeverWritesRoleOrTenant(): void
    {
        $code = (string) file_get_contents((new \ReflectionClass(ProfileController::class))->getFileName());

        foreach (['setWorkGroup(', 'setTenant(', 'setRoles('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, $forbidden . ' não pertence ao perfil');
        }

        $this->assertSame(['workGroup', 'tenant', 'roles', 'id'], ProfilePolicy::IGNORED_FIELDS);
    }
}
