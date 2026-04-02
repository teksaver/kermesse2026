<?php

namespace App\Service;

use App\Entity\LoginToken;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginLinkMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $fromAddress,
    ) {
    }

    public function send(LoginToken $loginToken, string $plainToken): void
    {
        $user = $loginToken->getUser();
        if (null === $user?->getEmail()) {
            return;
        }

        $url = $this->urlGenerator->generate('app_auth_magic_link', [
            'selector' => $loginToken->getSelector(),
            'token' => $plainToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Kermesse 2026'))
            ->to($user->getEmail())
            ->subject('Votre lien de connexion Kermesse 2026')
            ->htmlTemplate('emails/login_link.html.twig')
            ->context([
                'user' => $user,
                'loginUrl' => $url,
                'expiresAt' => $loginToken->getExpiresAt(),
            ]);

        $this->mailer->send($email);
    }
}
