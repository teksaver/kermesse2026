<?php

namespace App\EventSubscriber;

use App\Entity\UserSession;
use App\Security\AuthCookieManager;
use App\Security\JwtTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AuthCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly AuthCookieManager $authCookieManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $shouldClearCookies = true === $request->attributes->get('auth.clear_cookies');
        $session = $request->attributes->get('auth.issue_tokens_for_session');

        if ($session instanceof UserSession && !$shouldClearCookies) {
            $tokenPair = $this->jwtTokenManager->issueTokenPair($session);
            $this->entityManager->flush();

            foreach ($this->authCookieManager->createCookies($request, $tokenPair) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }

        if ($shouldClearCookies) {
            foreach ($this->authCookieManager->clearCookies($request) as $cookie) {
                $response->headers->setCookie($cookie);
            }
        }
    }
}
