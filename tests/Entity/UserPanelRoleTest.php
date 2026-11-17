<?php

namespace App\Tests\Entity;

use App\Entity\Tenant;
use App\Entity\User;
use App\Enum\PanelRole;
use PHPUnit\Framework\TestCase;

/**
 * O papel mostrado na listagem tem de bater com o papel real de getRoles():
 * a listagem antiga chamava de "Super Admin" todo usuário sem NEPE.
 */
class UserPanelRoleTest extends TestCase
{
    private function user(?Tenant $tenant, int $workGroup): User
    {
        return (new User())->setUsername('u')->setTenant($tenant)->setWorkGroup($workGroup);
    }

    public function testSuperAdminPrecisaDeGrupoZeroESemNepe(): void
    {
        $user = $this->user(null, 0);

        $this->assertSame(PanelRole::SuperAdmin, $user->panelRole());
        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testAdminDoNepe(): void
    {
        $user = $this->user(new Tenant(), 0);

        $this->assertSame(PanelRole::Admin, $user->panelRole());
        $this->assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
    }

    public function testEditor(): void
    {
        $this->assertSame(PanelRole::Editor, $this->user(new Tenant(), 1)->panelRole());
    }

    public function testRevisor(): void
    {
        $this->assertSame(PanelRole::Reviewer, $this->user(new Tenant(), 2)->panelRole());
    }

    /** @dataProvider gruposSemNepe */
    public function testUsuarioSemNepeForaDoGrupoZeroNaoESuperAdmin(int $workGroup): void
    {
        $user = $this->user(null, $workGroup);

        $this->assertSame(PanelRole::Inconsistent, $user->panelRole());
        $this->assertTrue($user->panelRole()->isInconsistent());
        $this->assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles(), 'o papel real não é de super admin');
    }

    public static function gruposSemNepe(): iterable
    {
        yield 'editor sem NEPE' => [1];
        yield 'revisor sem NEPE' => [2];
    }

    /** O rótulo acompanha o papel, para a listagem não repetir a regra */
    public function testRotulos(): void
    {
        $this->assertSame('Super Admin', PanelRole::SuperAdmin->label());
        $this->assertSame('Revisor', PanelRole::Reviewer->label());
        $this->assertSame('Cadastro inconsistente', PanelRole::Inconsistent->label());
    }

    /** @dataProvider nomes */
    public function testIniciais(string $nome, string $esperado): void
    {
        $user = (new User())->setName($nome);

        $this->assertSame($esperado, $user->initials());
    }

    public static function nomes(): iterable
    {
        yield 'nome composto' => ['Silvia Andrea Mause de Paula', 'SP'];
        yield 'nome simples' => ['Vera', 'V'];
        yield 'sem nome' => ['', ''];
        yield 'espaços à toa' => ['  Quati   Oliveira  ', 'QO'];
        yield 'acento' => ['Álvaro Éder', 'ÁÉ'];
    }
}
