<?php

namespace App\Controller\admin;

use App\Contract\PublishableInterface;
use App\Entity\Study;
use App\Entity\User;
use App\Entity\VideoSupport;
use App\Service\ContentApprovalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Approval workflow actions for studies and videos (articles keep theirs in ArticleController).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'admin_')]
class ContentApprovalController extends AbstractController
{
    public function __construct(private readonly ContentApprovalService $approvals) {}

    #[Route('/study/{id}/submit', name: 'study_submit', methods: ['POST'])]
    public function submitStudy(Study $study, Request $request): Response
    {
        return $this->submit($study, $request, 'study', 'admin_study_index');
    }

    /** Tela de revisão: o colega lê o material inteiro antes de aprovar (equivalente ao show do artigo) */
    #[Route('/study/{id}/review', name: 'study_review', methods: ['GET'])]
    public function reviewStudy(Study $study, \App\Service\BibliaService $biblia): Response
    {
        $this->denyUnlessSameTenant($study);

        return $this->render('admin/study/show.html.twig', [
            'study'   => $study,
            'passage' => $study->hasBibliaReference()
                ? $biblia->getPassage($study->getBibliaBook(), $study->getBibliaChapter(), $study->getBibliaVerseStart(), $study->getBibliaVerseEnd())
                : null,
        ]);
    }

    #[Route('/study/{id}/approve', name: 'study_approve', methods: ['POST'])]
    #[IsGranted('CONTENT_REVIEW')]
    public function approveStudy(Study $study, Request $request): Response
    {
        return $this->approve($study, $request, 'study', 'admin_study_index');
    }

    #[Route('/video/{id}/submit', name: 'video_submit', methods: ['POST'])]
    public function submitVideo(VideoSupport $video, Request $request): Response
    {
        return $this->submit($video, $request, 'video', 'admin_video_index');
    }

    #[Route('/video/{id}/approve', name: 'video_approve', methods: ['POST'])]
    #[IsGranted('CONTENT_REVIEW')]
    public function approveVideo(VideoSupport $video, Request $request): Response
    {
        return $this->approve($video, $request, 'video', 'admin_video_index');
    }

    private function submit(PublishableInterface $content, Request $request, string $type, string $redirectRoute): Response
    {
        $this->denyUnlessSameTenant($content);

        if ($this->isCsrfTokenValid(sprintf('submit_%s_%d', $type, $content->getId()), (string) $request->request->get('_token'))) {
            $this->approvals->submit($content)
                ? $this->addFlash('success', 'Enviado para aprovação.')
                : $this->addFlash('warning', 'Só rascunhos podem ser enviados para aprovação.');
        }

        return $this->redirectToRoute($redirectRoute);
    }

    /** As rotas /admin não passam pelo filtro de tenant: confere que o conteúdo é deste site */
    private function denyUnlessSameTenant(PublishableInterface $content): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isSuperAdmin() && $user->getTenant()?->getId() !== $content->getTenant()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function approve(PublishableInterface $content, Request $request, string $type, string $redirectRoute): Response
    {
        if ($this->isCsrfTokenValid(sprintf('approve_%s_%d', $type, $content->getId()), (string) $request->request->get('_token'))) {
            /** @var User $user */
            $user = $this->getUser();
            [$flashType, $message] = $this->approvals->approve($content, $user, $request->request->get('comment') ?: null)->flash();
            $this->addFlash($flashType, $message);
        }

        return $this->redirectToRoute($redirectRoute);
    }
}
