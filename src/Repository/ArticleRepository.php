<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Article> */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Article::class); }

    /** @return Article[] */
    public function findPublished(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findPublishedQuery(): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery();
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        return $this->findOneBy(['slug' => $slug, 'status' => ArticleStatus::Published]);
    }
}
