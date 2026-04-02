<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\LoginTokenRepository;
use App\Repository\UserRepository;
use App\Service\LoginLinkManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/auth/register', name: 'app_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        LoginLinkManager $loginLinkManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire de creation de compte a expire. Veuillez reessayer.');

            return $this->redirectToRoute('app_homepage');
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de saisir une adresse e-mail valide.');

            return $this->redirectToRoute('app_homepage');
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();
        }

        $loginLinkManager->createAndSend($user, 'register');
        $this->addFlash('success', 'Si cette adresse peut etre utilisee, un lien de connexion a ete envoye.');

        return $this->redirectToRoute('app_homepage');
    }

    #[Route('/auth/login', name: 'app_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        LoginLinkManager $loginLinkManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('login', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire de connexion a expire. Veuillez reessayer.');

            return $this->redirectToRoute('app_homepage');
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $userRepository->findOneBy(['email' => $email]) : null;
        if ($user instanceof User) {
            $loginLinkManager->createAndSend($user, 'login');
        }

        $this->addFlash('success', 'Si un compte existe pour cette adresse, un lien de connexion a ete envoye.');

        return $this->redirectToRoute('app_homepage');
    }

    #[Route('/auth/magic/{selector}/{token}', name: 'app_auth_magic_link', methods: ['GET'])]
    public function consumeMagicLink(
        string $selector,
        string $token,
        LoginTokenRepository $loginTokenRepository,
        EntityManagerInterface $entityManager,
        Request $request,
    ): RedirectResponse {
        $now = new \DateTimeImmutable();
        $loginToken = $loginTokenRepository->findUsableBySelector($selector, $now);

        if (null === $loginToken || !hash_equals($loginToken->getTokenHash(), hash('sha256', $token))) {
            $request->attributes->set('auth.clear_cookies', true);
            $this->addFlash('error', 'Ce lien de connexion est invalide ou deja expire.');

            return $this->redirectToRoute('app_homepage');
        }

        $user = $loginToken->getUser();
        if (!$user instanceof User || null === $user->getId()) {
            $this->addFlash('error', 'Le compte associe a ce lien est introuvable.');

            return $this->redirectToRoute('app_homepage');
        }

        $loginToken->consume($now);
        $user
            ->setIsVerified(true)
            ->setLastLoginAt($now);

        $session = (new UserSession())
            ->setUser($user)
            ->setSessionId($this->generateSessionId());

        $entityManager->persist($session);

        $request->attributes->set('auth.session', $session);
        $request->attributes->set('auth.issue_tokens_for_session', $session);
        $request->attributes->set('auth.clear_cookies', false);
        $this->addFlash('success', 'Connexion reussie. Votre session restera active jusqu a votre deconnexion.');

        return $this->redirectToRoute('app_homepage');
    }

    #[Route('/auth/logout', name: 'app_auth_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('logout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'La demande de deconnexion a expire. Reessayez.');

            return $this->redirectToRoute('app_homepage');
        }

        $session = $request->attributes->get('auth.session');
        if ($session instanceof UserSession) {
            $session->revoke(new \DateTimeImmutable());
            $entityManager->flush();
        }

        $request->attributes->set('auth.clear_cookies', true);
        $this->addFlash('success', 'Vous etes maintenant deconnecte.');

        return $this->redirectToRoute('app_homepage');
    }

    private function generateSessionId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
