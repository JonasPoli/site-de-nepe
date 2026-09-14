<?php

namespace App\Repository;

use App\Entity\Enum\ArticleStatus;
use App\Entity\VideoSupport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VideoSupport> */
class VideoSupportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, VideoSupport::class); }

    public function findLatest(): ?VideoSupport
    {
        return $this->publishedQueryBuilder()
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return VideoSupport[] */
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

    /** @return VideoSupport[] */
    public function findByCategory(\App\Entity\Category $category): array
    {
        return $this->publishedQueryBuilder()
            ->andWhere('v.category = :category')
            ->setParameter('category', $category)
            ->getQuery()->getResult();
    }

    /** Only videos that went through the approval workflow are public */
    private function publishedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('v')
            ->where('v.status = :published')
            ->setParameter('published', ArticleStatus::Published)
            ->orderBy('v.createdAt', 'DESC');
    }
}
