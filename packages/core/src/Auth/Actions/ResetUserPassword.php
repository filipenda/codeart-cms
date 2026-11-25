<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(private readonly PasswordRules $passwords, private readonly UserSessions $sessions) {}

    /**
     * @param  Model&Authenticatable  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => [...$this->passwords->rules(), 'confirmed']], [], __('codeart::auth.attributes'))->validate();

        // Whoever knew the old password (or held a session) is signed out everywhere.
        $user->forceFill(['password' => Hash::make((string) $input['password']), 'remember_token' => Str::random(60)])->save();
        $this->sessions->revokeAll($user->getKey());
    }
}
