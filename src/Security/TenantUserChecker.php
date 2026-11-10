<?php

namespace App\Security;

use App\Entity\User;
use App\Service\TenantContext;
use App\Service\TenantUrl;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Login só no domínio do próprio NEPE (firewalls.main.user_checker).
 *
 * Roda depois da senha conferida (checkPostAuth): senha errada continua com a mensagem padrão,
 * sem revelar o NEPE do usuário. O Symfony também chama checkPostAuth() no usuário de destino
 * do _switch_user, então um "entrar como" no domínio errado dá 403 sem derrubar a sessão do super admin.
 */
class TenantUserChecker implements UserCheckerInterface
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    #[\Override]
    public function checkPreAuth(UserInterface $user): void {}

    #[\Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User || $user->isSuperAdmin()) {
            return;
        }

        $domainTenant = $this->tenantContext->getTenant();
        if ($domainTenant !== null && PanelAccess::canUsePanel($user, $domainTenant)) {
            return;
        }

        $userTenant = $user->getTenant();
        if ($userTenant === null) {
            throw new CustomUserMessageAccountStatusException('Este usuário não está ligado a nenhum NEPE. Fale com o administrador do site.');
        }

        throw new CustomUserMessageAccountStatusException(sprintf(
            'Este usuário é de outro NEPE. Entre pelo endereço do seu site: %s/login',
            TenantUrl::base($userTenant),
        ));
    }
}
