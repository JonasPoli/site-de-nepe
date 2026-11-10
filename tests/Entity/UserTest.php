<?php

namespace App\Tests\Entity;

use App\Entity\Tenant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGroupZeroWithoutTenantIsSuperAdminAndNepeAdmin(): void
    {
        $roles = (new User())->setWorkGroup(0)->getRoles();

        $this->assertContains('ROLE_SUPER_ADMIN', $roles);
        $this->assertContains('ROLE_NEPE_ADMIN', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGroupZeroWithTenantIsNepeAdminButNotSuperAdmin(): void
    {
        $roles = (new User())->setWorkGroup(0)->setTenant(new Tenant())->getRoles();

        $this->assertContains('ROLE_NEPE_ADMIN', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertNotContains('ROLE_SUPER_ADMIN', $roles);
    }

    public function testEditorAndReviewerEnterThePanelWithoutManagingTheNepe(): void
    {
        foreach ([1 => 'editor', 2 => 'revisor'] as $workGroup => $label) {
            $roles = (new User())->setWorkGroup($workGroup)->setTenant(new Tenant())->getRoles();

            $this->assertContains('ROLE_EDITOR', $roles, $label);
            $this->assertContains('ROLE_ADMIN', $roles, $label);
            $this->assertNotContains('ROLE_NEPE_ADMIN', $roles, $label);
            $this->assertNotContains('ROLE_SUPER_ADMIN', $roles, $label);
        }
    }

    public function testStoredRolesAreKeptWithoutDuplicates(): void
    {
        // Os ids 1 e 6 de produção têm ROLE_SUPER_ADMIN e ROLE_ADMIN gravados na coluna roles
        $roles = (new User())->setWorkGroup(0)->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'])->getRoles();

        $this->assertSame(1, count(array_keys($roles, 'ROLE_SUPER_ADMIN', true)));
        $this->assertSame(1, count(array_keys($roles, 'ROLE_ADMIN', true)));
        $this->assertContains('ROLE_NEPE_ADMIN', $roles);
    }
}
