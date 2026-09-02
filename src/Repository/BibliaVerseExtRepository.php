<?php

namespace App\Repository;

use App\Entity\BibliaVerseExt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BibliaVerseExt>
 */
class BibliaVerseExtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BibliaVerseExt::class);
    }
}
