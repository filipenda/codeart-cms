<?php

namespace Codeart\Core\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property list<string> $roles
 * @property string|null $message
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon $created_at
 */
class UserInvitation extends Model
{
    protected $table = 'user_invitations';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['roles' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** What a person opening the link should be told: valid, used, expired. (Unknown tokens are 'invalid', decided by the caller.) */
    public function state(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'used',
            $this->expires_at->isPast() => 'expired',
            default => 'valid',
        };
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
