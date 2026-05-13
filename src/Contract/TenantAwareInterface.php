<?php

namespace App\Contract;

use App\Entity\Tenant;

interface TenantAwareInterface
{
    public function getTenant(): ?Tenant;

    public function setTenant(?Tenant $tenant): static;
}
