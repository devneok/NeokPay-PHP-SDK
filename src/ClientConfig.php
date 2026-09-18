<?php

declare(strict_types=1);

namespace Neok\Pay;

use Composer\InstalledVersions;

final readonly class ClientConfig
{
    public function __construct(#[\SensitiveParameter] private string $apiKey, public string $baseUrl = 'https://pay.neok.me/api/v1', public ?string $userAgentSuffix = null)
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('An API key is required.');
        }
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('The base URL must be an absolute URL.');
        }
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function userAgent(): string
    {
        return trim('neokpay-php/'.$this->packageVersion().' '.($this->userAgentSuffix ?? ''));
    }

    private function packageVersion(): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('devneok/neokpay-php')) {
            return 'development';
        }

        $version = InstalledVersions::getPrettyVersion('devneok/neokpay-php');
        // Composer's unversioned root placeholder is not a real stable release.
        if ($version === null || str_contains($version, 'no-version-set')
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+_-]*\z/', $version) !== 1) {
            return 'development';
        }

        return $version;
    }

    /** @return array<string, string|null> */
    public function __debugInfo(): array
    {
        return ['apiKey' => '[REDACTED]', 'baseUrl' => $this->baseUrl, 'userAgentSuffix' => $this->userAgentSuffix];
    }
}
