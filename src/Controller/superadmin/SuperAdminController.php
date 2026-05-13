<?php

namespace App\Controller\superadmin;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/superadmin', name: 'superadmin_')]
class SuperAdminController extends AbstractController
{
    #[Route('', name: 'dash')]
    public function dashboard(TenantRepository $tenants, UserRepository $users): Response
    {
        return $this->render('superadmin/dashboard.html.twig', [
            'tenants' => $tenants->findAll(),
            'users'   => $users->findAll(),
        ]);
    }

    // ── Tenant CRUD ─────────────────────────────────────────────────────────

    #[Route('/tenant/new', name: 'tenant_new', methods: ['GET', 'POST'])]
    public function tenantNew(Request $request, EntityManagerInterface $em): Response
    {
        $tenant = new Tenant();
        if ($request->isMethod('POST')) {
            $this->populateTenantFromRequest($tenant, $request);
            $em->persist($tenant);
            $em->flush();
            $this->addFlash('success', 'Tenant criado com sucesso.');
            return $this->redirectToRoute('superadmin_dash');
        }
        return $this->render('superadmin/tenant/new.html.twig', ['tenant' => $tenant]);
    }

    #[Route('/tenant/{id}/edit', name: 'tenant_edit', methods: ['GET', 'POST'])]
    public function tenantEdit(Tenant $tenant, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $this->populateTenantFromRequest($tenant, $request);

            /** @var UploadedFile|null $logoFile */
            $logoFile = $request->files->get('logoFile');
            if ($logoFile instanceof UploadedFile) {
                $tenant->setLogoFile($logoFile);
            }

            $em->flush();
            $this->addFlash('success', 'Tenant atualizado.');
            return $this->redirectToRoute('superadmin_dash');
        }
        return $this->render('superadmin/tenant/edit.html.twig', ['tenant' => $tenant]);
    }

    #[Route('/tenant/{id}/delete', name: 'tenant_delete', methods: ['POST'])]
    public function tenantDelete(Tenant $tenant, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_tenant_' . $tenant->getId(), (string) $request->request->get('_token'))) {
            $em->remove($tenant);
            $em->flush();
            $this->addFlash('success', 'Tenant removido.');
        }
        return $this->redirectToRoute('superadmin_dash');
    }

    // ── User (Admin) CRUD ────────────────────────────────────────────────────

    #[Route('/user/new', name: 'user_new', methods: ['GET', 'POST'])]
    public function userNew(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        TenantRepository $tenants,
    ): Response {
        $user = new User();
        if ($request->isMethod('POST')) {
            $this->populateUserFromRequest($user, $request, $em, $hasher, $tenants);
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Usuário criado.');
            return $this->redirectToRoute('superadmin_dash');
        }
        return $this->render('superadmin/user/new.html.twig', [
            'user'    => $user,
            'tenants' => $tenants->findAll(),
        ]);
    }

    #[Route('/user/{id}/edit', name: 'user_edit', methods: ['GET', 'POST'])]
    public function userEdit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        TenantRepository $tenants,
    ): Response {
        if ($request->isMethod('POST')) {
            $this->populateUserFromRequest($user, $request, $em, $hasher, $tenants);
            $em->flush();
            $this->addFlash('success', 'Usuário atualizado.');
            return $this->redirectToRoute('superadmin_dash');
        }
        return $this->render('superadmin/user/edit.html.twig', [
            'user'    => $user,
            'tenants' => $tenants->findAll(),
        ]);
    }

    #[Route('/user/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('del_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Usuário removido.');
        }
        return $this->redirectToRoute('superadmin_dash');
    }

    // ── Impersonation helper ─────────────────────────────────────────────────

    /**
     * Redirects to /admin with ?_switch_user=<username> to impersonate the
     * first admin of a given tenant. Symfony Security handles the rest.
     */
    #[Route('/impersonate/{id}', name: 'impersonate', methods: ['GET'])]
    public function impersonate(User $user): Response
    {
        return $this->redirect('/admin?_switch_user=' . urlencode((string) $user->getUsername()));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function populateTenantFromRequest(Tenant $tenant, Request $r): void
    {
        $tenant->setDomain((string) $r->request->get('domain'));
        $tenant->setName((string) $r->request->get('name'));
        $tenant->setPrimaryColor((string) $r->request->get('primaryColor', '#0044cc'));
        $tenant->setSecondaryColor((string) $r->request->get('secondaryColor', '#ffaa00'));
        $tenant->setContactEmail($r->request->get('contactEmail') ?: null);
        $tenant->setYoutubeLink($r->request->get('youtubeLink') ?: null);
        $tenant->setInstagramLink($r->request->get('instagramLink') ?: null);
        $tenant->setRequiredApprovals((int) $r->request->get('requiredApprovals', 1));
        $tenant->setTheme((string) $r->request->get('theme', 'nepe'));
    }

    private function populateUserFromRequest(
        User $user,
        Request $r,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        TenantRepository $tenants,
    ): void {
        $user->setUsername((string) $r->request->get('username'));
        $user->setName((string) $r->request->get('name'));
        $user->setEmail($r->request->get('email') ?: null);
        $user->setWorkGroup((int) $r->request->get('workGroup', 0));

        $tenantId = $r->request->get('tenant');
        $user->setTenant($tenantId ? $tenants->find((int) $tenantId) : null);

        $plain = (string) $r->request->get('password');
        if ($plain !== '') {
            $user->setPassword($hasher->hashPassword($user, $plain));
        }
    }
}
