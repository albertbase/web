<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OAuth client IDs whose id_token "aud" claim we accept on /api/auth/google.
 */
final class GoogleOAuthAudienceValidator
{
    /** @var list<string> */
    private array $allowedAudiences;

    public function __construct(
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        string $googleClientId,
        #[Autowire('%env(default::string:GOOGLE_ALLOWED_AUDIENCES)%')]
        ?string $googleAllowedAudiences = null,
        #[Autowire('%env(default::string:GOOGLE_ANDROID_CLIENT_ID)%')]
        ?string $googleAndroidClientId = null,
        #[Autowire('%env(default::string:GOOGLE_IOS_CLIENT_ID)%')]
        ?string $googleIosClientId = null,
        #[Autowire('%env(default::string:GOOGLE_MOBILE_WEB_CLIENT_ID)%')]
        ?string $googleMobileWebClientId = null,
        #[Autowire('%env(default::string:GOOGLE_MOBILE_ANDROID_CLIENT_ID)%')]
        ?string $googleMobileAndroidClientId = null,
    ) {
        $this->allowedAudiences = $this->normalizeList([
            $googleClientId,
            $googleAndroidClientId,
            $googleIosClientId,
            $googleMobileWebClientId,
            $googleMobileAndroidClientId,
            ...$this->splitCsv($googleAllowedAudiences ?? ''),
        ]);
    }

    public function isAllowed(string $audience): bool
    {
        return $audience !== '' && in_array($audience, $this->allowedAudiences, true);
    }

    /**
     * @param list<string|null> $values
     *
     * @return list<string>
     */
    private function normalizeList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    private function splitCsv(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        return array_map('trim', explode(',', $csv));
    }
}
