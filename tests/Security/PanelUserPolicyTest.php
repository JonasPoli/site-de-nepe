<?php

namespace App\Tests\Security;

use App\Entity\Tenant;
use App\Security\PanelUserPolicy;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;

class PanelUserPolicyTest extends TestCase
{
    use EntityIds;

    private Tenant $alcione;

    protected function setUp(): void
    {
        $this->alcione = self::tenantWithId(6, 'nepealcione.nepebrasil.org');
    }

    public function testAcceptsOnlyEditorAndReviewer(): void
    {
        $this->assertTrue(PanelUserPolicy::acceptsWorkGroup(1));
        $this->assertTrue(PanelUserPolicy::acceptsWorkGroup(2));
        $this->assertFalse(PanelUserPolicy::acceptsWorkGroup(0), 'grupo 0 enviado à mão');
        $this->assertFalse(PanelUserPolicy::acceptsWorkGroup(3));
        $this->assertFalse(PanelUserPolicy::acceptsWorkGroup(-1));
    }

    public function testAdminDeletesEditorsAndReviewersOfTheDomainTenant(): void
    {
        $admin = self::userWithId(10, $this->alcione, 0);

        $this->assertTrue(PanelUserPolicy::canDelete($admin, self::userWithId(11, self::tenantWithId(6), 1), $this->alcione));
        $this->assertTrue(PanelUserPolicy::canDelete($admin, self::userWithId(12, self::tenantWithId(6), 2), $this->alcione));
    }

    public function testSuperAdminDeletesEditorsOfTheDomainTenant(): void
    {
        $this->assertTrue(PanelUserPolicy::canDelete(self::userWithId(1, null, 0), self::userWithId(11, $this->alcione, 1), $this->alcione));
    }

    public function testNeverDeletesUsersOfAnotherTenantOrWithoutTenant(): void
    {
        $admin = self::userWithId(10, $this->alcione, 0);

        $this->assertFalse(PanelUserPolicy::canDelete($admin, self::userWithId(20, self::tenantWithId(3), 1), $this->alcione));
        $this->assertFalse(PanelUserPolicy::canDelete($admin, self::userWithId(21, null, 1), $this->alcione));
    }

    public function testNeverDeletesAdministratorsOrSuperAdmins(): void
    {
        $admin = self::userWithId(10, $this->alcione, 0);

        $this->assertFalse(PanelUserPolicy::canDelete($admin, self::userWithId(13, self::tenantWithId(6), 0), $this->alcione), 'outro administrador');
        $this->assertFalse(PanelUserPolicy::canDelete($admin, self::userWithId(1, null, 0), $this->alcione), 'super admin');
        $this->assertFalse(PanelUserPolicy::canDelete(self::userWithId(1, null, 0), self::userWithId(6, null, 0), $this->alcione), 'super admin exclui outro super admin');
    }

    public function testNeverDeletesHimself(): void
    {
        $editor = self::userWithId(11, $this->alcione, 1);

        $this->assertFalse(PanelUserPolicy::canDelete($editor, $editor, $this->alcione));
        $this->assertFalse(PanelUserPolicy::canDelete($editor, self::userWithId(11, self::tenantWithId(6), 1), $this->alcione));
    }
}
