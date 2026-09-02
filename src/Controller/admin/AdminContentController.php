<?php

namespace App\Controller\admin;

use App\Entity\HeroBanner;
use App\Entity\ResearchLine;
use App\Entity\Category;
use App\Entity\Study;
use App\Entity\StudyMaterial;
use App\Entity\VideoMaterial;
use App\Entity\VideoSupport;
use App\Entity\Page;
use App\Entity\PageSection;
use App\Entity\PageBlock;
use App\Entity\ContactMessage;
use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\HeroBannerRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\PageRepository;
use App\Repository\PageSectionRepository;
use App\Repository\PageBlockRepository;
use App\Repository\ResearchLineRepository;
use App\Repository\StudyRepository;
use App\Repository\UserRepository;
use App\Repository\VideoSupportRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'admin_')]
class AdminContentController extends AbstractController
{
    // ── Dashboard ────────────────────────────────────────────────────────────

    #[Route('', name: 'dash')]
    public function dashboard(
        ContactMessageRepository $contacts,
        ResearchLineRepository $lines,
        \App\Service\MessageService $messageService
    ): Response {
        $user = $this->getUser();
        $unreadInternal = $user instanceof \App\Entity\User ? $messageService->getUnreadCount($user) : 0;

        return $this->render('admin/dashboard.html.twig', [
            'unreadContacts' => $contacts->countUnread(),
            'lineCount'      => count($lines->findAll()),
            'unreadInternal' => $unreadInternal,
        ]);
    }

    // ── HeroBanner ───────────────────────────────────────────────────────────

    #[Route('/banner', name: 'banner_index')]
    public function bannerIndex(HeroBannerRepository $repo): Response
    {
        return $this->render('admin/banner/index.html.twig', ['banners' => $repo->findBy([], ['position' => 'ASC', 'id' => 'ASC'])]);
    }

    #[Route('/banner/new', name: 'banner_new', methods: ['GET', 'POST'])]
    public function bannerNew(Request $r, EntityManagerInterface $em, TenantContext $tc): Response
    {
        $banner = new HeroBanner();
        if ($r->isMethod('POST')) {
            $banner->setTenant($tc->requireTenant());
            $banner->setTitle((string) $r->request->get('title'));
            $banner->setSubtitle($r->request->get('subtitle') ?: null);
            $banner->setCtaText($r->request->get('ctaText') ?: null);
            $banner->setCtaLink($r->request->get('ctaLink') ?: null);
            $banner->setActive((bool) $r->request->get('active'));
            $file = $r->files->get('backgroundImageFile');
            if ($file instanceof UploadedFile) { $banner->setBackgroundImageFile($file); }
            $em->persist($banner);
            $em->flush();
            $this->addFlash('success', 'Banner criado.');
            return $this->redirectToRoute('admin_banner_index');
        }
        return $this->render('admin/banner/new.html.twig', ['banner' => $banner]);
    }

    #[Route('/banner/{id}/edit', name: 'banner_edit', methods: ['GET', 'POST'])]
    public function bannerEdit(HeroBanner $banner, Request $r, EntityManagerInterface $em): Response
    {
        if ($r->isMethod('POST')) {
            $banner->setTitle((string) $r->request->get('title'));
            $banner->setSubtitle($r->request->get('subtitle') ?: null);
            $banner->setCtaText($r->request->get('ctaText') ?: null);
            $banner->setCtaLink($r->request->get('ctaLink') ?: null);
            $banner->setActive((bool) $r->request->get('active'));
            $file = $r->files->get('backgroundImageFile');
            if ($file instanceof UploadedFile) { $banner->setBackgroundImageFile($file); }
            $em->flush();
            $this->addFlash('success', 'Banner atualizado.');
            return $this->redirectToRoute('admin_banner_index');
        }
        return $this->render('admin/banner/edit.html.twig', ['banner' => $banner]);
    }

    #[Route('/banner/reorder', name: 'banner_reorder', methods: ['POST'])]
    public function bannerReorder(Request $r, HeroBannerRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ids = json_decode($r->getContent(), true)['ids'] ?? [];
        foreach ($ids as $pos => $id) {
            $banner = $repo->find((int) $id);
            if ($banner) { $banner->setPosition($pos + 1); }
        }
        $em->flush();
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/banner/{id}/delete', name: 'banner_delete', methods: ['POST'])]
    public function bannerDelete(HeroBanner $banner, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_banner_' . $banner->getId(), (string) $r->request->get('_token'))) {
            $em->remove($banner);
            $em->flush();
        }
        return $this->redirectToRoute('admin_banner_index');
    }

    // ── ResearchLine ─────────────────────────────────────────────────────────

    #[Route('/research-line', name: 'research_line_index')]
    public function researchLineIndex(ResearchLineRepository $repo): Response
    {
        return $this->render('admin/research_line/index.html.twig', ['lines' => $repo->findBy([], ['position' => 'ASC'])]);
    }

    #[Route('/research-line/new', name: 'research_line_new', methods: ['GET', 'POST'])]
    public function researchLineNew(Request $r, EntityManagerInterface $em, TenantContext $tc): Response
    {
        $line = new ResearchLine();
        if ($r->isMethod('POST')) {
            $line->setTenant($tc->requireTenant());
            $line->setTitle((string) $r->request->get('title'));
            $line->setDescription($r->request->get('description') ?: null);
            $line->setIcon($r->request->get('icon') ?: null);
            $em->persist($line);
            $em->flush();
            $this->addFlash('success', 'Linha de pesquisa criada.');
            return $this->redirectToRoute('admin_research_line_index');
        }
        return $this->render('admin/research_line/new.html.twig', ['line' => $line]);
    }

