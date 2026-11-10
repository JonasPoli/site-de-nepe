<?php

namespace App\Tests\Controller\superadmin;

use App\Controller\superadmin\SuperAdminController;
use App\Entity\Tenant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ImpersonationUrlTest extends TestCase
{
    public function testGoesToTheDomainOfTheUserNepe(): void
    {
        $admin = (new User())->setUsername('admin.alcione')->setTenant((new Tenant())->setDomain('nepealcione.nepebrasil.org'));

        $this->assertSame(
            'https://nepealcione.nepebrasil.org/admin?_switch_user=admin.alcione',
            SuperAdminController::impersonationUrl($admin),
        );
    }

    public function testLocalTenantUsesHttp(): void
    {
        $editor = (new User())->setUsername('editor')->setWorkGroup(1)->setTenant((new Tenant())->setDomain('localhost'));

        $this->assertSame('http://localhost/admin?_switch_user=editor', SuperAdminController::impersonationUrl($editor));
    }

    public function testUserWithoutTenantStaysOnTheCurrentDomain(): void
    {
        $this->assertSame('/admin?_switch_user=outro.super', SuperAdminController::impersonationUrl((new User())->setUsername('outro.super')));
    }

    public function testEncodesTheUsername(): void
    {
        $user = (new User())->setUsername('maria@nepe.org&x=1')->setTenant((new Tenant())->setDomain('ueebp.nepebrasil.org'));

        $this->assertSame('https://ueebp.nepebrasil.org/admin?_switch_user=maria%40nepe.org%26x%3D1', SuperAdminController::impersonationUrl($user));
    }
}
