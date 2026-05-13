<?php

namespace App\Repository;

use App\Entity\Inscrito;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscrito>
 * @method Inscrito|null find($id, $lockMode = null, $lockVersion = null)
 * @method Inscrito|null findOneBy(array $criteria, array $orderBy = null)
 * @method Inscrito[]    findAll()
 * @method Inscrito[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InscritoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscrito::class);
    }
}
