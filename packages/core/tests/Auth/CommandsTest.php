<?php

use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\PendingCommand;
use Spatie\Permission\Models\Role;

function createUser(string $role = 'super-admin', string $email = 'boss@example.com'): PendingCommand
{
    return test()->artisan('user:create', ['name' => 'Boss', 'email' => $email, 'role' => $role]);
}

it('creates a user, asking for the password with hidden prompts', function () {
    createUser()->expectsQuestion('Password', 'Correct-horse-9')->expectsQuestion('Confirm password', 'Correct-horse-9')->assertSuccessful();

    $user = AdminTestUser::query()->where('email', 'boss@example.com')->firstOrFail();
    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and(Hash::check('Correct-horse-9', $user->password))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->approved_at)->not->toBeNull();
});

it('has no password argument or option', function () {
    $definition = collect(Artisan::all())->get('user:create')->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['name', 'email', 'role'])
        ->and(array_keys($definition->getOptions()))->not->toContain('password');
});

it('refuses without a terminal, a weak or unconfirmed password, a known e-mail or an unknown role', function () {
    createUser()->expectsQuestion('Password', 'weak')->expectsQuestion('Confirm password', 'weak')->assertFailed();
    createUser()->expectsQuestion('Password', 'Correct-horse-9')->expectsQuestion('Confirm password', 'Other-horse-99')->assertFailed();
    createUser('nonexistent-role')->expectsQuestion('Password', 'Correct-horse-9')->expectsQuestion('Confirm password', 'Correct-horse-9')->assertFailed();
    staff('editor', ['email' => 'boss@example.com']);
    createUser()->assertFailed();
    $this->artisan('user:create', ['name' => 'B', 'email' => 'b@example.com', 'role' => 'admin', '--no-interaction' => true])->assertFailed();

    expect(AdminTestUser::query()->where('email', 'b@example.com')->exists())->toBeFalse();
});

it('invites from the command line', function () {
    Mail::fake();

    $this->artisan('user:invite', ['name' => 'New', 'email' => 'new@example.com', 'role' => 'editor'])->assertSuccessful();
    $this->artisan('user:invite', ['name' => 'New', 'email' => 'new2@example.com', 'role' => 'no-such-role'])->assertFailed();

    Mail::assertSent(InvitationMail::class, 1);
    Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('new@example.com'));
});

it('deactivates a user: no sign-in, no sessions', function () {
    $user = staff('editor');
    DB::table('sessions')->insert(['id' => 's', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('user:deactivate', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    $this->postJson('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertStatus(422);
    $this->artisan('user:deactivate', ['email' => 'nobody@example.com'])->assertFailed();
});

it('creates the default roles and permissions with codeart:roles', function () {
    Role::query()->delete();

    $this->artisan('codeart:roles')->expectsOutputToContain('super-admin, admin, editor, member')->assertSuccessful();
});
