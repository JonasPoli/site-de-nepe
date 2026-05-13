<?php

namespace App\Repository;

use App\Entity\ItemAdicional;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemAdicional>
 * @method ItemAdicional|null find($id, $lockMode = null, $lockVersion = null)
 * @method ItemAdicional|null findOneBy(array $criteria, array $orderBy = null)
 * @method ItemAdicional[]    findAll()
 * @method ItemAdicional[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemAdicionalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemAdicional::class);
    }
}
