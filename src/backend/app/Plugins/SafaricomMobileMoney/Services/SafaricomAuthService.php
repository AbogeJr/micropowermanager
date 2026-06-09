<?php

declare(strict_types=1);

namespace App\Plugins\SafaricomMobileMoney\Services;

use App\Plugins\SafaricomMobileMoney\Models\SafaricomCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SafaricomAuthService {
    private const CACHE_TTL = 3500; // 1 hour - 100 seconds buffer

    public function __construct(
        private SafaricomCredential $credential,
    ) {}

    public function getAccessToken(): string {
        $credential = $this->credential->newQuery()->first();
        if (!$credential) {
            throw new \RuntimeException('Safaricom credentials are not configured.');
        }

        return Cache::remember(
            $this->cacheKey($credential->getEnvironment()),
            self::CACHE_TTL,
            fn () => $this->generateAccessToken($credential),
        );
    }

    public function clearAccessToken(): void {
        Cache::forget($this->cacheKey('sandbox'));
        Cache::forget($this->cacheKey('production'));
    }

    private function generateAccessToken(SafaricomCredential $credential): string {
        $consumerKey = $credential->getConsumerKey();
        $consumerSecret = $credential->getConsumerSecret();

        if ($consumerKey === '' || $consumerSecret === '') {
            throw new \RuntimeException('Safaricom consumer key/secret are missing.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($consumerKey.':'.$consumerSecret),
            'Content-Type' => 'application/json',
        ])->get($this->getBaseUrl($credential).'/oauth/v1/generate?grant_type=client_credentials');

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain Safaricom access token: '.$response->body());
        }

        $token = $response->json('access_token');
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Safaricom token endpoint returned no access_token.');
        }

        return $token;
    }

    private function getBaseUrl(SafaricomCredential $credential): string {
        return $credential->isProduction()
            ? (string) config('safaricom-mobile-money.api.production_url', 'https://api.safaricom.co.ke')
            : (string) config('safaricom-mobile-money.api.sandbox_url', 'https://sandbox.safaricom.co.ke');
    }

    private function cacheKey(string $environment): string {
        return 'safaricom:access_token:'.$environment;
    }
}
