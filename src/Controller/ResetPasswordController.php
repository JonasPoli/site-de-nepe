<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ExpiredResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 4096;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly PasswordResetMailer $passwordResetMailer,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', (string) $request->request->get('_token'))) {
                $error = 'A sessão expirou. Tente novamente.';
            } else {
                $email = trim((string) $request->request->get('email'));
                $user = $email !== '' ? $users->findOneBy(['email' => $email]) : null;

                if ($user instanceof User) {
                    try {
                        $this->passwordResetMailer->send($user, $request->getSchemeAndHttpHost());
                    } catch (TooManyPasswordRequestsException) {
                        // Um link acabou de ser enviado para este usuário: não envia outro
                    } catch (TransportExceptionInterface $e) {
                        $this->logger->error('Falha ao enviar e-mail de redefinição de senha: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }

                // Mesma resposta exista ou não a conta, para não revelar quais e-mails estão cadastrados
                return $this->redirectToRoute('app_check_email');
            }
        }

        return $this->render('security/forgot_password.html.twig', ['error' => $error]);
    }

    #[Route('/check-email', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('security/check_email.html.twig', [
            'lifetime_label' => PasswordResetMailer::describeLifetime($this->resetPasswordHelper->getTokenLifetime()),
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, ?string $token = null): Response
    {
        if ($token) {
            // Tira o token da URL (evita vazamento via Referer) e guarda na sessão
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if ($token === null) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', $e instanceof ExpiredResetPasswordTokenException
                ? 'Este link expirou. Peça um novo abaixo.'
                : 'Este link é inválido ou já foi usado. Peça um novo abaixo.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');

            if (!$this->isCsrfTokenValid('reset_password', (string) $request->request->get('_token'))) {
                $error = 'A sessão expirou. Tente novamente.';
            } elseif (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $error = sprintf('A senha precisa ter pelo menos %d caracteres.', self::MIN_PASSWORD_LENGTH);
            } elseif (strlen($password) > self::MAX_PASSWORD_LENGTH) {
                $error = 'A senha é longa demais.';
            } elseif ($password !== (string) $request->request->get('password_confirmation')) {
                $error = 'As senhas não conferem.';
            } else {
                // O link só pode ser usado uma vez
                $this->resetPasswordHelper->removeResetRequest($token);

                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $em->flush();

                $this->cleanSessionAfterReset();
                $this->addFlash('login_success', 'Senha definida! Agora é só entrar com seu usuário e a nova senha.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'user'       => $user,
            'error'      => $error,
            'min_length' => self::MIN_PASSWORD_LENGTH,
        ]);
    }
}
