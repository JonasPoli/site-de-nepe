<?php

namespace App\Repository;

use App\Entity\BibliaBook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BibliaBook>
 */
class BibliaBookRepository extends ServiceEntityRepository
{
    /** @var array<int, BibliaBook>|null */
    private ?array $cachedAllBooks = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BibliaBook::class);
    }

    /**
     * @return BibliaBook[]
     */
    public function findAllOrdered(): array
    {
        if ($this->cachedAllBooks !== null) {
            return $this->cachedAllBooks;
        }

        $this->cachedAllBooks = $this->createQueryBuilder('b')
            ->leftJoin('b.testment', 't')
            ->addSelect('t')
            ->orderBy('b.position', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->cachedAllBooks;
    }

    public function findBySlugOrAbbrevOrId(string|int $identifier): ?BibliaBook
    {
        if (is_numeric($identifier)) {
            return $this->find((int) $identifier);
        }

        $term = trim((string) $identifier);
        if ($term === '') {
            return null;
        }

        $allBooks = $this->findAllOrdered();
        $termLower = mb_strtolower($term, 'UTF-8');
        $termUnaccented = $this->removeAccents($termLower);

        // 1. Exact match on abbreviation (e.g. 'jo', 'jó', 'gn', 'mt')
        foreach ($allBooks as $book) {
            if (mb_strtolower($book->getAbbreviation(), 'UTF-8') === $termLower) {
                return $book;
            }
        }

        // 2. Exact match on name (e.g. 'João', 'Jó', 'Gênesis')
        foreach ($allBooks as $book) {
            if (mb_strtolower($book->getName(), 'UTF-8') === $termLower) {
                return $book;
            }
        }

        // 3. Exact match on bible_com_abreviation (e.g. 'john', 'job', 'genesis')
        foreach ($allBooks as $book) {
            if ($book->getBibleComAbreviation() && mb_strtolower($book->getBibleComAbreviation(), 'UTF-8') === $termLower) {
                return $book;
            }
        }

        // 4. Unaccented match on name (e.g. 'joao' -> 'João', 'genesis' -> 'Gênesis', 'exodo' -> 'Êxodo')
        foreach ($allBooks as $book) {
            if ($this->removeAccents(mb_strtolower($book->getName(), 'UTF-8')) === $termUnaccented) {
                return $book;
            }
        }

        // 5. Unaccented match on abbreviation (e.g. 'jo' without accent -> 'jo' / João)
        foreach ($allBooks as $book) {
            if ($this->removeAccents(mb_strtolower($book->getAbbreviation(), 'UTF-8')) === $termUnaccented) {
                return $book;
            }
        }

        return null;
    }

    private function removeAccents(string $str): string
    {
        $unwanted = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
            'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a',
            'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i',
            'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u',
            'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];

        return strtr($str, $unwanted);
    }
}
