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
        $verseStart = $request->query->getInt('verse_start') ?: $request->query->getInt('verse');
        $verseEnd = $request->query->getInt('verse_end') ?: $verseStart;
        $tenantParam = $request->query->get('tenant');

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

        $vStart = $verseStart > 0 ? $verseStart : 1;
        $vEnd = $verseEnd > 0 ? $verseEnd : $vStart;
        if ($vEnd < $vStart) {
            $tmp = $vStart;
            $vStart = $vEnd;
            $vEnd = $tmp;
        }

        $contents = $this->bibliaService->findContentsByPericope($book, $chapter, $vStart, $vEnd, $tenant);

        $refFormatted = ($vStart === $vEnd)
            ? sprintf('%s %d:%d', $book->getName(), $chapter, $vStart)
            : sprintf('%s %d:%d-%d', $book->getName(), $chapter, $vStart, $vEnd);

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
        $chapter = $request->query->getInt('chapter');
        $start = $request->query->getInt('start') ?: $request->query->getInt('verse_start');
        $end = $request->query->getInt('end') ?: $request->query->getInt('verse_end');

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
