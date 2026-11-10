<?php

namespace App\Repository;

use App\Entity\PageSection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PageSection> */
class PageSectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, PageSection::class); }

    /**
     * Seção pelo id, junto com a página ou a categoria dona. A seção não tem tenant: com o
     * tenant_filter ligado, o dono de outro tenant fica fora da junção (o filtro entra no ON do
     * LEFT JOIN) e a seção some do resultado. Quem chama confere de novo com TenantOwnership.
     */
    public function findOneWithOwner(int $id): ?PageSection
    {
        return $this->createQueryBuilder('s')
            ->addSelect('p', 'c')
            ->leftJoin('s.page', 'p')
            ->leftJoin('s.category', 'c')
            ->where('s.id = :id')
            ->andWhere('p.id IS NOT NULL OR c.id IS NOT NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
