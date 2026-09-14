<?php

namespace App\Tests\EventSubscriber;

use App\Doctrine\TenantFilter;
use App\Entity\Tenant;
use App\EventSubscriber\TenantSubscriber;
use App\Repository\TenantRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TenantSubscriberTest extends TestCase
{
    private TenantContext $context;

    public function testRegisteredDomainLoadsTenant(): void
    {
        $tenant = $this->tenant('nepe.org.br');
        $event = $this->handle(Request::create('https://nepe.org.br/sobre'), [$tenant]);

        $this->assertNull($event->getResponse());
        $this->assertSame($tenant, $this->context->getTenant());
    }

    public function testWwwRedirectsToRegisteredDomainKeepingPathAndQuery(): void
    {
        $event = $this->handle(Request::create('https://www.nepe.org.br/artigos?page=2'), [$this->tenant('nepe.org.br')]);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://nepe.org.br/artigos?page=2', $response->getTargetUrl());
        $this->assertNull($this->context->getTenant());
    }

    public function testDomainWithoutWwwRedirectsWhenWwwIsRegisteredKeepingPort(): void
    {
        $event = $this->handle(Request::create('http://nepe.org.br:8000/'), [$this->tenant('www.nepe.org.br')]);

        $this->assertSame('http://www.nepe.org.br:8000/', $event->getResponse()->getTargetUrl());
    }

    public function testPostOnTheOtherHostIsServedWithoutRedirect(): void
    {
        $tenant = $this->tenant('nepe.org.br');
        $event = $this->handle(Request::create('https://www.nepe.org.br/contato', 'POST'), [$tenant]);

        $this->assertNull($event->getResponse());
        $this->assertSame($tenant, $this->context->getTenant());
    }

    public function testUnknownDomainReturns404(): void
    {
        $event = $this->handle(Request::create('https://outro.org.br/'), [$this->tenant('nepe.org.br')]);

        $this->assertSame(404, $event->getResponse()->getStatusCode());
        $this->assertNull($this->context->getTenant());
    }

    /** @param list<Tenant> $tenants */
    private function handle(Request $request, array $tenants): RequestEvent
    {
        $byDomain = [];
        foreach ($tenants as $tenant) {
            $byDomain[$tenant->getDomain()] = $tenant;
        }

        $repository = $this->createMock(TenantRepository::class);
        $repository->method('findByDomain')->willReturnCallback(static fn (string $domain): ?Tenant => $byDomain[$domain] ?? null);

        $em = $this->createMock(EntityManagerInterface::class);
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('enable')->willReturn(new TenantFilter($em));
        $em->method('getFilters')->willReturn($filters);

        $this->context = new TenantContext();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        (new TenantSubscriber($repository, $this->context, $em))->onKernelRequest($event);

        return $event;
    }

    private function tenant(string $domain): Tenant
    {
        return (new Tenant())->setDomain($domain);
    }
}
