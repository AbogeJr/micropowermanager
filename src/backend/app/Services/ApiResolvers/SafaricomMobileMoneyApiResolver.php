<?php

declare(strict_types=1);

namespace App\Services\ApiResolvers;

use App\Exceptions\ValidationException;
use App\Services\Interfaces\IApiResolver;
use Illuminate\Http\Request;

class SafaricomMobileMoneyApiResolver implements IApiResolver {
    public function resolveCompanyId(Request $request): int {
        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = auth('api');
        $payload = $guard->check() ? $guard->payload() : null;

        $companyId = $payload?->get('companyId');

        if (!$companyId) {
            throw new ValidationException('Failed to parse company identifier from the request');
        }

        return (int) $companyId;
    }
}
