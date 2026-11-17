<?php

namespace App\Controller\superadmin;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Security\ProfilePolicy;
use App\Service\TenantUrl;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Storage\StorageInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/superadmin', name: 'superadmin_')]
class SuperAdminController extends AbstractController
{
    #[Route('', name: 'dash')]
    public function dashboard(Request $request, TenantRepository $tenants, UserRepository $users): Response
    {
        $busca = trim((string) $request->query->get('q', ''));

        $linhas = $tenants->findAllWithUserCount($busca !== '' ? $busca : null);
        $encontrados = $users->findAllForSuperAdmin($busca !== '' ? $busca : null);

        return $this->render('superadmin/dashboard.html.twig', [
            'busca'       => $busca,
            'tenantRows'  => $linhas,
            'userGroups'  => $this->groupByTenant($linhas, $encontrados),
            'totalTenants' => $busca === '' ? count($linhas) : $tenants->count([]),
            'totalUsers'  => $busca === '' ? count($encontrados) : $users->count([]),
        ]);
    }

    /**
     * Agrupa a lista já ordenada pelo banco: os usuários de um NEPE vêm seguidos,
     * então basta percorrer uma vez, sem nova consulta.
     *
     * Os grupos partem da lista de NEPEs, para que um NEPE sem nenhum usuário
     * também apareça — é assim que se percebe um NEPE que ficou sem administrador.
     * O grupo dos usuários sem NEPE fica por último, como vem da consulta.
     *
     * @param list<array{tenant: Tenant, users: int}> $tenantRows
     * @param list<User>                              $users
     *
     * @return list<array{tenant: ?Tenant, users: list<User>}>
     */
    private function groupByTenant(array $tenantRows, array $users): array
    {
        $grupos = [];

        foreach ($tenantRows as $linha) {
            $grupos[$linha['tenant']->getId()] = ['tenant' => $linha['tenant'], 'users' => []];
        }

        foreach ($users as $user) {
            $chave = $user->getTenant()?->getId() ?? 'sem-nepe';

            // Com busca, um usuário pode ser de um NEPE que o filtro de nome não trouxe
            if (!isset($grupos[$chave])) {
                $grupos[$chave] = ['tenant' => $user->getTenant(), 'users' => []];
            }

            $grupos[$chave]['users'][] = $user;
        }

        return array_values($grupos);
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

            /** @var UploadedFile|null $darkLogoFile */
            $darkLogoFile = $request->files->get('darkLogoFile');
            if ($darkLogoFile instanceof UploadedFile) {
                $tenant->setDarkLogoFile($darkLogoFile);
            }

            $em->flush();
            $this->addFlash('success', 'Tenant atualizado.');
            return $this->redirectToRoute('superadmin_dash');
        }
        return $this->render('superadmin/tenant/edit.html.twig', ['tenant' => $tenant]);
    }

    #[Route('/tenant/{id}/delete', name: 'tenant_delete', methods: ['POST'])]
    public function tenantDelete(Tenant $tenant, Request $request, EntityManagerInterface $em, UserRepository $users): Response
    {
        if ($this->isCsrfTokenValid('del_tenant_' . $tenant->getId(), (string) $request->request->get('_token'))) {
            // Os usuários saem junto: sem tenant, um admin viraria SuperAdmin (tenant null + workGroup 0)
            $tenantUsers = $users->findBy(['tenant' => $tenant]);
            foreach ($tenantUsers as $user) {
                $em->remove($user);
            }

            $em->remove($tenant);
            $em->flush();
            $this->addFlash('success', sprintf('Tenant removido, junto com %d usuário(s).', count($tenantUsers)));
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
            if ($senhaInvalida = $this->passwordError($request, required: true)) {
                $this->addFlash('error', $senhaInvalida);
                return $this->render('superadmin/user/new.html.twig', [
                    'user'    => $user,
                    'tenants' => $tenants->findAll(),
                ]);
            }
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
        StorageInterface $storage,
    ): Response {
        if ($request->isMethod('POST')) {
            // Senha em branco na edição continua significando "manter a senha atual"
            $erro = $this->passwordError($request, required: false) ?? $this->photoError($request);

            if ($erro !== null) {
                $this->addFlash('error', $erro);
                return $this->render('superadmin/user/edit.html.twig', [
                    'user'    => $user,
                    'tenants' => $tenants->findAll(),
                ]);
            }

            $this->applyPhoto($request, $user, $storage);
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
        return $this->redirect(self::impersonationUrl($user));
    }

    /**
     * O "entrar como" acontece no domínio do NEPE do usuário: o login e o painel só o aceitam lá.
     * Sem sessão naquele domínio, o firewall leva ao login e depois volta para este endereço.
     * Usuário sem tenant: no domínio atual.
     */
    public static function impersonationUrl(User $user): string
    {
        $tenant = $user->getTenant();
        $base = $tenant !== null ? TenantUrl::base($tenant) : '';

        return $base . '/admin?_switch_user=' . urlencode((string) $user->getUsername());
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

    /**
     * Confere a senha enviada contra a PasswordPolicy, a mesma regra do perfil, da criação
     * de usuário pelo NEPE e da redefinição por e-mail. Devolve o aviso ou null.
     */
    private function passwordError(Request $r, bool $required): ?string
    {
        $password = (string) $r->request->get('password');
        $confirmation = (string) $r->request->get('password_confirmation');

        if (!$required && $password === '' && $confirmation === '') {
            return null;
        }

        if ($password !== $confirmation) {
            return 'As duas senhas não são iguais.';
        }

        return PasswordPolicy::errorMessage($password);
    }

    /**
     * Confere a foto enviada antes de gravar qualquer coisa, pela mesma regra do
     * perfil do próprio usuário (JPEG, PNG ou WebP até 4 MB).
     */
    private function photoError(Request $r): ?string
    {
        $photo = $r->files->get('photoFile');

        if (!$photo instanceof UploadedFile) {
            return null;
        }

        if (!$photo->isValid()) {
            return 'O envio da foto falhou. Tente de novo.';
        }

        return ProfilePolicy::photoError($photo->getMimeType(), $photo->getSize());
    }

    /** Grava a foto nova ou apaga a atual; só é chamado depois de photoError() passar */
    private function applyPhoto(Request $r, User $user, StorageInterface $storage): void
    {
        $photo = $r->files->get('photoFile');

        if ($photo instanceof UploadedFile) {
            $user->setPhotoFile($photo);

            return;
        }

        if ($r->request->get('remove_photo') === '1' && $user->getPhoto() !== null) {
            // O Vich apaga ao trocar o arquivo, mas não quando o campo é zerado à mão
            $caminho = $storage->resolvePath($user, 'photoFile');

            if (is_string($caminho) && is_file($caminho)) {
                unlink($caminho);
            }

            $user->setPhoto(null);
            $user->setPhotoUpdatedAt(new \DateTimeImmutable());
        }
    }

    private function populateUserFromRequest(
        User $user,
        Request $r,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        TenantRepository $tenants,
    ): void {
        $tenantId = $r->request->get('tenant');
        $tenant = $tenantId ? $tenants->find((int) $tenantId) : null;
        if ($tenantId && $tenant === null) {
            // Tenant removido enquanto o formulário estava aberto: gravar null tornaria o usuário SuperAdmin
            throw $this->createNotFoundException(sprintf('Tenant %s não encontrado.', $tenantId));
        }

        $user->setUsername((string) $r->request->get('username'));
        $user->setName((string) $r->request->get('name'));
        $user->setEmail($r->request->get('email') ?: null);
        $user->setWorkGroup((int) $r->request->get('workGroup', 0));
        $user->setTenant($tenant);

        $plain = (string) $r->request->get('password');
        if ($plain !== '') {
            $user->setPassword($hasher->hashPassword($user, $plain));
        }
    }
}
