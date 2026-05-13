<?php

namespace App\Repository;

use App\Entity\VideoSupport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VideoSupport> */
class VideoSupportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, VideoSupport::class); }

    public function findLatest(): ?VideoSupport
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return VideoSupport[] */
    public function findGallery(int $skip = 1, int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.createdAt', 'DESC')
            ->setFirstResult($skip)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findAllQuery(): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery();
    }
}
