<?php

namespace App\Tests\Security;

use App\Security\PanelAccess;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;

class PanelAccessTest extends TestCase
{
    use EntityIds;

    public function testSuperAdminUsesThePanelOfAnyTenant(): void
    {
        $superAdmin = self::userWithId(1, null, 0);

        $this->assertTrue(PanelAccess::canUsePanel($superAdmin, self::tenantWithId(3)));
        $this->assertTrue(PanelAccess::canUsePanel($superAdmin, self::tenantWithId(6)));
    }

    public function testMembersUseOnlyThePanelOfTheirTenant(): void
    {
        $domain = self::tenantWithId(3);

        foreach ([0, 1, 2] as $workGroup) {
            $sameTenant = self::userWithId(10 + $workGroup, self::tenantWithId(3), $workGroup);
            $otherTenant = self::userWithId(20 + $workGroup, self::tenantWithId(6), $workGroup);

            $this->assertTrue(PanelAccess::canUsePanel($sameTenant, $domain), "grupo $workGroup do mesmo tenant");
            $this->assertFalse(PanelAccess::canUsePanel($otherTenant, $domain), "grupo $workGroup de outro tenant");
        }
    }

    public function testComparesTenantsById(): void
    {
        // Instâncias diferentes do mesmo tenant (ex.: uma vinda da sessão, outra do banco) valem igual
        $this->assertTrue(PanelAccess::canUsePanel(self::userWithId(7, self::tenantWithId(3), 1), self::tenantWithId(3)));
    }

    public function testUserWithoutTenantOutsideGroupZeroNeverUsesThePanel(): void
    {
        $this->assertFalse(PanelAccess::canUsePanel(self::userWithId(8, null, 1), self::tenantWithId(3)));
        $this->assertFalse(PanelAccess::canUsePanel(self::userWithId(9, null, 2), self::tenantWithId(3)));
    }
}
