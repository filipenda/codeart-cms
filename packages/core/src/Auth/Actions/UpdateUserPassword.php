<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(private readonly PasswordRules $passwords, private readonly UserSessions $sessions) {}

    /**
     * @param  Model&Authenticatable  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:'.Admin::guardName()],
            'password' => [...$this->passwords->rules(), 'confirmed'],
        ], [], __('codeart::auth.attributes'))->validateWithBag('updatePassword');

        $user->forceFill(['password' => Hash::make((string) $input['password'])])->save();

        // Other devices are signed out; this one stays.
        $current = request()->hasSession() ? request()->session()->getId() : null;
        $current === null ? $this->sessions->revokeAll($user->getKey()) : $this->sessions->revokeOthers($user->getKey(), $current);
    }
}
