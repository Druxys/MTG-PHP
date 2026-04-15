<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonImmutable;

class ApiTokenService
{
    /**
     * @return array{token: string, expiresAt: CarbonImmutable}
     */
    public function issueToken(User $user): array
    {
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addDay();

        ApiToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'expiresAt' => $expiresAt,
        ];
    }

    public function findValidTokenRecord(string $plainToken): ?ApiToken
    {
        return ApiToken::query()
            ->with('user')
            ->where('token_hash', $this->hashToken($plainToken))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revokeToken(string $plainToken): void
    {
        ApiToken::query()
            ->where('token_hash', $this->hashToken($plainToken))
            ->delete();
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
