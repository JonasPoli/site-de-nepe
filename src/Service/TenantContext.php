<?php

namespace App\Service;

use App\Entity\Tenant;

/**
 * Shared service that holds the current tenant for the duration of a request.
 * Injected by TenantSubscriber early in the kernel.request lifecycle.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Throws if no tenant is loaded — useful inside controllers where
     * a tenant is always expected.
     */
    public function requireTenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new \LogicException('No tenant loaded for this request.');
        }
        return $this->tenant;
    }
}
