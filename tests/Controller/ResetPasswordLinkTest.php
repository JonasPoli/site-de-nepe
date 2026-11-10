<?php

namespace App\Tests\Controller;

use App\Controller\ResetPasswordController;
use App\Entity\Tenant;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ResetPasswordLinkTest extends TestCase
{
    public function testLinkGoesToTheDomainOfTheUserNepeEvenWhenRequestedElsewhere(): void
    {
        $editor = (new User())->setWorkGroup(1)->setTenant((new Tenant())->setDomain('nepealcione.nepebrasil.org'));

        $this->assertSame(
            'https://nepealcione.nepebrasil.org',
            ResetPasswordController::resetLinkBaseUrl($editor, 'https://ueebp.nepebrasil.org'),
        );
    }

    public function testLocalTenantUsesHttp(): void
    {
        $editor = (new User())->setWorkGroup(1)->setTenant((new Tenant())->setDomain('127.0.0.1'));

        $this->assertSame('http://127.0.0.1', ResetPasswordController::resetLinkBaseUrl($editor, 'http://localhost:8000'));
    }

    public function testSuperAdminKeepsTheDomainOfTheRequest(): void
    {
        $this->assertSame(
            'https://ciscodedeus.nepebrasil.org',
            ResetPasswordController::resetLinkBaseUrl(new User(), 'https://ciscodedeus.nepebrasil.org'),
        );
    }
}
