<?php

namespace App\Security;

use App\Entity\Tenant;
use App\Entity\User;

/**
 * Regras dos usuários criados e excluídos pelo painel (/admin/editors).
 * Administradores (grupo 0) e super admins só são cadastrados pelo /superadmin.
 */
final class PanelUserPolicy
{
    /** Editor (1) e Revisor (2) */
    public const WORK_GROUPS = [1, 2];

    public static function acceptsWorkGroup(int $workGroup): bool
    {
        return in_array($workGroup, self::WORK_GROUPS, true);
    }

    /**
     * Exclusão só de editor ou revisor do tenant do domínio. Nunca alcança um administrador,
     * um super admin ou o próprio usuário.
     */
    public static function canDelete(User $actor, User $target, Tenant $domainTenant): bool
    {
        if (!self::acceptsWorkGroup($target->getWorkGroup())) {
            return false;
        }

        if ($actor === $target || ($actor->getId() !== null && $actor->getId() === $target->getId())) {
            return false;
        }

        $targetTenantId = $target->getTenant()?->getId();

        return $targetTenantId !== null && $targetTenantId === $domainTenant->getId();
    }
}
