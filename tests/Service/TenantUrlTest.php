<?php

namespace App\Tests\Service;

use App\Entity\Tenant;
use App\Service\TenantUrl;
use PHPUnit\Framework\TestCase;

class TenantUrlTest extends TestCase
{
    public function testPublicDomainUsesHttps(): void
    {
        $this->assertSame('https://nepealcione.nepebrasil.org', TenantUrl::base((new Tenant())->setDomain('nepealcione.nepebrasil.org')));
    }

    public function testLocalhostUsesHttp(): void
    {
        $this->assertSame('http://localhost', TenantUrl::base((new Tenant())->setDomain('localhost')));
    }

    public function testLoopbackIpUsesHttp(): void
    {
        $this->assertSame('http://127.0.0.1', TenantUrl::base((new Tenant())->setDomain('127.0.0.1')));
    }
}
