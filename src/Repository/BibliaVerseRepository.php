<?php

namespace App\Repository;

use App\Entity\BibliaBook;
use App\Entity\BibliaVerse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BibliaVerse>
 */
class BibliaVerseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BibliaVerse::class);
    }

    /**
     * @return BibliaVerse[]
     */
    public function findPassage(BibliaBook|int $book, int $chapter, ?int $verseStart = null, ?int $verseEnd = null, int $versionId = 2): array
    {
        $qb = $this->createQueryBuilder('v')
            ->join('v.book', 'b')
            ->addSelect('b')
            ->where('v.version = :version')
            ->andWhere('v.chapter = :chapter')
            ->setParameter('version', $versionId)
            ->setParameter('chapter', $chapter)
            ->orderBy('v.verse', 'ASC');

        if ($book instanceof BibliaBook) {
            $qb->andWhere('v.book = :book')->setParameter('book', $book);
        } else {
            $qb->andWhere('v.book = :bookId')->setParameter('bookId', $book);
        }

        if ($verseStart !== null && $verseEnd !== null) {
            $qb->andWhere('v.verse >= :vStart AND v.verse <= :vEnd')
               ->setParameter('vStart', min($verseStart, $verseEnd))
               ->setParameter('vEnd', max($verseStart, $verseEnd));
        } elseif ($verseStart !== null) {
            $qb->andWhere('v.verse = :vStart')->setParameter('vStart', $verseStart);
        }

        return $qb->getQuery()->getResult();
    }
}
