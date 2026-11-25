<?php

namespace Codeart\Core\Settings;

use Codeart\Core\Branding\BrandingImageUploader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * The checked write path for people (future admin screens): permission of the definition, then the lock.
 * `super-admin` can change everything and can lock/unlock; a locked setting is changeable by super-admin only.
 */
class SettingsManager
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsRegistry $registry,
    ) {}

    /** @throws AuthorizationException|SettingLocked|UnknownSetting|InvalidSettingValue */
    public function update(mixed $user, string $key, mixed $value, ?string $locale = null): void
    {
        $this->authorize($user, $key);
        $this->settings->set($key, $value, $locale);
    }

    /** Store an uploaded logo/favicon for an image setting. */
    public function uploadImage(mixed $user, string $key, UploadedFile $file): string
    {
        $this->authorize($user, $key);

        return app(BrandingImageUploader::class)->store($key, $file);
    }

    public function lock(mixed $user, string $key): void
    {
        $this->requireSuperAdmin($user, 'lock');
        $this->settings->lock($key);
    }

    public function unlock(mixed $user, string $key): void
    {
        $this->requireSuperAdmin($user, 'unlock');
        $this->settings->unlock($key);
    }

    public function canUpdate(mixed $user, string $key): bool
    {
        try {
            $this->authorize($user, $key);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    /** @throws AuthorizationException */
    public function authorize(mixed $user, string $key): void
    {
        $definition = $this->registry->definition($key);

        if (! $this->isSuperAdmin($user)) {
            $allowed = $definition->permission !== null && is_object($user) && Gate::forUser($user)->allows($definition->permission);
            if (! $allowed) {
                throw new AuthorizationException("You may not change setting [{$key}].");
            }
            if ($this->settings->isLocked($key)) {
                throw new SettingLocked($key);
            }
        }
    }

    private function requireSuperAdmin(mixed $user, string $action): void
    {
        if (! $this->isSuperAdmin($user)) {
            throw new AuthorizationException("Only a super-admin can {$action} settings.");
        }
    }

    private function isSuperAdmin(mixed $user): bool
    {
        return is_object($user) && method_exists($user, 'hasRole') && $user->hasRole('super-admin');
    }
}
