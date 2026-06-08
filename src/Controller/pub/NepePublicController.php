<?php

namespace App\Controller\pub;

use App\Entity\ContactMessage;
use App\Entity\NewsletterSubscriber;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\HeroBannerRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\PageRepository;
use App\Repository\ResearchLineRepository;
use App\Repository\StudyRepository;
use App\Repository\VideoSupportRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class NepePublicController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {}

    private function theme(string $template): string
    {
        $tenant = $this->tenantContext->getTenant();
        $theme  = $tenant?->getTheme() ?? 'nepe';
        return "themes/{$theme}/{$template}";
    }

    #[Route('/', name: 'pub_home')]
    public function home(
        HeroBannerRepository $banners,
        ArticleRepository $articles,
        VideoSupportRepository $videos,
        ResearchLineRepository $lines,
        PageRepository $pages,
        StudyRepository $studies,
    ): Response {
        $activeBanners = $banners->findActiveAll();
        return $this->render($this->theme('home.html.twig'), [
            'banner'        => $activeBanners[0] ?? null,
            'banners'       => $activeBanners,
            'latestNews'    => $articles->findPublished(3),
            'featuredVideo' => $videos->findLatest(),
            'gallery'       => $videos->findGallery(1, 8),
            'lines'         => $lines->findBy([], ['position' => 'ASC']),
            'headerPages'   => $pages->findForHeader(),
            'latestStudies' => $studies->findGallery(0, 3),
        ]);
    }

    #[Route('/noticias', name: 'pub_articles')]
    public function articles(
        Request $request,
        ArticleRepository $repo, 
        PageRepository $pages,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $query = $repo->findPublishedQuery();
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 24);

        return $this->render($this->theme('articles.html.twig'), [
            'articles'    => $pagination,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/noticia/{slug}', name: 'pub_article_show')]
    public function articleShow(string $slug, ArticleRepository $repo, PageRepository $pages): Response
    {
        $article = $repo->findPublishedBySlug($slug) ?? throw $this->createNotFoundException();
        return $this->render($this->theme('article.html.twig'), [
            'article'     => $article,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/videos', name: 'pub_videos')]
    public function videos(
        Request $request,
        VideoSupportRepository $repo, 
        PageRepository $pages,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $query = $repo->findAllQuery();
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 24);

        return $this->render($this->theme('videos.html.twig'), [
            'videos'      => $pagination,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/video/{slug}', name: 'pub_video_show')]
    public function videoShow(string $slug, VideoSupportRepository $repo, PageRepository $pages): Response
    {
        $video = $repo->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        return $this->render($this->theme('video.html.twig'), [
            'video'       => $video,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/estudos', name: 'pub_studies')]
    public function studies(
        Request $request,
        StudyRepository $repo,
        PageRepository $pages,
        \Knp\Component\Pager\PaginatorInterface $paginator
    ): Response {
        $query = $repo->findAllQuery();
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 24);

        return $this->render($this->theme('studies.html.twig'), [
            'studies'     => $pagination,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/estudo/{slug}', name: 'pub_study_show')]
    public function studyShow(string $slug, StudyRepository $repo, PageRepository $pages): Response
    {
        $study = $repo->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        return $this->render($this->theme('study.html.twig'), [
            'study'       => $study,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/categoria/{slug}', name: 'pub_category_show')]
    public function categoryShow(
        string $slug,
        CategoryRepository $repo,
        ArticleRepository $articles,
        VideoSupportRepository $videos,
        StudyRepository $studies,
    ): Response {
        $category = $repo->findOneBy(['slug' => $slug])
            ?? throw $this->createNotFoundException('Categoria não encontrada.');

        return $this->render($this->theme('category.html.twig'), [
            'category'        => $category,
            'catArticles'     => $articles->findByCategory($category),
            'catVideos'       => $videos->findByCategory($category),
            'catStudies'      => $studies->findByCategory($category),
        ]);
    }

    #[Route('/pagina/{slug}', name: 'pub_page_show')]
    public function pageShow(string $slug, PageRepository $repo, PageRepository $pages): Response
    {
        $page = $repo->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        return $this->render($this->theme('page.html.twig'), [
            'page'        => $page,
            'headerPages' => $pages->findForHeader(),
        ]);
    }

    #[Route('/newsletter', name: 'pub_newsletter', methods: ['POST'])]
    public function newsletter(
        Request $request,
        EntityManagerInterface $em,
        NewsletterSubscriberRepository $repo,
    ): Response {
        $email  = trim((string) $request->request->get('email'));
        $name   = trim((string) $request->request->get('name'));
        $tenant = $this->tenantContext->requireTenant();

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && !$repo->emailExists($email, $tenant)) {
            $sub = new NewsletterSubscriber();
            $sub->setTenant($tenant);
            $sub->setEmail($email);
            $sub->setName($name);
            $em->persist($sub);
            $em->flush();
            $this->addFlash('newsletter_s', 'Inscrição realizada com sucesso!');
        } else {
            $this->addFlash('newsletter_f', 'E-mail inválido ou já inscrito.');
        }

        return $this->redirectToRoute('pub_home', ['_fragment' => 'newsletter']);
    }

    #[Route('/contato', name: 'pub_contact', methods: ['GET'])]
    public function contactGet(PageRepository $pages): Response
    {
        return $this->redirectToRoute('pub_home', ['_fragment' => 'contato']);
    }

    #[Route('/contato', name: 'pub_contact_post', methods: ['POST'])]
    public function contactPost(Request $request, EntityManagerInterface $em): Response
    {
        $redirect = $this->redirectToRoute('pub_home', ['_fragment' => 'contato']);

        if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token'))) {
            $this->addFlash('contact_f', 'Sessão expirada. Atualize a página e tente novamente.');
            return $redirect;
        }

        $name    = trim((string) $request->request->get('name'));
        $email   = trim((string) $request->request->get('email'));
        $message = trim((string) $request->request->get('message'));

        if (!$name || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('contact_f', 'Preencha todos os campos corretamente.');
            return $redirect;
        }

        $tenant = $this->tenantContext->requireTenant();

        $contact = new ContactMessage();
        $contact->setTenant($tenant);
        $contact->setSenderName($name);
        $contact->setSenderEmail($email);
        $contact->setMessage($message);
        $em->persist($contact);
        $em->flush();

        // Envia o e-mail de notificação imediatamente
        try {
            $baseUrl = $request->getSchemeAndHttpHost();

            $emailMessage = (new TemplatedEmail())
                ->from(new Address($this->params->get('emailFrom'), $tenant->getName()))
                ->to($tenant->getContactEmail() ?? $this->params->get('emailContactTo'))
                ->replyTo(new Address($email, $name))
                ->subject(sprintf('Nova mensagem de contato: %s', $name))
                ->htmlTemplate('email/contact.html.twig')
                ->context([
                    'tenant'       => $tenant,
                    'base_url'     => $baseUrl,
                    'name'         => $name,
                    'sender_email' => $email,
                    'phone'        => '—',
                    'message'      => $message,
                    'submitted_at' => new \DateTimeImmutable('now'),
                ]);

            $this->mailer->send($emailMessage);
        } catch (TransportExceptionInterface $e) {
            // Mensagem já salva no banco; falha no e-mail não deve bloquear o usuário
            $this->logger->error('Falha ao enviar e-mail de contato: ' . $e->getMessage(), ['exception' => $e]);
        }

        $this->addFlash('contact_s', 'Mensagem enviada com sucesso!');
        return $redirect;
    }
}
