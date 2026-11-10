<?php

namespace App\Tests\Support;

use App\Entity\Tenant;
use App\Entity\User;

/**
 * Entidades em memória com id, como se viessem do banco (os testes não têm banco).
 */
trait EntityIds
{
    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private static function withId(object $entity, int $id): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);

        return $entity;
    }

    private static function tenantWithId(int $id, string $domain = ''): Tenant
    {
        return self::withId((new Tenant())->setDomain($domain ?: 'nepe' . $id . '.nepebrasil.org'), $id);
    }

    /** Usuário do grupo informado; sem tenant e no grupo 0, é super admin */
    private static function userWithId(int $id, ?Tenant $tenant, int $workGroup = 1): User
    {
        return self::withId((new User())->setUsername('usuario' . $id)->setTenant($tenant)->setWorkGroup($workGroup), $id);
    }
}
