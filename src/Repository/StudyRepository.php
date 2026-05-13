<?php

namespace App\Repository;

use App\Entity\Study;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Study> */
class StudyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Study::class); }

    public function findLatest(): ?Study
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return Study[] */
    public function findGallery(int $skip = 1, int $limit = 12): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($skip)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findAllQuery(): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery();
    }
}
