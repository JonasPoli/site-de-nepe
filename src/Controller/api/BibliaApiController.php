<?php

namespace App\Controller\api;

use App\Repository\BibliaBookRepository;
use App\Repository\TenantRepository;
use App\Service\BibliaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/biblia', name: 'api_biblia_')]
class BibliaApiController extends AbstractController
{
    public function __construct(
        private readonly BibliaService $bibliaService,
        private readonly BibliaBookRepository $bookRepo,
        private readonly TenantRepository $tenantRepo,
    ) {}

    #[Route('/contents', name: 'contents', methods: ['GET'])]
    public function contents(Request $request): JsonResponse
    {
        $bookParam = $request->query->get('book');
        $chapter = $request->query->getInt('chapter');
        $hasVerseFilter = $request->query->has('verse') || $request->query->has('verse_start');
        $verseStart = $hasVerseFilter ? ($request->query->getInt('verse_start') ?: $request->query->getInt('verse')) : null;
        $verseEnd = $request->query->has('verse_end') ? $request->query->getInt('verse_end') : $verseStart;
        $tenantParam = $request->query->get('tenant');
        $typeParam = $request->query->get('type');

        if (!$bookParam || $chapter <= 0) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Parâmetros obrigatórios ausentes: "book" (ID ou sigla do livro) e "chapter" (número do capítulo).'
            ], Response::HTTP_BAD_REQUEST, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $book = $this->bookRepo->findBySlugOrAbbrevOrId($bookParam);
        if (!$book) {
            return new JsonResponse([
                'status' => 'error',
                'message' => sprintf('Livro bíblico não encontrado para o parâmetro: "%s".', $bookParam)
            ], Response::HTTP_NOT_FOUND, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $tenant = null;
        if ($tenantParam) {
            $tenant = is_numeric($tenantParam)
                ? $this->tenantRepo->find((int) $tenantParam)
                : $this->tenantRepo->findOneBy(['domain' => $tenantParam]);
        }

        $vStart = $verseStart !== null && $verseStart > 0 ? $verseStart : null;
        $vEnd = $verseEnd !== null && $verseEnd > 0 ? $verseEnd : $vStart;
        if ($vStart !== null && $vEnd !== null && $vEnd < $vStart) {
            $tmp = $vStart;
            $vStart = $vEnd;
            $vEnd = $tmp;
        }

        $contents = $this->bibliaService->findContentsByPericope($book, $chapter, $vStart, $vEnd, $tenant, $typeParam);

        if ($vStart === null) {
            $refFormatted = sprintf('%s %d (Capítulo inteiro)', $book->getName(), $chapter);
        } elseif ($vStart === $vEnd) {
            $refFormatted = sprintf('%s %d:%d', $book->getName(), $chapter, $vStart);
        } else {
            $refFormatted = sprintf('%s %d:%d-%d', $book->getName(), $chapter, $vStart, $vEnd);
        }

        return new JsonResponse([
            'status' => 'success',
            'query' => [
                'book_id' => $book->getId(),
                'book_name' => $book->getName(),
                'book_abbreviation' => $book->getAbbreviation(),
                'chapter' => $chapter,
                'verse_start' => $vStart,
                'verse_end' => $vEnd,
                'reference_formatted' => $refFormatted,
                'type_filter' => $typeParam ?: null,
                'tenant_filter' => $tenant?->getDomain() ?? null,
            ],
            'total' => count($contents),
            'results' => $contents,
        ], Response::HTTP_OK, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }

    #[Route('/passage', name: 'passage', methods: ['GET'])]
    public function passage(Request $request): JsonResponse
    {
        $bookParam = $request->query->get('book');
        $chapter = (int) $request->query->get('chapter');
        $start = (int) ($request->query->get('start') ?: $request->query->get('verse_start')) ?: null;
        $end = (int) ($request->query->get('end') ?: $request->query->get('verse_end')) ?: null;

        if (!$bookParam || $chapter <= 0) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Parâmetros obrigatórios ausentes: "book" e "chapter".'
            ], Response::HTTP_BAD_REQUEST, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        $passage = $this->bibliaService->getPassage($bookParam, $chapter, $start, $end);
        if (!$passage) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Passagem bíblica não encontrada.'
            ], Response::HTTP_NOT_FOUND, [
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return new JsonResponse([
            'status' => 'success',
            'data' => $passage,
        ], Response::HTTP_OK, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
