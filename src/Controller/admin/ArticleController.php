<?php

namespace App\Controller\admin;

use App\Entity\Article;
use App\Entity\ArticleApproval;
use App\Entity\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/article', name: 'admin_article_')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ArticleRepository $repo): Response
    {
        return $this->render('admin/article/index.html.twig', [
            'articles' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ARTICLE_EDIT')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        TenantContext $tenantContext,
        CategoryRepository $categories,
        SluggerInterface $slugger,
    ): Response {
        $article = new Article();
        if ($request->isMethod('POST')) {
            $this->populateArticle($article, $request, $slugger, $em);
            $article->setTenant($tenantContext->requireTenant());
            $article->setAuthor($this->getUser());
            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Artigo criado.');
            return $this->redirectToRoute('admin_article_index');
        }
        return $this->render('admin/article/new.html.twig', [
            'article'    => $article,
            'categories' => $categories->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ARTICLE_EDIT')]
    public function edit(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categories,
        SluggerInterface $slugger,
    ): Response {
        if ($request->isMethod('POST')) {
            // ── Detectar mudança de conteúdo ─────────────────────────────────
            // Se o artigo já possui aprovações e o autor alterar title/conteúdo,
            // as aprovações são invalidadas e o status volta para Draft.
            $contentChanged = $this->contentChanged($article, $request);

            $this->populateArticle($article, $request, $slugger, $em);

            if ($contentChanged && $article->getApprovals()->count() > 0) {
                // Remover todas as aprovações existentes
                foreach ($article->getApprovals() as $approval) {
                    $em->remove($approval);
                }
                // Revogar publicação e voltar para rascunho
                $article->setStatus(ArticleStatus::Draft);
                $article->setPublishedAt(null);

                $this->addFlash('warning',
                    'O conteúdo foi alterado. As aprovações anteriores foram invalidadas e o artigo voltou para rascunho — será necessário enviar novamente para revisão.'
                );
            } else {
                $this->addFlash('success', 'Artigo atualizado.');
            }

            $em->flush();
            return $this->redirectToRoute('admin_article_index');
        }
        $messages = $em->getRepository(\App\Entity\Message::class)->findByArticleContext($article->getId());

        return $this->render('admin/article/edit.html.twig', [
            'article'    => $article,
            'categories' => $categories->findAll(),
            'messages'   => $messages,
        ]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Article $article, \App\Repository\MessageRepository $messageRepository): Response
    {
        $user = $this->getUser();
        $isAuthor = ($user instanceof \App\Entity\User && $article->getAuthor() === $user);

        // Se não for o autor, pode mandar msg para o autor
        $recipients = [];
        if (!$isAuthor && $article->getAuthor()) {
            $recipients[] = $article->getAuthor();
        }

        $messages = $messageRepository->findByArticleContext($article->getId());

        return $this->render('admin/article/show.html.twig', [
            'article'    => $article,
            'isAuthor'   => $isAuthor,
            'recipients' => $recipients,
            'messages'   => $messages,
        ]);
    }

    /** Submit for review — changes status from draft to pending */
    #[Route('/{id}/submit', name: 'submit', methods: ['POST'])]
    #[IsGranted('ARTICLE_EDIT')]
    public function submit(Article $article, EntityManagerInterface $em): Response
    {
        if ($article->getStatus() === ArticleStatus::Draft) {
            $article->setStatus(ArticleStatus::Pending);
            $em->flush();
            $this->addFlash('success', 'Artigo enviado para aprovação.');
        }
        return $this->redirectToRoute('admin_article_index');
    }

    /** Reviewer approves the article — auto-publishes if threshold is reached */
    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('ARTICLE_REVIEW')]
    public function approve(Article $article, Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($article->getAuthor() === $user) {
            $this->addFlash('error', 'Você não pode aprovar o próprio artigo.');
            return $this->redirectToRoute('admin_article_index');
        }

        if ($article->isApprovedBy($user)) {
            $this->addFlash('warning', 'Você já aprovou este artigo.');
            return $this->redirectToRoute('admin_article_index');
        }

        $approval = new ArticleApproval();
        $approval->setArticle($article);
        $approval->setReviewer($user);
        $approval->setComment($request->request->get('comment') ?: null);
        $em->persist($approval);

        // Auto-publish when approvals >= tenant.requiredApprovals
        $required = $article->getTenant()?->getRequiredApprovals() ?? 1;
        if ($article->getApprovalCount() + 1 >= $required) {
            $article->setStatus(ArticleStatus::Published);
            $article->setPublishedAt(new \DateTimeImmutable());
        }

        $em->flush();
        $this->addFlash('success', 'Aprovação registrada.');
        return $this->redirectToRoute('admin_article_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Article $article, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_article_' . $article->getId(), (string) $request->request->get('_token'))) {
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Artigo removido.');
        }
        return $this->redirectToRoute('admin_article_index');
    }

    /**
     * Verifica se os campos de conteúdo relevantes foram alterados.
     * Compara o estado atual do artigo (banco) com os dados enviados no formulário
     * ANTES de chamar populateArticle(), enquanto o entity ainda tem os valores originais.
     */
    private function contentChanged(Article $article, Request $r): bool
    {
        $fields = [
            'title'            => $article->getTitle(),
            'shortDescription' => (string) $article->getShortDescription(),
            'content'          => (string) $article->getContent(),
        ];

        foreach ($fields as $field => $current) {
            $submitted = trim((string) $r->request->get($field));
            if (trim($current) !== $submitted) {
                return true;
            }
        }

        return false;
    }

    private function populateArticle(Article $article, Request $r, SluggerInterface $slugger, EntityManagerInterface $em): void
    {
        $article->setTitle((string) $r->request->get('title'));
        $article->setShortDescription($r->request->get('shortDescription') ?: null);
        $article->setContent($r->request->get('content') ?: null);
        $article->setSeoTitle($r->request->get('seoTitle') ?: null);
        $article->setSeoDescription($r->request->get('seoDescription') ?: null);
        $article->setCanonicalUrl($r->request->get('canonicalUrl') ?: null);
        $article->setIsNoIndex((bool) $r->request->get('isNoIndex'));
        $article->setImageAlt($r->request->get('imageAlt') ?: null);

        $catId = (int) $r->request->get('category');
        $article->setCategory($catId ? $em->getReference(\App\Entity\Category::class, $catId) : null);

        $slug = $r->request->get('slug');
        $article->setSlug($slug ?: strtolower((string) $slugger->slug($article->getTitle())));

        // ── Referência Bíblica / Perícope ──────────────────────────────────
        if ($r->request->get('has_biblia_ref') && $r->request->get('biblia_book_id')) {
            $bookId = (int) $r->request->get('biblia_book_id');
            $article->setBibliaBook($bookId ? $em->getReference(\App\Entity\BibliaBook::class, $bookId) : null);
            $article->setBibliaChapter($r->request->getInt('biblia_chapter') ?: null);
            $article->setBibliaVerseStart($r->request->getInt('biblia_verse_start') ?: null);
            $article->setBibliaVerseEnd($r->request->getInt('biblia_verse_end') ?: null);
        } else {
            $article->setBibliaBook(null);
            $article->setBibliaChapter(null);
            $article->setBibliaVerseStart(null);
            $article->setBibliaVerseEnd(null);
        }

        // ── Main Image ────────────────────────────────────────────────────────
        $imageFile = $r->files->get('imageFile');
        if ($imageFile) {
            $article->setImageFile($imageFile);
        }

        // ── Delete removed gallery images ─────────────────────────────────────
        $deleteIds = array_filter(array_map('intval', (array) $r->request->all('delete_gallery')));
        foreach ($article->getGallery() as $img) {
            if (in_array($img->getId(), $deleteIds, true)) {
                $em->remove($img);
            }
        }

        // ── Add new uploaded gallery images ───────────────────────────────────
        $galleryFiles = $r->files->all('gallery_file');
        foreach ($galleryFiles as $file) {
            if ($file) {
                $img = new \App\Entity\ArticleImage();
                $img->setArticle($article);
                $img->setFile($file);
                $em->persist($img);
            }
        }
    }
}
