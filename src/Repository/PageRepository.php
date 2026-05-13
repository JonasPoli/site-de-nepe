<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Page> */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Page::class); }

    /** @return Page[] */
    public function findForHeader(): array { return $this->findBy(['showInHeader' => true]); }

    /** @return Page[] */
    public function findForFooter(): array { return $this->findBy(['showInFooter' => true]); }
}
