<?php

declare(strict_types=1);

namespace App\Plugins\SafaricomMobileMoney\Services;

use App\Plugins\SafaricomMobileMoney\Models\SafaricomCredential;
use Illuminate\Support\Facades\Crypt;

class SafaricomCredentialService {
    private const ENCRYPTED_FIELDS = ['consumer_key', 'consumer_secret', 'passkey'];

    public function __construct(
        private SafaricomCredential $credential,
        private SafaricomAuthService $authService,
    ) {}

    public function getCredentials(): SafaricomCredential {
        $credential = $this->credential->newQuery()->first();
        if (!$credential) {
            return $this->createCredentials();
        }

        return $credential;
    }

    public function createCredentials(): SafaricomCredential {
        return $this->credential->newQuery()->create([
            'consumer_key' => '',
            'consumer_secret' => '',
            'passkey' => '',
            'shortcode' => '',
            'environment' => 'sandbox',
            'validation_url' => null,
            'confirmation_url' => null,
            'timeout_url' => null,
            'result_url' => null,
        ]);
    }

    public function hasCredentials(): bool {
        return $this->credential->newQuery()->exists();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCredentials(array $data): SafaricomCredential {
        $credential = $this->getCredentials();

        $secretsRotated = false;
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $secretsRotated = true;
                $data[$field] = Crypt::encrypt($data[$field]);
            }
        }

        $environmentChanged = array_key_exists('environment', $data)
            && $data['environment'] !== $credential->getEnvironment();

        $credential->update($data);
        $credential->refresh();

        if ($secretsRotated || $environmentChanged) {
            $this->authService->clearAccessToken();
        }

        return $credential;
    }
}
