<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ApiTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret
    ) {}

    public function generate(User $user, int $ttlSeconds = 86400): string
    {
        $issuedAt = time();
        $payload = [
            'sub' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
        ];

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true);

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw new \RuntimeException('Invalid token signature.');
        }

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
            throw new \RuntimeException('Invalid token header.');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid token payload.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new \RuntimeException('Token expired.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