    #[Route('/research-line/{id}/edit', name: 'research_line_edit', methods: ['GET', 'POST'])]
    public function researchLineEdit(ResearchLine $line, Request $r, EntityManagerInterface $em): Response
    {
        if ($r->isMethod('POST')) {
            $line->setTitle((string) $r->request->get('title'));
            $line->setDescription($r->request->get('description') ?: null);
            $line->setIcon($r->request->get('icon') ?: null);
            $em->flush();
            $this->addFlash('success', 'Linha de pesquisa atualizada.');
            return $this->redirectToRoute('admin_research_line_index');
        }
        return $this->render('admin/research_line/edit.html.twig', ['line' => $line]);
    }

    #[Route('/research-line/{id}/delete', name: 'research_line_delete', methods: ['POST'])]
    public function researchLineDelete(ResearchLine $line, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_rl_' . $line->getId(), (string) $r->request->get('_token'))) {
            $em->remove($line);
            $em->flush();
        }
        return $this->redirectToRoute('admin_research_line_index');
    }

    #[Route('/research-line/reorder', name: 'research_line_reorder', methods: ['POST'])]
    public function researchLineReorder(Request $r, ResearchLineRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        return $this->reorderEntities($r, $repo, $em);
    }

    // ── Category ─────────────────────────────────────────────────────────────

    #[Route('/category', name: 'category_index')]
    public function categoryIndex(CategoryRepository $repo): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $repo->findRootCategories(),
        ]);
    }

    #[Route('/category/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $r, EntityManagerInterface $em, TenantContext $tc, SluggerInterface $slugger): Response
    {
        $cat = new Category();
        if ($r->isMethod('POST')) {
            $cat->setTenant($tc->requireTenant());
            $this->populateCategory($cat, $r, $slugger);
            $em->persist($cat);
            $em->flush();
            $this->addFlash('success', 'Categoria criada.');
            return $this->redirectToRoute('admin_category_index');
        }
        return $this->render('admin/category/new.html.twig', ['category' => $cat]);
    }

    #[Route('/category/{id}/edit', name: 'category_edit', methods: ['GET', 'POST'])]
    public function categoryEdit(Category $cat, Request $r, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($r->isMethod('POST')) {
            $this->populateCategory($cat, $r, $slugger);
            $em->flush();
            $this->addFlash('success', 'Categoria atualizada.');
            return $this->redirectToRoute('admin_category_index');
        }
        return $this->render('admin/category/edit.html.twig', ['category' => $cat]);
    }

    #[Route('/category/{id}/delete', name: 'category_delete', methods: ['POST'])]
    public function categoryDelete(Category $cat, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_cat_' . $cat->getId(), (string) $r->request->get('_token'))) {
            $em->remove($cat);
            $em->flush();
        }
        return $this->redirectToRoute('admin_category_index');
    }

    // ── SubCategory ──────────────────────────────────────────────────────────

    #[Route('/category/{id}/sub', name: 'subcategory_index')]
    public function subcategoryIndex(Category $cat): Response
    {
        return $this->render('admin/category/sub_index.html.twig', [
            'category' => $cat,
            'subcategories' => $cat->getChildren(),
        ]);
    }

    #[Route('/category/{id}/sub/new', name: 'subcategory_new', methods: ['GET', 'POST'])]
    public function subcategoryNew(Category $cat, Request $r, EntityManagerInterface $em, TenantContext $tc, SluggerInterface $slugger): Response
    {
        // Sub-categories cannot themselves have children (max 1 level)
        if ($cat->isSubCategory()) {
            $this->addFlash('danger', 'Sub-categorias não podem ter sub-categorias.');
            return $this->redirectToRoute('admin_category_index');
        }
        $sub = new Category();
        if ($r->isMethod('POST')) {
            $sub->setTenant($tc->requireTenant());
            $sub->setParent($cat);
            $this->populateCategory($sub, $r, $slugger);
            $em->persist($sub);
            $em->flush();
            $this->addFlash('success', 'Sub-categoria criada.');
            return $this->redirectToRoute('admin_subcategory_index', ['id' => $cat->getId()]);
        }
        return $this->render('admin/category/sub_new.html.twig', [
            'category' => $cat,
            'subcategory' => $sub,
        ]);
    }

    #[Route('/subcategory/{id}/edit', name: 'subcategory_edit', methods: ['GET', 'POST'])]
    public function subcategoryEdit(Category $sub, Request $r, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $parent = $sub->getParent();
        if ($r->isMethod('POST')) {
            $this->populateCategory($sub, $r, $slugger);
            $em->flush();
            $this->addFlash('success', 'Sub-categoria atualizada.');
            return $this->redirectToRoute('admin_subcategory_index', ['id' => $parent?->getId()]);
        }
        return $this->render('admin/category/sub_edit.html.twig', [
            'category' => $parent,
            'subcategory' => $sub,
        ]);
    }

    #[Route('/subcategory/{id}/delete', name: 'subcategory_delete', methods: ['POST'])]
    public function subcategoryDelete(Category $sub, Request $r, EntityManagerInterface $em): Response
    {
        $parentId = $sub->getParent()?->getId();
        if ($this->isCsrfTokenValid('del_sub_' . $sub->getId(), (string) $r->request->get('_token'))) {
            $em->remove($sub);
            $em->flush();
        }
        return $this->redirectToRoute('admin_subcategory_index', ['id' => $parentId]);
    }

    // ── Category Sections ─────────────────────────────────────────────────────

    #[Route('/category/{catId}/section', name: 'cat_section_index')]
    public function catSectionIndex(int $catId, CategoryRepository $cats): Response
    {
        $cat = $cats->find($catId) ?? throw $this->createNotFoundException();
        return $this->render('admin/section/index.html.twig', [
            'page'     => null,
            'category' => $cat,
            'sections' => $cat->getSections(),
        ]);
    }

    #[Route('/category/{catId}/section/new', name: 'cat_section_new', methods: ['GET', 'POST'])]
    public function catSectionNew(int $catId, Request $r, EntityManagerInterface $em, CategoryRepository $cats): Response
    {
        $cat = $cats->find($catId) ?? throw $this->createNotFoundException();
        $section = new PageSection();
        if ($r->isMethod('POST')) {
            $section->setCategory($cat);
            $section->setTitlePart1($r->request->get('titlePart1') ?: null);
            $section->setTitlePart2($r->request->get('titlePart2') ?: null);
            $section->setActive((bool) $r->request->get('active'));
            $em->persist($section);
            $em->flush();
            $this->addFlash('success', 'Seção criada.');
            return $this->redirectToRoute('admin_cat_section_index', ['catId' => $catId]);
        }
        return $this->render('admin/section/new.html.twig', [
            'page'     => null,
            'category' => $cat,
            'section'  => $section,
        ]);
    }

    // ── VideoSupport ─────────────────────────────────────────────────────────

    #[Route('/video', name: 'video_index')]
    public function videoIndex(VideoSupportRepository $repo): Response
    {
        return $this->render('admin/video/index.html.twig', ['videos' => $repo->findBy([], ['createdAt' => 'DESC'])]);
    }

    #[Route('/video/new', name: 'video_new', methods: ['GET', 'POST'])]
    public function videoNew(Request $r, EntityManagerInterface $em, TenantContext $tc, CategoryRepository $cats, SluggerInterface $slugger): Response
    {
        $video = new VideoSupport();
        if ($r->isMethod('POST')) {
            $video->setTenant($tc->requireTenant());
            $this->populateVideo($video, $r, $slugger, $em);
            $em->persist($video);
            $em->flush();
            $this->addFlash('success', 'Vídeo criado.');
            return $this->redirectToRoute('admin_video_index');
        }
        return $this->render('admin/video/new.html.twig', ['video' => $video, 'categories' => $cats->findAll()]);
    }

    #[Route('/video/{id}/edit', name: 'video_edit', methods: ['GET', 'POST'])]
    public function videoEdit(VideoSupport $video, Request $r, EntityManagerInterface $em, CategoryRepository $cats, SluggerInterface $slugger): Response
    {
        if ($r->isMethod('POST')) {
            $this->populateVideo($video, $r, $slugger, $em);
            $em->flush();
            $this->addFlash('success', 'Vídeo atualizado.');
            return $this->redirectToRoute('admin_video_index');
        }
        return $this->render('admin/video/edit.html.twig', [
            'video'      => $video,
            'categories' => $cats->findAll(),
        ]);
    }

    #[Route('/video/{id}/delete', name: 'video_delete', methods: ['POST'])]
    public function videoDelete(VideoSupport $video, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_video_' . $video->getId(), (string) $r->request->get('_token'))) {
            $em->remove($video);
            $em->flush();
        }
        return $this->redirectToRoute('admin_video_index');
    }

    // ── Study ─────────────────────────────────────────────────────────────────

    #[Route('/study', name: 'study_index')]
    public function studyIndex(StudyRepository $repo): Response
    {
        return $this->render('admin/study/index.html.twig', ['studies' => $repo->findBy([], ['createdAt' => 'DESC'])]);
    }

    #[Route('/study/new', name: 'study_new', methods: ['GET', 'POST'])]
    public function studyNew(Request $r, EntityManagerInterface $em, TenantContext $tc, CategoryRepository $cats, SluggerInterface $slugger): Response
    {
        $study = new Study();
        if ($r->isMethod('POST')) {
            $study->setTenant($tc->requireTenant());
            $study->setAuthor($this->getUser() instanceof User ? $this->getUser() : null);
            $this->populateStudy($study, $r, $slugger, $em);
            $em->persist($study);
            $em->flush();
            $this->addFlash('success', 'Material criado.');
            return $this->redirectToRoute('admin_study_index');
        }
        return $this->render('admin/study/new.html.twig', ['study' => $study, 'categories' => $cats->findAll()]);
    }

    #[Route('/study/{id}/edit', name: 'study_edit', methods: ['GET', 'POST'])]
    public function studyEdit(Study $study, Request $r, EntityManagerInterface $em, CategoryRepository $cats, SluggerInterface $slugger): Response
    {
        if ($r->isMethod('POST')) {
            $this->populateStudy($study, $r, $slugger, $em);
            $em->flush();
            $this->addFlash('success', 'Material atualizado.');
            return $this->redirectToRoute('admin_study_index');
        }
        return $this->render('admin/study/edit.html.twig', [
            'study'      => $study,
            'categories' => $cats->findAll(),
        ]);
    }

    #[Route('/study/{id}/delete', name: 'study_delete', methods: ['POST'])]
    public function studyDelete(Study $study, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_study_' . $study->getId(), (string) $r->request->get('_token'))) {
            $em->remove($study);
            $em->flush();
        }
        return $this->redirectToRoute('admin_study_index');
    }

    // ── ContactMessage ────────────────────────────────────────────────────────

    #[Route('/contact', name: 'contact_index')]
    public function contactIndex(ContactMessageRepository $repo): Response
    {
        return $this->render('admin/contact/index.html.twig', ['messages' => $repo->findBy([], ['createdAt' => 'DESC'])]);
    }

    #[Route('/contact/{id}/read', name: 'contact_read', methods: ['POST'])]
    public function contactRead(ContactMessage $msg, EntityManagerInterface $em): Response
    {
        $msg->setIsRead(true);
        $em->flush();
        return $this->redirectToRoute('admin_contact_index');
    }

    #[Route('/contact/{id}/delete', name: 'contact_delete', methods: ['POST'])]
    public function contactDelete(ContactMessage $msg, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_contact_' . $msg->getId(), (string) $r->request->get('_token'))) {
            $em->remove($msg);
            $em->flush();
        }
        return $this->redirectToRoute('admin_contact_index');
    }

    // ── Newsletter ────────────────────────────────────────────────────────────

    #[Route('/newsletter', name: 'newsletter_index')]
    public function newsletterIndex(NewsletterSubscriberRepository $repo, TenantContext $tc): Response
    {
        $tenant = $tc->getTenant();
        $subscribers = $tenant ? $repo->findByTenant($tenant) : [];
        return $this->render('admin/newsletter/index.html.twig', ['subscribers' => $subscribers]);
    }

    #[Route('/newsletter/{id}/delete', name: 'newsletter_delete', methods: ['POST'])]
    public function newsletterDelete(NewsletterSubscriber $sub, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_nl_' . $sub->getId(), (string) $r->request->get('_token'))) {
            $em->remove($sub);
            $em->flush();
        }
        return $this->redirectToRoute('admin_newsletter_index');
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    #[Route('/page', name: 'page_index')]
    public function pageIndex(PageRepository $repo): Response
    {
        return $this->render('admin/page/index.html.twig', ['pages' => $repo->findAll()]);
    }

    #[Route('/page/new', name: 'page_new', methods: ['GET', 'POST'])]
    public function pageNew(Request $r, EntityManagerInterface $em, TenantContext $tc, SluggerInterface $slugger): Response
    {
        $page = new Page();
        if ($r->isMethod('POST')) {
            $page->setTenant($tc->requireTenant());
            $this->populatePage($page, $r, $slugger);
            $em->persist($page);
            $em->flush();
            $this->addFlash('success', 'Página criada.');
            return $this->redirectToRoute('admin_page_index');
        }
        return $this->render('admin/page/new.html.twig', ['page' => $page]);
    }

    #[Route('/page/{id}/edit', name: 'page_edit', methods: ['GET', 'POST'])]
    public function pageEdit(Page $page, Request $r, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if ($r->isMethod('POST')) {
            $this->populatePage($page, $r, $slugger);
            $em->flush();
            $this->addFlash('success', 'Página atualizada.');
            return $this->redirectToRoute('admin_page_index');
        }
        return $this->render('admin/page/edit.html.twig', ['page' => $page]);
    }

    #[Route('/page/{id}/delete', name: 'page_delete', methods: ['POST'])]
    public function pageDelete(Page $page, Request $r, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_page_' . $page->getId(), (string) $r->request->get('_token'))) {
            $em->remove($page);
            $em->flush();
        }
        return $this->redirectToRoute('admin_page_index');
    }

    // ── PageSection ───────────────────────────────────────────────────────────

    #[Route('/page/{pageId}/section', name: 'section_index')]
    public function sectionIndex(int $pageId, PageRepository $pages): Response
    {
        $page = $pages->find($pageId) ?? throw $this->createNotFoundException();
        return $this->render('admin/section/index.html.twig', ['page' => $page, 'sections' => $page->getSections()]);
    }

    #[Route('/page/{pageId}/section/new', name: 'section_new', methods: ['GET', 'POST'])]
    public function sectionNew(int $pageId, Request $r, EntityManagerInterface $em, PageRepository $pages): Response
    {
        $page = $pages->find($pageId) ?? throw $this->createNotFoundException();
        $section = new PageSection();
        if ($r->isMethod('POST')) {
            $section->setPage($page);
            $section->setTitlePart1($r->request->get('titlePart1') ?: null);
            $section->setTitlePart2($r->request->get('titlePart2') ?: null);
            $section->setActive((bool) $r->request->get('active'));
            $em->persist($section);
            $em->flush();
            $this->addFlash('success', 'Seção criada.');
            return $this->redirectToRoute('admin_section_index', ['pageId' => $pageId]);
        }
        return $this->render('admin/section/new.html.twig', ['page' => $page, 'section' => $section]);
    }

    #[Route('/section/{id}/edit', name: 'section_edit', methods: ['GET', 'POST'])]
    public function sectionEdit(PageSection $section, Request $r, EntityManagerInterface $em): Response
    {
        if ($r->isMethod('POST')) {
            $section->setTitlePart1($r->request->get('titlePart1') ?: null);
            $section->setTitlePart2($r->request->get('titlePart2') ?: null);
            $section->setActive((bool) $r->request->get('active'));
            $em->flush();
            $this->addFlash('success', 'Seção atualizada.');
            if ($section->getCategory()) {
                return $this->redirectToRoute('admin_cat_section_index', ['catId' => $section->getCategory()->getId()]);
            }
            return $this->redirectToRoute('admin_section_index', ['pageId' => $section->getPage()?->getId()]);
        }
        return $this->render('admin/section/edit.html.twig', ['section' => $section]);
    }

    #[Route('/section/{id}/delete', name: 'section_delete', methods: ['POST'])]
    public function sectionDelete(PageSection $section, Request $r, EntityManagerInterface $em): Response
    {
        $catId  = $section->getCategory()?->getId();
        $pageId = $section->getPage()?->getId();
        if ($this->isCsrfTokenValid('del_sec_' . $section->getId(), (string) $r->request->get('_token'))) {
            $em->remove($section);
            $em->flush();
        }
        if ($catId) {
            return $this->redirectToRoute('admin_cat_section_index', ['catId' => $catId]);
        }
        return $this->redirectToRoute('admin_section_index', ['pageId' => $pageId]);
    }

    #[Route('/section/reorder', name: 'section_reorder', methods: ['POST'])]
    public function sectionReorder(Request $r, PageSectionRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        return $this->reorderEntities($r, $repo, $em);
    }

    // ── PageBlock ─────────────────────────────────────────────────────────────

    #[Route('/section/{sectionId}/block', name: 'block_index')]
    public function blockIndex(int $sectionId, PageSectionRepository $sections): Response
    {
        $section = $sections->find($sectionId) ?? throw $this->createNotFoundException();
        return $this->render('admin/block/index.html.twig', ['section' => $section, 'blocks' => $section->getBlocks()]);
    }

    #[Route('/section/{sectionId}/block/new', name: 'block_new', methods: ['GET', 'POST'])]
    public function blockNew(
        int $sectionId, Request $r, EntityManagerInterface $em,
        PageSectionRepository $sections, CategoryRepository $cats,
        PageRepository $pages
    ): Response {
        $section = $sections->find($sectionId) ?? throw $this->createNotFoundException();
        $block = new PageBlock();
        $type  = $r->query->get('type', $r->request->get('type', ''));

        if ($r->isMethod('POST')) {
            $this->populateBlock($block, $r, $em, $cats);
            $block->setSection($section);
            $em->persist($block);
            $em->flush();
            $this->addFlash('success', 'Bloco criado.');
            return $this->redirectToRoute('admin_block_index', ['sectionId' => $sectionId]);
        }
        return $this->render('admin/block/new.html.twig', [
            'section'    => $section,
            'block'      => $block,
            'type'       => $type ?: null,
            'categories' => $cats->findRootCategories(),
            'pages'      => $pages->findBy([], ['title' => 'ASC']),
        ]);
    }

    #[Route('/block/{id}/edit', name: 'block_edit', methods: ['GET', 'POST'])]
    public function blockEdit(
        PageBlock $block, Request $r, EntityManagerInterface $em,
        CategoryRepository $cats, PageRepository $pages
    ): Response {
        if ($r->isMethod('POST')) {
            $this->populateBlock($block, $r, $em, $cats);
            $em->flush();
            $this->addFlash('success', 'Bloco atualizado.');
            return $this->redirectToRoute('admin_block_index', ['sectionId' => $block->getSection()?->getId()]);
        }
        return $this->render('admin/block/edit.html.twig', [
            'block'      => $block,
            'type'       => $block->getType(),
            'categories' => $cats->findRootCategories(),
            'pages'      => $pages->findBy([], ['title' => 'ASC']),
        ]);
    }

    private function populateBlock(PageBlock $block, Request $r, EntityManagerInterface $em, CategoryRepository $cats): void
    {
        $type = $r->request->get('type', $block->getType() ?: 'text_image');
        $block->setType($type);
        $block->setPreTitle($r->request->get('preTitle') ?: null);
        $block->setTitle($r->request->get('title') ?: null);
        $block->setText($r->request->get('text') ?: null);
        $block->setEmbedUrl($r->request->get('embedUrl') ?: null);
        $block->setItemCount($r->request->get('itemCount') !== null ? (int)$r->request->get('itemCount') ?: null : null);

        // Related category
        $catId = (int) $r->request->get('relatedCategoryId');
        $block->setRelatedCategory($catId ? $cats->find($catId) : null);

        // Config JSON (blurbs4, stats, newsletter, map, contact, etc.)
        $cfg = $r->request->all('config');
        $block->setConfig($cfg ?: null);

        // Main image (text_image)
        $file = $r->files->get('imageFile');
        if ($file instanceof UploadedFile && $file->isValid()) { $block->setImageFile($file); }

        // ── Gallery images ───────────────────────────────────────────────────
        if ($type === 'gallery') {
            $delIds = array_filter(array_map('intval', (array)$r->request->all('delete_gallery')));
            foreach ($block->getGalleryImages() as $img) {
                if (in_array($img->getId(), $delIds, true)) { $em->remove($img); }
            }
            foreach ((array)$r->files->all('galleryFiles') as $gFile) {
                if (!$gFile instanceof UploadedFile || !$gFile->isValid()) { continue; }
                $img = new \App\Entity\PageBlockImage();
                $img->setBlock($block);
                $img->setFile($gFile);
                $img->setPosition($block->getGalleryImages()->count());
                $em->persist($img);
            }
        }

        // ── Testimonials ─────────────────────────────────────────────────────
        if ($type === 'testimonials') {
            $delIds = array_filter(array_map('intval', (array)$r->request->all('delete_testimonial')));
            foreach ($block->getTestimonials() as $t) {
                if (in_array($t->getId(), $delIds, true)) { $em->remove($t); }
            }
            $items = $r->request->all('testimonials');
            $files = $r->files->all('testimonials');
            foreach ($items as $idx => $data) {
                if (!empty($data['id'])) {
                    foreach ($block->getTestimonials() as $t) {
                        if ($t->getId() === (int)$data['id'] && !in_array($t->getId(), $delIds, true)) {
                            $t->setName($data['name'] ?? '');
                            $t->setRole($data['role'] ?? null);
                            $t->setText($data['text'] ?? '');
                            $t->setRating((int)($data['rating'] ?? 5));
                            if (isset($files[$idx]['avatarFile']) && $files[$idx]['avatarFile'] instanceof UploadedFile && $files[$idx]['avatarFile']->isValid()) {
                                $t->setAvatarFile($files[$idx]['avatarFile']);
                            }
                        }
                    }
                } else {
                    $t = new \App\Entity\PageBlockTestimonial();
                    $t->setBlock($block);
                    $t->setName($data['name'] ?? '');
                    $t->setRole($data['role'] ?? null);
                    $t->setText($data['text'] ?? '');
                    $t->setRating((int)($data['rating'] ?? 5));
                    $t->setPosition($idx);
                    if (isset($files[$idx]['avatarFile']) && $files[$idx]['avatarFile'] instanceof UploadedFile && $files[$idx]['avatarFile']->isValid()) {
                        $t->setAvatarFile($files[$idx]['avatarFile']);
                    }
                    $em->persist($t);
                }
            }
        }

        // ── Partner logos ────────────────────────────────────────────────────
        if ($type === 'partner_logos') {
            $delIds = array_filter(array_map('intval', (array)$r->request->all('delete_logo')));
            foreach ($block->getPartnerLogos() as $l) {
                if (in_array($l->getId(), $delIds, true)) { $em->remove($l); }
            }
            $logos     = $r->request->all('logos');
            $logoFiles = $r->files->all('logos');
            foreach ($logos as $idx => $data) {
                if (!empty($data['id'])) {
                    foreach ($block->getPartnerLogos() as $l) {
                        if ($l->getId() === (int)$data['id'] && !in_array($l->getId(), $delIds, true)) {
                            $l->setName($data['name'] ?? null);
                            $l->setUrl($data['url'] ?? null);
                            if (isset($logoFiles[$idx]['logoFile']) && $logoFiles[$idx]['logoFile'] instanceof UploadedFile && $logoFiles[$idx]['logoFile']->isValid()) {
                                $l->setLogoFile($logoFiles[$idx]['logoFile']);
                            }
                        }
                    }
                }
            }
            // Bulk upload
            foreach ((array)$r->files->all('logoFiles') as $lf) {
                if (!$lf instanceof UploadedFile || !$lf->isValid()) { continue; }
                $l = new \App\Entity\PageBlockPartnerLogo();
                $l->setBlock($block);
                $l->setLogoFile($lf);
                $l->setPosition($block->getPartnerLogos()->count());
                $em->persist($l);
            }
        }

        // ── Team members ─────────────────────────────────────────────────────
        if ($type === 'team') {
            $delIds = array_filter(array_map('intval', (array)$r->request->all('delete_member')));
            foreach ($block->getTeamMembers() as $m) {
                if (in_array($m->getId(), $delIds, true)) { $em->remove($m); }
            }
            $items = $r->request->all('team') ?: [];
            $files = $r->files->all('team') ?: [];
            foreach ($items as $idx => $data) {
                if (!empty($data['id'])) {
                    foreach ($block->getTeamMembers() as $m) {
                        if ($m->getId() === (int)$data['id'] && !in_array($m->getId(), $delIds, true)) {
                            $m->setName($data['name'] ?? '');
                            $m->setRole($data['role'] ?? null);
                            $m->setBio($data['bio'] ?? null);
                            $m->setLinkedinUrl($data['linkedinUrl'] ?? null);
                            $m->setFacebookUrl($data['facebookUrl'] ?? null);
                            $m->setInstagramUrl($data['instagramUrl'] ?? null);
                            $m->setWhatsappUrl($data['whatsappUrl'] ?? null);
                            $m->setPhone($data['phone'] ?? null);
                            $m->setEmail($data['email'] ?? null);
                            $m->setPosition($idx);
                            if (isset($files[$idx]['imageFile']) && $files[$idx]['imageFile'] instanceof UploadedFile && $files[$idx]['imageFile']->isValid()) {
                                $m->setImageFile($files[$idx]['imageFile']);
                            }
                        }
                    }
                } else {
                    $m = new \App\Entity\PageBlockTeamMember();
                    $m->setBlock($block);
                    $m->setName($data['name'] ?? '');
                    $m->setRole($data['role'] ?? null);
                    $m->setBio($data['bio'] ?? null);
                    $m->setLinkedinUrl($data['linkedinUrl'] ?? null);
                    $m->setFacebookUrl($data['facebookUrl'] ?? null);
                    $m->setInstagramUrl($data['instagramUrl'] ?? null);
                    $m->setWhatsappUrl($data['whatsappUrl'] ?? null);
                    $m->setPhone($data['phone'] ?? null);
                    $m->setEmail($data['email'] ?? null);
                    $m->setPosition($idx);
                    if (isset($files[$idx]['imageFile']) && $files[$idx]['imageFile'] instanceof UploadedFile && $files[$idx]['imageFile']->isValid()) {
                        $m->setImageFile($files[$idx]['imageFile']);
                    }
                    $em->persist($m);
                }
            }
        }

        // ── Banner (multi-slide via JSON config) ─────────────────────────────
        if ($type === 'banner') {
            $banners        = $r->request->all('banners') ?: [];
            $files          = $r->files->all('banners') ?: [];
            $savedBanners   = [];
            $existingBanners = $block->getConfig()['banners'] ?? [];

            foreach ($banners as $idx => $data) {
                $slideImage = $data['image'] ?? null;

                if (isset($files[$idx]['imageFile']) && $files[$idx]['imageFile'] instanceof UploadedFile && $files[$idx]['imageFile']->isValid()) {
                    $uploadedFile = $files[$idx]['imageFile'];
                    $extension    = $uploadedFile->guessExtension() ?: 'bin';
                    $newFilename  = uniqid('banner_', true) . '.' . $extension;
                    $targetDir    = $this->getParameter('kernel.project_dir') . '/public/uploads/page_block';
                    $uploadedFile->move($targetDir, $newFilename);
                    $slideImage = $newFilename;
                }

                if (empty($slideImage) && isset($existingBanners[$idx]['image'])) {
                    $slideImage = $existingBanners[$idx]['image'];
                }

                $savedBanners[] = [
                    'title'   => $data['title'] ?? '',
                    'text'    => $data['text'] ?? '',
                    'ctaText' => $data['ctaText'] ?? '',
                    'ctaLink' => $data['ctaLink'] ?? '',
                    'active'  => (isset($data['active']) && $data['active'] === '1') ? '1' : '0',
                    'image'   => $slideImage,
                ];
            }

            $cfg = $block->getConfig() ?: [];
            $cfg['banners'] = $savedBanners;
            // Preserve other config keys (not overwritten by banners key)
            $reqCfg = $r->request->all('config') ?: [];
            foreach ($reqCfg as $k => $v) {
                if ($k !== 'banners') { $cfg[$k] = $v; }
            }
            $block->setConfig($cfg);

            // Set primary image/title from first active slide for search/display purposes
            $firstSlide = null;
            foreach ($savedBanners as $b) {
                if (($b['active'] ?? '0') === '1') { $firstSlide = $b; break; }
            }
            if (!$firstSlide && !empty($savedBanners)) { $firstSlide = $savedBanners[0]; }

            if ($firstSlide) {
                $block->setTitle($firstSlide['title'] ?? null);
                $block->setText($firstSlide['text'] ?? null);
                $block->setImage($firstSlide['image'] ?? null);
            } else {
                $block->setTitle(null);
                $block->setText(null);
                $block->setImage(null);
            }
        }
    }

    #[Route('/block/{id}/delete', name: 'block_delete', methods: ['POST'])]
    public function blockDelete(PageBlock $block, Request $r, EntityManagerInterface $em): Response
    {
        $sectionId = $block->getSection()?->getId();
        if ($this->isCsrfTokenValid('del_block_' . $block->getId(), (string) $r->request->get('_token'))) {
            $em->remove($block);
            $em->flush();
        }
        return $this->redirectToRoute('admin_block_index', ['sectionId' => $sectionId]);
    }

    #[Route('/block/reorder', name: 'block_reorder', methods: ['POST'])]
    public function blockReorder(Request $r, PageBlockRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        return $this->reorderEntities($r, $repo, $em);
    }

    // ── Editor Users (managed by Tenant Admin) ────────────────────────────────

    #[Route('/editors', name: 'editor_index')]
    public function editorIndex(UserRepository $users): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        return $this->render('admin/editor/index.html.twig', [
            'editors' => $users->findBy(['tenant' => $me->getTenant(), 'workGroup' => [1, 2]]),
        ]);
    }

    #[Route('/editors/new', name: 'editor_new', methods: ['GET', 'POST'])]
    public function editorNew(Request $r, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $editor = new User();
        if ($r->isMethod('POST')) {
            $editor->setUsername((string) $r->request->get('username'));
            $editor->setName((string) $r->request->get('name'));
            $editor->setEmail($r->request->get('email') ?: null);
            $editor->setWorkGroup((int) $r->request->get('workGroup', 1));
            $editor->setTenant($me->getTenant());
            $editor->setPassword($hasher->hashPassword($editor, (string) $r->request->get('password')));
            $em->persist($editor);
            $em->flush();
            $this->addFlash('success', 'Usuário criado.');
            return $this->redirectToRoute('admin_editor_index');
        }
        return $this->render('admin/editor/new.html.twig', ['editor' => $editor]);
    }

    #[Route('/editors/{id}/delete', name: 'editor_delete', methods: ['POST'])]
    public function editorDelete(User $editor, Request $r, EntityManagerInterface $em): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        if ($editor->getTenant() === $me->getTenant()) {
            if ($this->isCsrfTokenValid('del_editor_' . $editor->getId(), (string) $r->request->get('_token'))) {
                $em->remove($editor);
                $em->flush();
            }
        }
        return $this->redirectToRoute('admin_editor_index');
    }

    // ── Tenant Settings ───────────────────────────────────────────────────────

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(Request $r, EntityManagerInterface $em, TenantContext $tc): Response
    {
        $tenant = $tc->requireTenant();
        if ($r->isMethod('POST')) {
            $tenant->setAboutText($r->request->get('aboutText') ?: null);
            $tenant->setAboutFullText($r->request->get('aboutFullText') ?: null);
            $tenant->setContactEmail($r->request->get('contactEmail') ?: null);
            $tenant->setPhone($r->request->get('phone') ?: null);
            $tenant->setAddress($r->request->get('address') ?: null);
            $tenant->setMapsEmbedUrl($r->request->get('mapsEmbedUrl') ?: null);
            $tenant->setYoutubeLink($r->request->get('youtubeLink') ?: null);
            $tenant->setInstagramLink($r->request->get('instagramLink') ?: null);
            $tenant->setFacebookLink($r->request->get('facebookLink') ?: null);
            $tenant->setWhatsappLink($r->request->get('whatsappLink') ?: null);
            $tenant->setLinkedinLink($r->request->get('linkedinLink') ?: null);
            $tenant->setRequiredApprovals((int) $r->request->get('requiredApprovals', 1));
            $file = $r->files->get('aboutImageFile');
            if ($file instanceof UploadedFile) { $tenant->setAboutImageFile($file); }
            $em->flush();
            $this->addFlash('success', 'Configurações salvas.');
            return $this->redirectToRoute('admin_settings');
        }
        return $this->render('admin/settings.html.twig', ['tenant' => $tenant]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reorderEntities(Request $r, $repo, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode((string) $r->getContent(), true);
        $ids = $data['ids'] ?? [];
        foreach ($ids as $position => $id) {
            $entity = $repo->find((int) $id);
            if ($entity && method_exists($entity, 'setPosition')) {
                $entity->setPosition($position);
            }
        }
        $em->flush();
        return new JsonResponse(['ok' => true]);
    }

    private function populateCategory(Category $cat, Request $r, SluggerInterface $slugger): void
    {
        $cat->setName((string) $r->request->get('name'));
        $cat->setSlug($r->request->get('slug') ?: strtolower((string) $slugger->slug($cat->getName())));
        $cat->setPreTitle($r->request->get('preTitle') ?: null);
        $cat->setDescription($r->request->get('description') ?: null);
        $cat->setShowInHeader((bool) $r->request->get('showInHeader'));
        $cat->setShowInFooter((bool) $r->request->get('showInFooter'));
    }

    private function populatePage(Page $page, Request $r, SluggerInterface $slugger): void
    {
        $page->setTitle((string) $r->request->get('title'));
        $page->setSlug($r->request->get('slug') ?: strtolower((string) $slugger->slug($page->getTitle())));
        $page->setShowInHeader((bool) $r->request->get('showInHeader'));
        $page->setShowInFooter((bool) $r->request->get('showInFooter'));
        $page->setSeoTitle($r->request->get('seoTitle') ?: null);
        $page->setSeoDescription($r->request->get('seoDescription') ?: null);
    }

    private function populateVideo(VideoSupport $video, Request $r, SluggerInterface $slugger, EntityManagerInterface $em): void
    {
        $video->setTitle((string) $r->request->get('title'));
        $video->setSlug($r->request->get('slug') ?: strtolower((string) $slugger->slug($video->getTitle())));
        $video->setYoutubeId((string) $r->request->get('youtubeId'));
        $video->setDescription($r->request->get('description') ?: null);
        $video->setMaterialsHtml($r->request->get('materialsHtml') ?: null);

        // ── Custom Thumbnail ──────────────────────────────────────────────────
        $thumbFile = $r->files->get('customThumbnailFile');
        if ($thumbFile instanceof UploadedFile) {
            $video->setCustomThumbnailFile($thumbFile);
        } elseif ($r->request->getBoolean('remove_custom_thumbnail')) {
            $video->setCustomThumbnail(null);
            $video->setCustomThumbnailFile(null);
        }

        // ── Category ──────────────────────────────────────────────────────────
        $catId = (int) $r->request->get('category');
        $category = $catId ? $em->getReference(\App\Entity\Category::class, $catId) : null;
        $video->setCategory($category);

        // ── Delete removed materials ──────────────────────────────────────────
        $deleteIds = array_filter(array_map('intval', (array) $r->request->all('delete_material')));
        foreach ($video->getMaterials() as $mat) {
            if (in_array($mat->getId(), $deleteIds, true)) {
                $em->remove($mat);
            }
        }

        // ── Add new uploaded materials ────────────────────────────────────────
        $files  = $r->files->all('material_file');
        $labels = $r->request->all('material_label');
        foreach ($files as $idx => $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFile) { continue; }
            $label = trim((string) ($labels[$idx] ?? ''));
            if ($label === '') { $label = $uploadedFile->getClientOriginalName(); }
            $mat = new VideoMaterial();
            $mat->setVideo($video);
            $mat->setLabel($label);
            $mat->setExtension(strtolower($uploadedFile->getClientOriginalExtension()));
            $mat->setFile($uploadedFile);
            $em->persist($mat);
        }
    }

    private function populateStudy(Study $study, Request $r, SluggerInterface $slugger, EntityManagerInterface $em): void
    {
        $study->setTitle((string) $r->request->get('title'));
        $study->setSlug($r->request->get('slug') ?: strtolower((string) $slugger->slug($study->getTitle())));
        $study->setDescription($r->request->get('description') ?: null);
        $study->setMaterialsHtml($r->request->get('materialsHtml') ?: null);
        $study->setActive((bool) $r->request->get('active'));

        // ── Cover image ───────────────────────────────────────────────────────
        $coverFile = $r->files->get('coverImageFile');
        if ($coverFile instanceof UploadedFile) {
            $study->setCoverImageFile($coverFile);
        }

        // ── Category ──────────────────────────────────────────────────────────
        $catId = (int) $r->request->get('category');
        $category = $catId ? $em->getReference(\App\Entity\Category::class, $catId) : null;
        $study->setCategory($category);

        // ── Delete removed materials ──────────────────────────────────────────
        $deleteIds = array_filter(array_map('intval', (array) $r->request->all('delete_material')));
        foreach ($study->getMaterials() as $mat) {
            if (in_array($mat->getId(), $deleteIds, true)) {
                $em->remove($mat);
            }
        }

        // ── Add new uploaded materials ────────────────────────────────────────
        $files  = $r->files->all('material_file');
        $labels = $r->request->all('material_label');
        foreach ($files as $idx => $uploadedFile) {
            if (!$uploadedFile instanceof UploadedFile) { continue; }
            $label = trim((string) ($labels[$idx] ?? ''));
            if ($label === '') { $label = $uploadedFile->getClientOriginalName(); }
            $mat = new StudyMaterial();
            $mat->setStudy($study);
            $mat->setLabel($label);
            $mat->setExtension(strtolower($uploadedFile->getClientOriginalExtension()));
            $mat->setFile($uploadedFile);
            $em->persist($mat);
        }
    }
}
