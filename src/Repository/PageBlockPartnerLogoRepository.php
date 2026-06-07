<?php

namespace App\Repository;

use App\Entity\PageBlockPartnerLogo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageBlockPartnerLogo>
 */
class PageBlockPartnerLogoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageBlockPartnerLogo::class);
    }
}
