<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\BibliaBook;
use App\Entity\BibliaVerse;
use App\Entity\Enum\ArticleStatus;
use App\Entity\Page;
use App\Entity\Study;
use App\Entity\Tenant;
use App\Entity\VideoSupport;
use App\Repository\ArticleRepository;
use App\Repository\BibliaBookRepository;
use App\Repository\BibliaTestamentRepository;
use App\Repository\BibliaVerseRepository;
use App\Repository\BibliaVersionRepository;
use App\Repository\PageRepository;
use App\Repository\StudyRepository;
use App\Repository\VideoSupportRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BibliaService
{
    private ?array $cachedStructure = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly BibliaTestamentRepository $testamentRepo,
        private readonly BibliaBookRepository $bookRepo,
        private readonly BibliaVerseRepository $verseRepo,
        private readonly BibliaVersionRepository $versionRepo,
        private readonly ArticleRepository $articleRepo,
        private readonly VideoSupportRepository $videoRepo,
        private readonly StudyRepository $studyRepo,
        private readonly PageRepository $pageRepo,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Retorna a hierarquia estrutural completa:
     * Testamentos -> Livros -> Capítulos -> Total de Versículos
     *
     * @return array{testaments: array<int, array{id: int, name: string}>, books: array<int, array{id: int, testament_id: int, position: int, name: string, abbrev: string, chapters: array<int, int>}>}
     */
    public function getStructure(): array
    {
        if ($this->cachedStructure !== null) {
            return $this->cachedStructure;
        }

        $testaments = $this->connection->fetchAllAssociative('SELECT id, name FROM biblia_testament ORDER BY id ASC');
        $books = $this->connection->fetchAllAssociative('SELECT id, testment_id, position, name, abbreviation, bible_com_abreviation FROM biblia_book ORDER BY position ASC, id ASC');

        // Buscar max(verse) por livro e capítulo na versão ARC (2)
        $verseStats = $this->connection->fetchAllAssociative(
            'SELECT book_id, chapter, MAX(verse) AS total_verses FROM biblia_verse WHERE version_id = 2 GROUP BY book_id, chapter ORDER BY book_id ASC, chapter ASC'
        );

        $chaptersByBook = [];
        foreach ($verseStats as $row) {
            $bId = (int) $row['book_id'];
            $ch = (int) $row['chapter'];
            $tot = (int) $row['total_verses'];
            $chaptersByBook[$bId][$ch] = $tot;
        }

        $formattedBooks = [];
        foreach ($books as $b) {
            $bId = (int) $b['id'];
            $formattedBooks[] = [
                'id' => $bId,
                'testament_id' => (int) $b['testment_id'],
                'position' => (int) $b['position'],
                'name' => (string) $b['name'],
                'abbrev' => (string) $b['abbreviation'],
                'bible_com' => $b['bible_com_abreviation'] ?? null,
                'chapters' => $chaptersByBook[$bId] ?? [],
            ];
        }

        $this->cachedStructure = [
            'testaments' => array_map(fn($t) => ['id' => (int) $t['id'], 'name' => (string) $t['name']], $testaments),
            'books' => $formattedBooks,
        ];

        return $this->cachedStructure;
    }

    /**
     * Recupera o trecho bíblico formatado (versículos ARC).
     *
     * @return array{book: array{id: int, name: string, abbrev: string}, chapter: int, verse_start: int, verse_end: int, reference_formatted: string, verses: array<int, array{id: int, verse: int, text: string, subject: ?string}>}|null
     */
    public function getPassage(int|string|BibliaBook $bookIdentifier, int $chapter, ?int $verseStart = null, ?int $verseEnd = null, int $versionId = 2): ?array
    {
        $book = $bookIdentifier instanceof BibliaBook ? $bookIdentifier : $this->bookRepo->findBySlugOrAbbrevOrId($bookIdentifier);
        if (!$book) {
            return null;
        }

        $vStart = $verseStart ?: 1;
        $vEnd = $verseEnd ?: $vStart;
        if ($vEnd < $vStart) {
            $tmp = $vStart;
            $vStart = $vEnd;
            $vEnd = $tmp;
        }

        $verses = $this->verseRepo->findPassage($book, $chapter, $vStart, $vEnd, $versionId);
        if (empty($verses)) {
            return null;
        }

        $versesArray = [];
        foreach ($verses as $v) {
            $versesArray[] = [
                'id' => $v->getId(),
                'verse' => $v->getVerse(),
                'text' => $v->getText(),
                'subject' => $v->getSubject(),
            ];
        }

        $refFormatted = ($vStart === $vEnd)
            ? sprintf('%s %d:%d', $book->getName(), $chapter, $vStart)
            : sprintf('%s %d:%d-%d', $book->getName(), $chapter, $vStart, $vEnd);

        return [
            'book' => [
                'id' => $book->getId(),
                'name' => $book->getName(),
                'abbrev' => $book->getAbbreviation(),
            ],
            'chapter' => $chapter,
            'verse_start' => $vStart,
            'verse_end' => $vEnd,
            'reference_formatted' => $refFormatted,
            'version' => [
                'id' => 2,
                'name' => 'Almeida Revista e Corrigida',
                'abbrev' => 'ARC',
            ],
            'verses' => $versesArray,
        ];
    }

    /**
     * Busca todos os conteúdos associados ao trecho pesquisado.
     * Se verseStart e verseEnd forem nulos, retorna todos os conteúdos do capítulo.
     * Se informados, utiliza regra de sobreposição de intervalos:
     * C_start <= Q_end AND C_end >= Q_start (onde se C_end for nulo, C_end = C_start).
     *
     * @param ?string $type Filtro opcional de tipo ('article', 'video', 'study'/'material', 'page')
     * @return array<int, array<string, mixed>>
     */
    public function findContentsByPericope(
        int|string|BibliaBook $bookIdentifier,
        int $chapter,
        ?int $verseStart = null,
        ?int $verseEnd = null,
        ?Tenant $tenant = null,
        ?string $type = null
    ): array {
        $book = $bookIdentifier instanceof BibliaBook ? $bookIdentifier : $this->bookRepo->findBySlugOrAbbrevOrId($bookIdentifier);
        if (!$book) {
            return [];
        }

        $typeNormalized = $type ? strtolower(trim($type)) : null;
        $searchAllVerses = ($verseStart === null && $verseEnd === null);

        $qStart = $verseStart;
        $qEnd = $verseEnd ?: $qStart;
        if ($qStart !== null && $qEnd !== null && $qEnd < $qStart) {
            $tmp = $qStart;
            $qStart = $qEnd;
            $qEnd = $tmp;
        }

        $results = [];

        // 1. Artigos Publicados
        if ($typeNormalized === null || in_array($typeNormalized, ['article', 'noticia', 'artigo', 'articles', 'noticias', 'artigos'], true)) {
            $artQb = $this->articleRepo->createQueryBuilder('a')
                ->leftJoin('a.tenant', 't')
                ->leftJoin('a.bibliaBook', 'b')
                ->addSelect('t', 'b')
                ->where('a.bibliaBook = :book')
                ->andWhere('a.bibliaChapter = :chap')
                ->andWhere('a.status = :published')
                ->setParameter('book', $book)
                ->setParameter('chap', $chapter)
                ->setParameter('published', ArticleStatus::Published);

            if (!$searchAllVerses) {
                $artQb->andWhere('a.bibliaVerseStart <= :qEnd')
                      ->andWhere('(a.bibliaVerseEnd >= :qStart OR (a.bibliaVerseEnd IS NULL AND a.bibliaVerseStart >= :qStart))')
                      ->setParameter('qStart', $qStart)
                      ->setParameter('qEnd', $qEnd);
            }

            if ($tenant) {
                $artQb->andWhere('a.tenant = :tenant')->setParameter('tenant', $tenant);
            }

            /** @var Article $article */
            foreach ($artQb->getQuery()->getResult() as $article) {
                $results[] = $this->formatArticleResult($article);
            }
        }

        // 2. Vídeos
        if ($typeNormalized === null || in_array($typeNormalized, ['video', 'videos', 'youtube'], true)) {
            $vidQb = $this->videoRepo->createQueryBuilder('v')
                ->leftJoin('v.tenant', 't')
                ->leftJoin('v.bibliaBook', 'b')
                ->addSelect('t', 'b')
                ->where('v.bibliaBook = :book')
                ->andWhere('v.bibliaChapter = :chap')
                ->setParameter('book', $book)
                ->setParameter('chap', $chapter);

            if (!$searchAllVerses) {
                $vidQb->andWhere('v.bibliaVerseStart <= :qEnd')
                      ->andWhere('(v.bibliaVerseEnd >= :qStart OR (v.bibliaVerseEnd IS NULL AND v.bibliaVerseStart >= :qStart))')
                      ->setParameter('qStart', $qStart)
                      ->setParameter('qEnd', $qEnd);
            }

            if ($tenant) {
                $vidQb->andWhere('v.tenant = :tenant')->setParameter('tenant', $tenant);
            }

            /** @var VideoSupport $video */
            foreach ($vidQb->getQuery()->getResult() as $video) {
                $results[] = $this->formatVideoResult($video);
            }
        }

        // 3. Estudos / Materiais
        if ($typeNormalized === null || in_array($typeNormalized, ['study', 'material', 'estudo', 'studies', 'materials', 'estudos', 'materiais'], true)) {
            $studyQb = $this->studyRepo->createQueryBuilder('s')
                ->leftJoin('s.tenant', 't')
                ->leftJoin('s.bibliaBook', 'b')
                ->addSelect('t', 'b')
                ->where('s.bibliaBook = :book')
                ->andWhere('s.bibliaChapter = :chap')
                ->andWhere('s.active = :active')
                ->setParameter('book', $book)
                ->setParameter('chap', $chapter)
                ->setParameter('active', true);

            if (!$searchAllVerses) {
                $studyQb->andWhere('s.bibliaVerseStart <= :qEnd')
                        ->andWhere('(s.bibliaVerseEnd >= :qStart OR (s.bibliaVerseEnd IS NULL AND s.bibliaVerseStart >= :qStart))')
                        ->setParameter('qStart', $qStart)
                        ->setParameter('qEnd', $qEnd);
            }

            if ($tenant) {
                $studyQb->andWhere('s.tenant = :tenant')->setParameter('tenant', $tenant);
            }

            /** @var Study $study */
            foreach ($studyQb->getQuery()->getResult() as $study) {
                $results[] = $this->formatStudyResult($study);
            }
        }

        // 4. Páginas
        if ($typeNormalized === null || in_array($typeNormalized, ['page', 'pagina', 'pages', 'paginas'], true)) {
            $pageQb = $this->pageRepo->createQueryBuilder('p')
                ->leftJoin('p.tenant', 't')
                ->leftJoin('p.bibliaBook', 'b')
                ->addSelect('t', 'b')
                ->where('p.bibliaBook = :book')
                ->andWhere('p.bibliaChapter = :chap')
                ->setParameter('book', $book)
                ->setParameter('chap', $chapter);

            if (!$searchAllVerses) {
                $pageQb->andWhere('p.bibliaVerseStart <= :qEnd')
                       ->andWhere('(p.bibliaVerseEnd >= :qStart OR (p.bibliaVerseEnd IS NULL AND p.bibliaVerseStart >= :qStart))')
                       ->setParameter('qStart', $qStart)
                       ->setParameter('qEnd', $qEnd);
            }

            if ($tenant) {
                $pageQb->andWhere('p.tenant = :tenant')->setParameter('tenant', $tenant);
            }

            /** @var Page $page */
            foreach ($pageQb->getQuery()->getResult() as $page) {
                $results[] = $this->formatPageResult($page);
            }
        }

        return $results;
    }

    private function buildTenantBaseUrl(Tenant $tenant): string
    {
        $domain = trim($tenant->getDomain());
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return rtrim($domain, '/');
        }

        // Se for localhost ou IP, usa http, caso contrário https
        $scheme = (str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1')) ? 'http://' : 'https://';
        return rtrim($scheme . $domain, '/');
    }

    private function formatTenantData(Tenant $tenant, string $baseUrl): array
    {
        $logoUrl = null;
        if ($tenant->getLogo()) {
            $logoUrl = $baseUrl . '/uploads/tenant/logo/' . $tenant->getLogo();
        }

        return [
            'id' => $tenant->getId(),
            'name' => $tenant->getName(),
            'domain' => $tenant->getDomain(),
            'logo_url' => $logoUrl,
            'primary_color' => $tenant->getPrimaryColor(),
        ];
    }

    private function formatBibliaRef(?BibliaBook $book, ?int $chapter, ?int $verseStart, ?int $verseEnd): ?array
    {
        if (!$book || $chapter === null || $verseStart === null) {
            return null;
        }

        $formatted = ($verseEnd && $verseEnd > $verseStart)
            ? sprintf('%s %d:%d-%d', $book->getName(), $chapter, $verseStart, $verseEnd)
            : sprintf('%s %d:%d', $book->getName(), $chapter, $verseStart);

        return [
            'book_id' => $book->getId(),
            'book_name' => $book->getName(),
            'book_abbreviation' => $book->getAbbreviation(),
            'chapter' => $chapter,
            'verse_start' => $verseStart,
            'verse_end' => $verseEnd ?: $verseStart,
            'formatted' => $formatted,
        ];
    }

    private function formatArticleResult(Article $article): array
    {
        $tenant = $article->getTenant();
        $baseUrl = $tenant ? $this->buildTenantBaseUrl($tenant) : '';
        $articleUrl = $baseUrl . '/noticia/' . $article->getSlug();

        $imageUrl = null;
        if ($article->getImage()) {
            $imageUrl = $baseUrl . '/uploads/article/' . $article->getImage();
        }

        return [
            'id' => $article->getId(),
            'type' => 'article',
            'type_label' => 'Artigo / Notícia',
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'description' => $article->getShortDescription(),
            'url' => $articleUrl,
            'image_url' => $imageUrl,
            'tenant' => $tenant ? $this->formatTenantData($tenant, $baseUrl) : null,
            'biblical_reference' => $this->formatBibliaRef($article->getBibliaBook(), $article->getBibliaChapter(), $article->getBibliaVerseStart(), $article->getBibliaVerseEnd()),
            'published_at' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $article->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function formatVideoResult(VideoSupport $video): array
    {
        $tenant = $video->getTenant();
        $baseUrl = $tenant ? $this->buildTenantBaseUrl($tenant) : '';
        $videoUrl = $baseUrl . '/video/' . $video->getSlug();

        $thumbUrl = $video->getThumbnailUrl();
        if ($thumbUrl && str_starts_with($thumbUrl, '/')) {
            $thumbUrl = $baseUrl . $thumbUrl;
        }

        return [
            'id' => $video->getId(),
            'type' => 'video',
            'type_label' => 'Vídeo',
            'title' => $video->getTitle(),
            'slug' => $video->getSlug(),
            'description' => $video->getDescription(),
            'url' => $videoUrl,
            'image_url' => $thumbUrl,
            'video' => [
                'youtube_id' => $video->getYoutubeId(),
                'embed_url' => $video->getEmbedUrl(),
                'thumbnail_url' => $thumbUrl,
                'has_custom_thumbnail' => $video->hasCustomThumbnail(),
            ],
            'tenant' => $tenant ? $this->formatTenantData($tenant, $baseUrl) : null,
            'biblical_reference' => $this->formatBibliaRef($video->getBibliaBook(), $video->getBibliaChapter(), $video->getBibliaVerseStart(), $video->getBibliaVerseEnd()),
            'published_at' => null,
            'created_at' => $video->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function formatStudyResult(Study $study): array
    {
        $tenant = $study->getTenant();
        $baseUrl = $tenant ? $this->buildTenantBaseUrl($tenant) : '';
        $studyUrl = $baseUrl . '/estudo/' . $study->getSlug();

        $imageUrl = null;
        if ($study->getCoverImage()) {
            $imageUrl = $baseUrl . '/uploads/study_cover/' . $study->getCoverImage();
        }

        return [
            'id' => $study->getId(),
            'type' => 'study',
            'type_label' => 'Material / Estudo',
            'title' => $study->getTitle(),
            'slug' => $study->getSlug(),
            'description' => $study->getDescription(),
            'url' => $studyUrl,
            'image_url' => $imageUrl,
            'tenant' => $tenant ? $this->formatTenantData($tenant, $baseUrl) : null,
            'biblical_reference' => $this->formatBibliaRef($study->getBibliaBook(), $study->getBibliaChapter(), $study->getBibliaVerseStart(), $study->getBibliaVerseEnd()),
            'published_at' => null,
            'created_at' => $study->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function formatPageResult(Page $page): array
    {
        $tenant = $page->getTenant();
        $baseUrl = $tenant ? $this->buildTenantBaseUrl($tenant) : '';
        $pageUrl = $baseUrl . '/pagina/' . $page->getSlug();

        return [
            'id' => $page->getId(),
            'type' => 'page',
            'type_label' => 'Página Institucional',
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'description' => $page->getSeoDescription(),
            'url' => $pageUrl,
            'image_url' => null,
            'tenant' => $tenant ? $this->formatTenantData($tenant, $baseUrl) : null,
            'biblical_reference' => $this->formatBibliaRef($page->getBibliaBook(), $page->getBibliaChapter(), $page->getBibliaVerseStart(), $page->getBibliaVerseEnd()),
            'published_at' => null,
            'created_at' => null,
        ];
    }
}
