<?php

namespace App\Repository;

use App\Entity\StudyMaterial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StudyMaterial> */
class StudyMaterialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, StudyMaterial::class); }
}
