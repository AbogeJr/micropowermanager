<?php

namespace App\Plugins\SafaricomMobileMoney\Models;

use App\Models\Base\BaseModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int         $id
 * @property string      $consumer_key
 * @property string      $consumer_secret
 * @property string      $passkey
 * @property string      $shortcode
 * @property string      $environment
 * @property string|null $validation_url
 * @property string|null $confirmation_url
 * @property string|null $timeout_url
 * @property string|null $result_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SafaricomCredential extends BaseModel {
    protected $table = 'safaricom_credentials';

    public function getConsumerKey(): string {
        try {
            return Crypt::decrypt($this->attributes['consumer_key']);
        } catch (\Throwable) {
            return $this->attributes['consumer_key'] ?? '';
        }
    }

    public function getConsumerSecret(): string {
        try {
            return Crypt::decrypt($this->attributes['consumer_secret']);
        } catch (\Throwable) {
            return $this->attributes['consumer_secret'] ?? '';
        }
    }

    public function getPasskey(): string {
        try {
            return Crypt::decrypt($this->attributes['passkey']);
        } catch (\Throwable) {
            return $this->attributes['passkey'] ?? '';
        }
    }

    public function getShortcode(): string {
        return $this->shortcode ?? '';
    }

    public function getEnvironment(): string {
        return $this->environment ?? 'sandbox';
    }

    public function isProduction(): bool {
        return $this->getEnvironment() === 'production';
    }

    public function isSandbox(): bool {
        return $this->getEnvironment() === 'sandbox';
    }

    public function getValidationUrl(): ?string {
        return $this->validation_url;
    }

    public function getConfirmationUrl(): ?string {
        return $this->confirmation_url;
    }

    public function getTimeoutUrl(): ?string {
        return $this->timeout_url;
    }

    public function getResultUrl(): ?string {
        return $this->result_url;
    }
}
