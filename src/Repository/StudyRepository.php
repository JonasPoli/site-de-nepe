<?php

namespace App\Repository;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Study;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Study> */
class StudyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Study::class); }

    public function findLatest(): ?Study
    {
        return $this->publishedQueryBuilder()
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return Study[] */
    public function findGallery(int $skip = 1, int $limit = 12): array
    {
        return $this->publishedQueryBuilder()
            ->setFirstResult($skip)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findAllQuery(): \Doctrine\ORM\Query
    {
        return $this->publishedQueryBuilder()->getQuery();
    }

    /** @return Study[] */
    public function findByCategory(\App\Entity\Category $category): array
    {
        return $this->publishedQueryBuilder()
            ->andWhere('s.category = :category')
            ->setParameter('category', $category)
            ->getQuery()->getResult();
    }

    /** Only studies that went through the approval workflow are public */
    private function publishedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :published')
            ->setParameter('published', ArticleStatus::Published)
            ->orderBy('s.createdAt', 'DESC');
    }
}
