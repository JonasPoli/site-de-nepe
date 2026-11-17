<?php

namespace App\Controller\admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use App\Security\ProfilePolicy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Perfil do próprio usuário (/admin/perfil).
 *
 * Aberto a todo papel que entra no painel (ROLE_ADMIN: administrador do NEPE, editor, revisor
 * e super admin). O alvo é sempre o usuário da sessão: não existe id na rota nem leitura de
 * usuário no request, e workGroup e tenant nunca são gravados aqui — quem mexe no papel de
 * alguém é o administrador do NEPE (/admin/editors) ou o super admin (/superadmin).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/perfil', name: 'admin_profile')]
class ProfileController extends AbstractController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        StorageInterface $storage,
        Security $security,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$request->isMethod('POST')) {
            return $this->render('admin/profile/edit.html.twig', ['usuario' => $user]);
        }

        if (!$this->isCsrfTokenValid('profile', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'A sessão expirou. Abra o perfil de novo e salve outra vez.');

            return $this->redirectToRoute('admin_profile');
        }

        $name     = trim((string) $request->request->get('name'));
        $username = trim((string) $request->request->get('username'));
        $email    = ProfilePolicy::normalizeOptional($request->request->get('email'));
        $whatsapp = ProfilePolicy::normalizeWhatsapp($request->request->get('whatsapp'));
        $password = (string) $request->request->get('password');
        $confirm  = (string) $request->request->get('password_confirmation');
        $photo    = $request->files->get('photoFile');
        $removePhoto = $request->request->get('remove_photo') === '1';

        // Tudo é conferido antes de qualquer gravação: um erro deixa o cadastro como estava
        $errors = [];

        if ($name === '') {
            $errors[] = 'O nome é obrigatório.';
        }

        if ($username === '') {
            $errors[] = 'O usuário de login é obrigatório.';
        } elseif ($this->isTaken($users, 'username', $username, $user)) {
            $errors[] = 'Esse usuário de login já está em uso.';
        }

        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'O e-mail informado não é válido.';
            } elseif ($this->isTaken($users, 'email', $email, $user)) {
                $errors[] = 'Esse e-mail já está em uso por outro usuário.';
            }
        }

        $passwordChanged = $password !== '' || $confirm !== '';
        if ($passwordChanged) {
            if ($password !== $confirm) {
                $errors[] = 'As duas senhas não são iguais.';
            } elseif ($message = PasswordPolicy::errorMessage($password)) {
                $errors[] = $message;
            }
        }

        if ($photo instanceof UploadedFile) {
            if (!$photo->isValid()) {
                $errors[] = 'O envio da foto falhou. Tente de novo.';
            } elseif ($message = ProfilePolicy::photoError($photo->getMimeType(), $photo->getSize())) {
                $errors[] = $message;
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                $this->addFlash('error', $message);
            }

            return $this->render('admin/profile/edit.html.twig', [
                'usuario'  => $user,
                'digitado' => ['name' => $name, 'username' => $username, 'email' => $email, 'whatsapp' => $whatsapp],
            ]);
        }

        $usernameAntes = $user->getUsername();

        $user->setName($name);
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setWhatsapp($whatsapp);

        if ($photo instanceof UploadedFile) {
            $user->setPhotoFile($photo);
        } elseif ($removePhoto && $user->getPhoto() !== null) {
            $this->deletePhotoFile($storage, $user);
            $user->setPhoto(null);
            $user->setPhotoUpdatedAt(new \DateTimeImmutable());
        }

        if ($passwordChanged) {
            $user->setPassword($hasher->hashPassword($user, $password));
        }

        try {
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            // Corrida com outro cadastro gravado entre a conferência e o flush
            $em->clear();
            $this->addFlash('error', 'Esse usuário de login ou e-mail acabou de ser usado por outro usuário. Tente outro.');

            return $this->redirectToRoute('admin_profile');
        }

        if (ProfilePolicy::requiresNewLogin($usernameAntes, $username, $passwordChanged)) {
            // Sem isto o Symfony derrubaria a sessão sozinho na requisição seguinte, levando a um
            // /login sem explicação: o logout aqui é o que permite dar o aviso.
            $security->logout(false);
            // 'login_success' é o tipo que a tela de login mostra
            $this->addFlash('login_success', sprintf(
                'Perfil salvo. Entre de novo com o usuário %s e a senha atual.',
                $username
            ));

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Perfil salvo.');

        return $this->redirectToRoute('admin_profile');
    }

    /**
     * Usuário de login e e-mail são únicos no sistema todo (um e-mail pertence a um só usuário):
     * a busca não se limita ao tenant do domínio.
     */
    private function isTaken(UserRepository $users, string $field, string $value, User $me): bool
    {
        $found = $users->findOneBy([$field => $value]);

        return $found !== null && $found->getId() !== $me->getId();
    }

    private function deletePhotoFile(StorageInterface $storage, User $user): void
    {
        $path = $storage->resolvePath($user, 'photoFile');
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
