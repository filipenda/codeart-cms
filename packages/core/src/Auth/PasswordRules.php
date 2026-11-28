<?php

namespace Codeart\Core\Auth;

use Closure;
use Codeart\Core\Settings\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

/** The password rule, built from security.password.* on every call (settings can change at runtime). */
class PasswordRules
{
    public function __construct(private readonly Settings $settings) {}

    /** @return list<mixed> rules for a password field (add 'confirmed' yourself where needed) */
    public function rules(): array
    {
        $password = Password::min((int) $this->settings->get('security.password.min_length'));
        if ($this->settings->get('security.password.require_mixed_case')) {
            $password->mixedCase();
        }
        if ($this->settings->get('security.password.require_number')) {
            $password->numbers();
        }
        if ($this->settings->get('security.password.require_symbol')) {
            $password->symbols();
        }

        $rules = ['required', 'string', 'max:255', $password];
        if ($this->settings->get('security.password.check_breached')) {
            $rules[] = $this->notBreached();
        }

        return $rules;
    }

    /**
     * The configured rules as a checklist for the strength meter: [key, label, value]. The breached check is not part of it
     * (server-side only); see `checksBreached()`.
     *
     * @return list<array{key: string, label: string, value: ?int}>
     */
    public function requirements(): array
    {
        $min = (int) $this->settings->get('security.password.min_length');
        $list = [['key' => 'min_length', 'label' => __('codeart::profile.pw_min', ['n' => $min]), 'value' => $min]];
        foreach (['require_mixed_case' => 'mixed_case', 'require_number' => 'number', 'require_symbol' => 'symbol'] as $setting => $key) {
            if ($this->settings->get('security.password.'.$setting)) {
                $list[] = ['key' => $key, 'label' => __('codeart::profile.pw_'.$key), 'value' => null];
            }
        }

        return $list;
    }

    public function checksBreached(): bool
    {
        return (bool) $this->settings->get('security.password.check_breached');
    }

    /** Laravel's uncompromised check, but an unreachable service never blocks the user and never throws. */
    private function notBreached(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                try {
                    $failed = Validator::make([$attribute => $value], [$attribute => [Password::min(1)->uncompromised()]])->fails();
                } catch (Throwable) {
                    return; // fail open: the breach service is unreachable
                }
                if ($failed) {
                    $fail('codeart::auth.password_breached')->translate();
                }
            }
        };
    }
}
