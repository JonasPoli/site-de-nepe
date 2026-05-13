<?php

namespace App\Repository;

use App\Entity\TipoInscricao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TipoInscricao>
 * @method TipoInscricao|null find($id, $lockMode = null, $lockVersion = null)
 * @method TipoInscricao|null findOneBy(array $criteria, array $orderBy = null)
 * @method TipoInscricao[]    findAll()
 * @method TipoInscricao[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TipoInscricaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipoInscricao::class);
    }
}
