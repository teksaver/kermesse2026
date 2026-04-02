<?php

namespace App\Service;

use App\Entity\LoginToken;
use App\Entity\User;
use App\Repository\LoginTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LoginLinkManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginTokenRepository $loginTokenRepository,
        private readonly LoginLinkMailer $loginLinkMailer,
        #[Autowire('%env(int:AUTH_LOGIN_LINK_TTL)%')] private readonly int $loginLinkTtl,
    ) {
    }

    public function createAndSend(User $user, string $purpose): void
    {
        $now = new \DateTimeImmutable();
        $selector = bin2hex(random_bytes(16));
        $plainToken = bin2hex(random_bytes(32));

        $this->loginTokenRepository->invalidateOpenTokensForUser($user, $now);

        $loginToken = (new LoginToken())
            ->setUser($user)
            ->setSelector($selector)
            ->setTokenHash(hash('sha256', $plainToken))
            ->setPurpose($purpose)
            ->setExpiresAt($now->modify(sprintf('+%d seconds', $this->loginLinkTtl)));

        $this->entityManager->persist($loginToken);
        $this->entityManager->flush();

        $this->loginLinkMailer->send($loginToken, $plainToken);
    }
}
