<?php

namespace App\Repository;

use App\Entity\HeroBanner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HeroBanner> */
class HeroBannerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, HeroBanner::class); }

    public function findActiveOne(): ?HeroBanner
    {
        return $this->findOneBy(['active' => true]);
    }

    /** @return HeroBanner[] */
    public function findActiveAll(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
