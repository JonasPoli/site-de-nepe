<?php

namespace App\Repository;

use App\Entity\PageBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PageBlock> */
class PageBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, PageBlock::class); }

    /**
     * Bloco pelo id, junto com a seção e a página ou a categoria dona da seção. Bloco e seção não
     * têm tenant: com o tenant_filter ligado, o dono de outro tenant fica fora da junção (o filtro
     * entra no ON do LEFT JOIN) e o bloco some do resultado. Quem chama confere de novo com TenantOwnership.
     */
    public function findOneWithOwner(int $id): ?PageBlock
    {
        return $this->createQueryBuilder('b')
            ->addSelect('s', 'p', 'c')
            ->innerJoin('b.section', 's')
            ->leftJoin('s.page', 'p')
            ->leftJoin('s.category', 'c')
            ->where('b.id = :id')
            ->andWhere('p.id IS NOT NULL OR c.id IS NOT NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
