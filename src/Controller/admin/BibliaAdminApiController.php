<?php

namespace App\Controller\admin;

use App\Service\BibliaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/api/biblia', name: 'admin_api_biblia_')]
class BibliaAdminApiController extends AbstractController
{
    public function __construct(
        private readonly BibliaService $bibliaService,
    ) {}

    #[Route('/structure', name: 'structure', methods: ['GET'])]
    public function structure(): JsonResponse
    {
        return new JsonResponse($this->bibliaService->getStructure());
    }

    #[Route('/passage', name: 'passage', methods: ['GET'])]
    public function passage(Request $request): JsonResponse
    {
        $book = $request->query->get('book');
        $chapter = (int) $request->query->get('chapter');
        $start = (int) $request->query->get('start') ?: null;
        $end = (int) $request->query->get('end') ?: null;

        if (!$book || $chapter <= 0) {
            return new JsonResponse(['error' => 'Parâmetros inválidos (book e chapter são obrigatórios).'], 400);
        }

        $passage = $this->bibliaService->getPassage($book, $chapter, $start, $end);

        if (!$passage) {
            return new JsonResponse(['error' => 'Passagem não encontrada para os parâmetros informados.'], 404);
        }

        return new JsonResponse($passage);
    }
}
