<?php

namespace Codeart\Core\Publishing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Issues and looks up preview links. A token is 48 random characters; only its SHA-256 is stored, so a database
 * leak does not leak working links. It is bound to ONE model, ONE revision and ONE user, and dies at `expires_at`.
 */
class PreviewTokens
{
    /** The plain token; it is never stored and cannot be shown again. */
    public function issue(Model $model, Revision $revision, Authenticatable $user, ?string $locale = null): string
    {
        $token = Str::random(48);
        PreviewToken::query()->create([
            'token_hash' => $this->hash($token),
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'revision_id' => $revision->id,
            'locale' => $locale,
            'user_id' => $user->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(max(1, (int) config('codeart.preview.ttl_minutes', 60))),
        ]);

        return $token;
    }

    /** The row of a live (not expired) token, or null. */
    public function find(string $token): ?PreviewToken
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }
        $row = PreviewToken::query()->where('token_hash', $this->hash($token))->first();

        return $row !== null && ! $row->expired() ? $row : null;
    }

    public function revoke(string $token): void
    {
        PreviewToken::query()->where('token_hash', $this->hash($token))->delete();
    }

    public function prune(): int
    {
        return PreviewToken::query()->where('expires_at', '<', now())->delete();
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
