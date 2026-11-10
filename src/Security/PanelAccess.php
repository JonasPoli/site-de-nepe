<?php

namespace App\Security;

use App\Entity\Tenant;
use App\Entity\User;

/**
 * Quem pode usar o painel (/admin) do domínio acessado.
 * Aplicada no login (TenantUserChecker) e em cada requisição de /admin (PanelTenantSubscriber).
 */
final class PanelAccess
{
    /**
     * Super admin entra pelo domínio de qualquer NEPE. Os demais só pelo domínio do próprio NEPE:
     * o tenant do usuário precisa ter o mesmo id do tenant do domínio. Usuário sem tenant fora do
     * grupo 0 nunca entra.
     */
    public static function canUsePanel(User $user, Tenant $domainTenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $userTenantId = $user->getTenant()?->getId();

        return $userTenantId !== null && $userTenantId === $domainTenant->getId();
    }
}
