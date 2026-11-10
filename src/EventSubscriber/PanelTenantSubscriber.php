<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\PanelAccess;
use App\Service\TenantContext;
use App\Service\TenantUrl;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Painel só para quem é do NEPE do domínio (ou super admin), em cada requisição de /admin.
 *
 * O login já confere o domínio (TenantUserChecker); este ponto pega as sessões abertas antes
 * dessa regra e a troca de tenant feita pelo super admin com o usuário logado. Quando a regra
 * falha, a sessão deste domínio é encerrada e a resposta é 403 com o link do painel certo
 * (um redirecionamento cairia no login do outro domínio sem explicar o motivo).
 */
class PanelTenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TenantContext $tenantContext,
        private readonly Environment $twig,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        // Logo depois do firewall (prioridade 8), que carrega o usuário da sessão
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }

        // Sem tenant, o TenantSubscriber já respondeu (domínio não configurado)
        $domainTenant = $this->tenantContext->getTenant();
        if ($domainTenant === null) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || PanelAccess::canUsePanel($user, $domainTenant)) {
            return;
        }

        $this->tokenStorage->setToken(null);
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $userTenant = $user->getTenant();
        $event->setResponse(new Response(
            $this->twig->render('admin/acesso_outro_nepe.html.twig', [
                'domain_tenant' => $domainTenant,
                'user_tenant'   => $userTenant,
                'panel_url'     => $userTenant !== null ? TenantUrl::base($userTenant) . '/admin' : null,
            ]),
            Response::HTTP_FORBIDDEN,
        ));
    }
}
