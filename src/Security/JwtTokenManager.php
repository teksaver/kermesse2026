<?php

namespace App\Security;

use App\Entity\UserSession;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class JwtTokenManager
{
    public function __construct(
        #[Autowire('%env(AUTH_JWT_SECRET)%')] private readonly string $secret,
        #[Autowire('%env(int:AUTH_ACCESS_TOKEN_TTL)%')] private readonly int $accessTokenTtl,
        #[Autowire('%env(int:AUTH_REFRESH_TOKEN_TTL)%')] private readonly int $refreshTokenTtl,
        #[Autowire('%env(int:AUTH_REFRESH_THRESHOLD)%')] private readonly int $refreshThreshold,
    ) {
    }

    /**
     * @return array{sub:int,sid:string,typ:string,exp:int,iat:int,nonce?:string}
     */
    public function parseAndValidate(string $token, string $expectedType): array
    {
        try {
            $parts = explode('.', $token);
            if (3 !== count($parts)) {
                throw new AuthenticationException('Malformed token.');
            }

            [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

            $header = json_decode($this->base64UrlDecode($encodedHeader), true, flags: \JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, flags: \JSON_THROW_ON_ERROR);

            if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? null) !== 'HS256') {
                throw new AuthenticationException('Unsupported token format.');
            }

            $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true));
            if (!hash_equals($expectedSignature, $encodedSignature)) {
                throw new AuthenticationException('Invalid token signature.');
            }

            $now = time();
            if (($payload['typ'] ?? null) !== $expectedType || !isset($payload['sub'], $payload['sid'], $payload['exp'], $payload['iat'])) {
                throw new AuthenticationException('Invalid token payload.');
            }

            if ((int) $payload['exp'] <= $now) {
                throw new AuthenticationException('Token expired.');
            }
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationException('Token rejected.', 0, $exception);
        }

        return [
            'sub' => (int) $payload['sub'],
            'sid' => (string) $payload['sid'],
            'typ' => (string) $payload['typ'],
            'exp' => (int) $payload['exp'],
            'iat' => (int) $payload['iat'],
            'nonce' => isset($payload['nonce']) ? (string) $payload['nonce'] : null,
        ];
    }

    /**
     * @return array{accessToken:string,refreshToken:string,accessExpiresAt:\DateTimeImmutable,refreshExpiresAt:\DateTimeImmutable}
     */
    public function issueTokenPair(UserSession $session): array
    {
        $now = new \DateTimeImmutable();
        $accessExpiresAt = $now->modify(sprintf('+%d seconds', $this->accessTokenTtl));
        $refreshExpiresAt = $now->modify(sprintf('+%d seconds', $this->refreshTokenTtl));
        $nonce = bin2hex(random_bytes(32));

        $session->rotate(hash('sha256', $nonce), $refreshExpiresAt, $now);

        return [
            'accessToken' => $this->encode([
                'sub' => $session->getUser()?->getId(),
                'sid' => $session->getSessionId(),
                'typ' => 'access',
                'iat' => $now->getTimestamp(),
                'exp' => $accessExpiresAt->getTimestamp(),
            ]),
            'refreshToken' => $this->encode([
                'sub' => $session->getUser()?->getId(),
                'sid' => $session->getSessionId(),
                'typ' => 'refresh',
                'nonce' => $nonce,
                'iat' => $now->getTimestamp(),
                'exp' => $refreshExpiresAt->getTimestamp(),
            ]),
            'accessExpiresAt' => $accessExpiresAt,
            'refreshExpiresAt' => $refreshExpiresAt,
        ];
    }

    public function shouldRefreshSoon(int $expiresAtTimestamp): bool
    {
        return $expiresAtTimestamp - time() <= $this->refreshThreshold;
    }

    /**
     * @param array<string, int|string|null> $payload
     */
    private function encode(array $payload): string
    {
        $encodedHeader = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], \JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, \JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true));

        return $encodedHeader.'.'.$encodedPayload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if (0 !== $padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if (false === $decoded) {
            throw new \RuntimeException('Invalid base64url payload.');
        }

        return $decoded;
    }
}
