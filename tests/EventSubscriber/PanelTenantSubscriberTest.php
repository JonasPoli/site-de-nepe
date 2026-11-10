<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Tenant;
use App\Entity\User;
use App\EventSubscriber\PanelTenantSubscriber;
use App\Service\TenantContext;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class PanelTenantSubscriberTest extends TestCase
{
    use EntityIds;

    private Tenant $ueebp;
    private Tenant $alcione;
    private TokenStorage $tokens;
    private Session $session;

    protected function setUp(): void
    {
        $this->ueebp = self::tenantWithId(7, 'ueebp.nepebrasil.org');
        $this->alcione = self::tenantWithId(6, 'nepealcione.nepebrasil.org');
        $this->tokens = new TokenStorage();
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testRunsRightAfterTheFirewall(): void
    {
        $this->assertSame(['onKernelRequest', 7], PanelTenantSubscriber::getSubscribedEvents()[KernelEvents::REQUEST]);
    }

    public function testUserOfAnotherTenantGets403WithTheLinkOfHisPanelAndLosesTheSession(): void
    {
        $editor = self::userWithId(20, $this->alcione, 1);
        $this->login($editor);
        $this->session->set('_security_main', 'token serializado');
        $oldSessionId = $this->session->getId();

        $event = $this->handle('https://ueebp.nepebrasil.org/admin', $this->ueebp);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('painel: https://nepealcione.nepebrasil.org/admin', $response->getContent());
        $this->assertNull($this->tokens->getToken(), 'o token é limpo');
        $this->assertFalse($this->session->has('_security_main'), 'a sessão é invalidada');
        $this->assertNotSame($oldSessionId, $this->session->getId());
    }

    public function testUserWithoutTenantOutsideGroupZeroGets403WithoutLink(): void
    {
        $this->login(self::userWithId(21, null, 2));

        $event = $this->handle('https://ueebp.nepebrasil.org/admin/video', $this->ueebp);

        $this->assertSame(403, $event->getResponse()->getStatusCode());
        $this->assertSame('painel: ', $event->getResponse()->getContent());
        $this->assertNull($this->tokens->getToken());
    }

    public function testUserOfTheDomainTenantPasses(): void
    {
        $this->login(self::userWithId(22, self::tenantWithId(7, 'ueebp.nepebrasil.org'), 1));

        $event = $this->handle('https://ueebp.nepebrasil.org/admin/video', $this->ueebp);

        $this->assertNull($event->getResponse());
        $this->assertNotNull($this->tokens->getToken());
    }

    public function testSuperAdminPassesOnAnyDomain(): void
    {
        $this->login(self::userWithId(1, null, 0));

        $this->assertNull($this->handle('https://ueebp.nepebrasil.org/admin', $this->ueebp)->getResponse());
        $this->assertNull($this->handle('https://nepealcione.nepebrasil.org/admin', $this->alcione)->getResponse());
        $this->assertNotNull($this->tokens->getToken());
    }

    public function testIgnoresVisitorWithoutLogin(): void
    {
        $this->assertNull($this->handle('https://ueebp.nepebrasil.org/admin', $this->ueebp)->getResponse());
    }

    public function testIgnoresPathsOutsideTheAdmin(): void
    {
        $this->login(self::userWithId(20, $this->alcione, 1));

        $this->assertNull($this->handle('https://ueebp.nepebrasil.org/', $this->ueebp)->getResponse());
        $this->assertNull($this->handle('https://ueebp.nepebrasil.org/login', $this->ueebp)->getResponse());
        $this->assertNotNull($this->tokens->getToken());
    }

    private function login(User $user): void
    {
        $this->tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function handle(string $url, Tenant $domainTenant): RequestEvent
    {
        $request = Request::create($url);
        $request->setSession($this->session);

        $context = new TenantContext();
        $context->setTenant($domainTenant);

        $twig = new Environment(new ArrayLoader(['admin/acesso_outro_nepe.html.twig' => 'painel: {{ panel_url }}']));

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        (new PanelTenantSubscriber($this->tokens, $context, $twig))->onKernelRequest($event);

        return $event;
    }
}
