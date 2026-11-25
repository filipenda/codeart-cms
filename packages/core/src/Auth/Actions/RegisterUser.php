<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Settings\Settings;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/** Public registration (Fortify calls this). The route itself is closed unless auth.registration.enabled. */
class RegisterUser implements CreatesNewUsers
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PasswordRules $passwords,
        private readonly CreateAdminUser $create,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $table = Admin::userModel()::query()->getModel()->getTable();
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($table, 'email'), $this->allowedDomain(...)],
            'password' => [...$this->passwords->rules(), 'confirmed'],
        ], [], __('codeart::auth.attributes'))->validate();

        $needsApproval = (bool) $this->settings->get('auth.registration.require_approval');

        $user = ($this->create)(
            (string) $validated['name'],
            (string) $validated['email'],
            (string) $validated['password'],
            [(string) $this->settings->get('auth.registration.default_role')],
            null,
            $needsApproval ? ['is_active' => false, 'approved_at' => null] : [],
        );
        assert($user instanceof User); // the app's User model extends Laravel's

        return $user;
    }

    /** Validation rule: the e-mail's domain must be on auth.registration.allowed_email_domains (when that list is set). */
    private function allowedDomain(string $attribute, mixed $value, \Closure $fail): void
    {
        $allowed = array_map('strtolower', array_filter((array) $this->settings->get('auth.registration.allowed_email_domains'), 'is_string'));
        if ($allowed === [] || ! is_string($value)) {
            return;
        }
        $domain = strtolower(substr(strrchr($value, '@') ?: '', 1));
        if (! in_array($domain, $allowed, true)) {
            $fail(__('codeart::auth.registration_domain'));
        }
    }
}
