<?php

namespace App\Security;

use App\Entity\PageBlock;
use App\Entity\PageSection;
use App\Entity\Tenant;

/**
 * Seções e blocos não têm tenant próprio: pertencem ao tenant da página ou da categoria dona.
 * Usada no painel depois da busca com o tenant_filter (PageSectionRepository/PageBlockRepository::findOneWithOwner).
 */
final class TenantOwnership
{
    /** Id do tenant dono; null quando não há dono ou quando página e categoria discordam */
    public static function tenantIdOf(PageSection|PageBlock $item): ?int
    {
        $section = $item instanceof PageBlock ? $item->getSection() : $item;
        if ($section === null) {
            return null;
        }

        $owners = array_filter([$section->getPage(), $section->getCategory()]);
        $tenantIds = array_unique(array_map(static fn (object $owner): ?int => $owner->getTenant()?->getId(), $owners));

        return count($tenantIds) === 1 ? reset($tenantIds) : null;
    }

    public static function belongsTo(PageSection|PageBlock $item, Tenant $tenant): bool
    {
        $tenantId = self::tenantIdOf($item);

        return $tenantId !== null && $tenantId === $tenant->getId();
    }
}
