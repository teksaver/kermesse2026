<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserRepository;
use App\Repository\UserSessionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtCookieAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly UserSessionRepository $userSessionRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has(AuthCookieManager::ACCESS_COOKIE) || $request->cookies->has(AuthCookieManager::REFRESH_COOKIE);
    }

    public function authenticate(Request $request): Passport
    {
        $now = new \DateTimeImmutable();
        $accessToken = $request->cookies->get(AuthCookieManager::ACCESS_COOKIE);

        if (is_string($accessToken) && '' !== $accessToken) {
            try {
                $claims = $this->jwtTokenManager->parseAndValidate($accessToken, 'access');
                $session = $this->requireActiveSession($claims['sid'], $claims['sub'], $now);

                $request->attributes->set('auth.session', $session);
                if ($this->jwtTokenManager->shouldRefreshSoon($claims['exp'])) {
                    $request->attributes->set('auth.issue_tokens_for_session', $session);
                }
                $request->attributes->set('auth.clear_cookies', false);

                return $this->createPassport($session->getUser());
            } catch (AuthenticationException) {
                $request->attributes->set('auth.clear_cookies', true);
            }
        }

        $refreshToken = $request->cookies->get(AuthCookieManager::REFRESH_COOKIE);
        if (!is_string($refreshToken) || '' === $refreshToken) {
            throw new AuthenticationException('No refresh token.');
        }

        $claims = $this->jwtTokenManager->parseAndValidate($refreshToken, 'refresh');
        $session = $this->requireActiveSession($claims['sid'], $claims['sub'], $now);
        if (!hash_equals($session->getRefreshTokenHash(), hash('sha256', (string) ($claims['nonce'] ?? '')))) {
            throw new AuthenticationException('Refresh token rejected.');
        }

        $request->attributes->set('auth.session', $session);
        $request->attributes->set('auth.issue_tokens_for_session', $session);
        $request->attributes->set('auth.clear_cookies', false);

        return $this->createPassport($session->getUser());
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->attributes->set('auth.clear_cookies', true);

        return null;
    }

    private function createPassport(?UserInterface $user): Passport
    {
        if (!$user instanceof User || null === $user->getId()) {
            throw new AuthenticationException('User account not found.');
        }

        return new SelfValidatingPassport(new UserBadge((string) $user->getId(), function (string $identifier): User {
            $user = $this->userRepository->find((int) $identifier);
            if (!$user instanceof User) {
                throw new AuthenticationException('User account missing.');
            }

            return $user;
        }));
    }

    private function requireActiveSession(string $sessionId, int $userId, \DateTimeImmutable $now): UserSession
    {
        $session = $this->userSessionRepository->findActiveSession($sessionId, $userId, $now);
        if (!$session instanceof UserSession || !$session->getUser() instanceof User) {
            throw new AuthenticationException('Session is not active.');
        }

        return $session;
    }
}
