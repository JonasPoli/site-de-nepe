<?php

namespace App\Repository;

use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function findByDomain(string $domain): ?Tenant
    {
        return $this->findOneBy(['domain' => $domain]);
    }

    /**
     * NEPEs em ordem alfabética, cada um com quantos usuários tem, numa consulta só.
     *
     * A busca compara sem diferenciar maiúsculas nem acentos, porque a collation
     * do banco é utf8mb4_unicode_ci: "alcione" encontra "NEPE Alcíone".
     *
     * @return list<array{tenant: Tenant, users: int}>
     */
    public function findAllWithUserCount(?string $busca = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t AS tenant', 'COUNT(u.id) AS users')
            ->leftJoin(User::class, 'u', Join::WITH, 'u.tenant = t')
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC');

        if ($busca !== null && $busca !== '') {
            $qb->andWhere('t.name LIKE :busca OR t.domain LIKE :busca')
                ->setParameter('busca', '%' . $busca . '%');
        }

        return array_map(
            static fn (array $linha): array => ['tenant' => $linha['tenant'], 'users' => (int) $linha['users']],
            $qb->getQuery()->getResult(),
        );
    }
}
