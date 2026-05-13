<?php

namespace App\Doctrine;

use App\Contract\TenantAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter — automatically appends `tenant_id = X` to every
 * SELECT/UPDATE/DELETE for entities implementing TenantAwareInterface.
 *
 * Enabled (and parameterized) by TenantSubscriber on every request.
 */
class TenantFilter extends SQLFilter
{
    #[\Override]
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Only apply to entities that are tenant-aware
        if (!$targetEntity->reflClass->implementsInterface(TenantAwareInterface::class)) {
            return '';
        }

        // tenant_id column must exist on the entity table
        if (!$targetEntity->hasField('tenant') && !$targetEntity->hasAssociation('tenant')) {
            return '';
        }

        return sprintf('%s.tenant_id = %s', $targetTableAlias, $this->getParameter('tenant_id'));
    }
}
