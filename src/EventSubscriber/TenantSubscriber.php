<?php

namespace App\EventSubscriber;

use App\Repository\TenantRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs on every request (priority 100 — before controllers).
 * Resolves the Tenant from the HTTP host, injects it into TenantContext
 * and enables the Doctrine TenantFilter.
 */
class TenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip Symfony profiler / debug routes and API routes
        if (str_starts_with($request->getPathInfo(), '/_') || str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $host = $request->getHost();
        $tenant = $this->tenantRepository->findByDomain($host);

        if ($tenant === null) {
            // "www.site.org" and "site.org" belong to the same tenant, whichever of them is registered
            $otherHost = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
            $tenant = $this->tenantRepository->findByDomain($otherHost);

            // Pages move to the registered host; forms posted to the other one are still served.
            // 302 instead of 301: browsers cache a 301 and the superadmin may still change the domain.
            if ($tenant !== null && $request->isMethodSafe()) {
                $event->setResponse(new RedirectResponse($this->urlWithHost($request, $otherHost)));
                return;
            }
        }

        if ($tenant === null) {
            // No tenant for this domain — return a friendly 404
            $event->setResponse(new Response(
                sprintf('<h1>Domínio não configurado</h1><p>O domínio <strong>%s</strong> não está registrado nesta plataforma.</p>', htmlspecialchars($host)),
                Response::HTTP_NOT_FOUND,
            ));
            return;
        }

        // Store tenant for use in controllers/templates
        $this->tenantContext->setTenant($tenant);

        // Admin routes bypass tenant isolation — admins manage entities across tenants
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        // Enable the Doctrine filter and parameterize it
        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenant_id', $tenant->getId(), 'integer');
    }

    /** Same URL (scheme, port, path and query) on another host */
    private function urlWithHost(Request $request, string $host): string
    {
        $scheme = $request->getScheme();
        $port = (int) $request->getPort();
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . ($port !== 0 && $port !== $defaultPort ? ':' . $port : '') . $request->getRequestUri();
    }
}
