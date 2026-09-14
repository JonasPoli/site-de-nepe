<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Sends the e-mail with the link to define a new password, branded with the
 * user's tenant. Used by the "Esqueci minha senha" page and by app:tenant:import.
 */
class PasswordResetMailer
{
    /** Imported admins may take a few days to open the welcome e-mail */
    public const WELCOME_LINK_LIFETIME = 7 * 86400;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'emailFrom')]
        private readonly string $emailFrom,
    ) {}

    /**
     * @param string $baseUrl Scheme and host the link must point to, e.g. "https://nepe.org.br"
     * @param bool   $welcome First access of an imported admin: longer link lifetime and different wording
     *
     * @throws TooManyPasswordRequestsException when a link was sent to this user moments ago
     * @throws TransportExceptionInterface
     */
    public function send(User $user, string $baseUrl, bool $welcome = false): void
    {
        if (!$user->getEmail()) {
            throw new \LogicException(sprintf('User "%s" has no e-mail.', $user->getUserIdentifier()));
        }

        $lifetime = $welcome ? self::WELCOME_LINK_LIFETIME : $this->resetPasswordHelper->getTokenLifetime();
        $token = $this->resetPasswordHelper->generateResetToken($user, $lifetime);

        $baseUrl = rtrim($baseUrl, '/');
        $tenant = $user->getTenant();
        $siteName = $tenant?->getName() ?? 'NEPE';

        $email = (new TemplatedEmail())
            ->from(new Address($this->emailFrom, $siteName))
            ->to(new Address($user->getEmail(), $user->getName()))
            ->subject($welcome ? sprintf('Seu acesso ao site %s', $siteName) : sprintf('Redefinição de senha — %s', $siteName))
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'tenant'         => $tenant,
                'base_url'       => $baseUrl,
                'user'           => $user,
                'welcome'        => $welcome,
                'reset_url'      => $baseUrl . $this->urlGenerator->generate('app_reset_password', ['token' => $token->getToken()]),
                'login_url'      => $baseUrl . $this->urlGenerator->generate('app_login'),
                'lifetime_label' => self::describeLifetime($lifetime),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // The link never reached the user: drop it so it doesn't throttle a new attempt
            $this->resetPasswordHelper->removeResetRequest($token->getToken());
            throw $e;
        }
    }

    public static function describeLifetime(int $seconds): string
    {
        if ($seconds >= 86400) {
            $days = intdiv($seconds, 86400);
            return $days . ($days === 1 ? ' dia' : ' dias');
        }

        if ($seconds >= 3600) {
            $hours = intdiv($seconds, 3600);
            return $hours . ($hours === 1 ? ' hora' : ' horas');
        }

        $minutes = max(1, intdiv($seconds, 60));
        return $minutes . ($minutes === 1 ? ' minuto' : ' minutos');
    }
}
