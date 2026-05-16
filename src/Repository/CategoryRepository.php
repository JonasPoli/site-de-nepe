<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Category> */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Category::class); }

    /**
     * Returns only root categories (no parent), ordered by name.
     *
     * @return Category[]
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Alias for use in dropdowns where only root categories can be selected as parent.
     *
     * @return Category[]
     */
    public function findOnlyRoots(): array
    {
        return $this->findRootCategories();
    }

    /**
     * Root categories marked to appear in the header nav.
     * Includes their children (sub-categories) for dropdown rendering.
     *
     * @return Category[]
     */
    public function findForHeader(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.showInHeader = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Root categories marked to appear in the footer nav.
     *
     * @return Category[]
     */
    public function findForFooter(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.showInFooter = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
