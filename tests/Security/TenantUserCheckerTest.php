<?php

namespace App\Tests\Security;

use App\Entity\Tenant;
use App\Security\TenantUserChecker;
use App\Service\TenantContext;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class TenantUserCheckerTest extends TestCase
{
    use EntityIds;

    private Tenant $renovando;
    private Tenant $alcione;

    protected function setUp(): void
    {
        $this->renovando = self::tenantWithId(3, 'renovandoconsciencias.nepebrasil.org');
        $this->alcione = self::tenantWithId(6, 'nepealcione.nepebrasil.org');
    }

    public function testRefusesUserOfAnotherTenantWithTheAddressOfHisSite(): void
    {
        $admin = self::userWithId(10, $this->renovando, 0);

        try {
            $this->checker($this->alcione)->checkPostAuth($admin);
            $this->fail('O login no domínio de outro NEPE deveria ser recusado.');
        } catch (CustomUserMessageAccountStatusException $e) {
            $this->assertSame(
                'Este usuário é de outro NEPE. Entre pelo endereço do seu site: https://renovandoconsciencias.nepebrasil.org/login',
                $e->getMessageKey(),
            );
        }
    }

    public function testRefusesUserWithoutTenantOutsideGroupZero(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('não está ligado a nenhum NEPE');

        $this->checker($this->alcione)->checkPostAuth(self::userWithId(11, null, 1));
    }

    public function testAcceptsUserOfTheDomainTenant(): void
    {
        $this->checker($this->alcione)->checkPostAuth(self::userWithId(12, self::tenantWithId(6, 'nepealcione.nepebrasil.org'), 1));

        $this->addToAssertionCount(1);
    }

    public function testAcceptsSuperAdminOnAnyDomain(): void
    {
        $superAdmin = self::userWithId(1, null, 0);

        $this->checker($this->alcione)->checkPostAuth($superAdmin);
        $this->checker($this->renovando)->checkPostAuth($superAdmin);

        $this->addToAssertionCount(2);
    }

    public function testPreAuthDoesNothingSoAWrongPasswordKeepsTheDefaultMessage(): void
    {
        $this->checker($this->alcione)->checkPreAuth(self::userWithId(10, $this->renovando, 0));

        $this->addToAssertionCount(1);
    }

    private function checker(Tenant $domainTenant): TenantUserChecker
    {
        $context = new TenantContext();
        $context->setTenant($domainTenant);

        return new TenantUserChecker($context);
    }
}
