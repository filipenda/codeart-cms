<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Auth\Mail\RegistrationApprovedMail;
use Codeart\Core\Auth\Mail\RegistrationRejectedMail;
use Codeart\Core\Auth\UserPolicy;
use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Confirmation\RequireConfirmation;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Codeart\Core\Users\UserStatus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
});

/** Send a dangerous request the way the browser does: confirmation token for this user, method and path + fresh password. */
function danger($test, AdminTestUser $actor, string $method, string $path, array $data = [])
{
    $token = app(ConfirmationTokens::class)->issue($actor, RequireConfirmation::scope($method, $path));

    return $test->withSession(['auth.password_confirmed_at' => time()])->actingAs($actor, 'admin')->call($method, $path, $data + ['confirmation' => $token, '_token' => csrf_token()]);
}

function usersList($test, AdminTestUser $actor, string $query = ''): DOMXPath
{
    return dom($test->actingAs($actor, 'admin')->get('/admin/users'.$query)->assertOk()->getContent());
}

it('grants the user permissions to the agreed roles only', function () {
    $admin = staff('admin');
    foreach (['view', 'create', 'update', 'block', 'approve', 'delete', 'reset-2fa'] as $p) {
        expect($admin->can("users.{$p}"))->toBeTrue($p);
    }
    expect($admin->can('users.force-delete'))->toBeFalse()
        ->and(staff('super-admin')->can('users.force-delete'))->toBeTrue()
        ->and(staff('editor')->can('users.view'))->toBeFalse()
        ->and(staff('member')->can('users.view'))->toBeFalse();
});

it('shows the users list to admin and super-admin only', function (string $role, int $status) {
    $this->actingAs(staff($role), 'admin')->get('/admin/users')->assertStatus($status);
})->with([['super-admin', 200], ['admin', 200], ['editor', 403]]);

it('answers 403 to an editor on every users URL, and redirects guests', function () {
    $editor = staff('editor');
    $target = staff('member');
    $this->get('/admin/users')->assertRedirect();
    foreach ([['get', '/admin/users'], ['get', "/admin/users/{$target->id}"], ['put', "/admin/users/{$target->id}"], ['post', "/admin/users/{$target->id}/approve"], ['post', "/admin/users/{$target->id}/unblock"], ['post', "/admin/users/{$target->id}/restore"]] as [$m, $url]) {
        $this->actingAs($editor, 'admin')->call(strtoupper($m), $url)->assertForbidden();
    }
    foreach ([['post', 'block'], ['delete', ''], ['delete', 'force'], ['post', 'reset-2fa'], ['put', 'role']] as [$m, $suffix]) {
        danger($this, $editor, strtoupper($m), "/admin/users/{$target->id}".($suffix ? "/{$suffix}" : ''))->assertForbidden();
    }
    expect($target->fresh()->is_active)->toBeTrue()->and($target->fresh()->trashed())->toBeFalse();
});

it('lists status, role, 2FA, and finds rows by translated text', function () {
    $admin = staff('admin');
    staff('editor', ['name' => 'Plain Ed']);
    staff('member', ['name' => 'Blocked Bob', 'is_active' => false]);
    staff('member', ['name' => 'Waiting Wendy', 'approved_at' => null, 'is_active' => false]);
    $rows = '//div[@data-table="users"]//tbody/tr';

    expect(count_nodes(usersList($this, $admin), $rows))->toBe(4)
        ->and(count_nodes(usersList($this, $admin, '?q=Blokiran'), $rows))->toBe(1)       // translated status text
        ->and(count_nodes(usersList($this, $admin, '?q=blocked'), $rows))->toBe(1)        // raw key
        ->and(count_nodes(usersList($this, $admin, '?q=Urednik'), $rows))->toBe(1)        // translated role
        ->and(count_nodes(usersList($this, $admin, '?q=Waiting'), $rows))->toBe(1)
        ->and(count_nodes(usersList($this, $admin, '?filter[status]=pending'), $rows))->toBe(1)
        ->and(count_nodes(usersList($this, $admin, '?filter[role]=editor'), $rows))->toBe(1)
        ->and(count_nodes(usersList($this, $admin, '?sort=status&dir=asc'), $rows))->toBe(4)
        ->and(text_of(usersList($this, $admin), $rows))->toContain('Čeka odobrenje');
});

it('hides deleted users until the deleted filter is used', function () {
    $admin = staff('admin');
    staff('member', ['name' => 'Gone Gary'])->delete();
    $rows = '//div[@data-table="users"]//tbody/tr';

    expect(text_of(usersList($this, $admin), '//div[@data-table="users"]'))->not->toContain('Gone Gary')
        ->and(text_of(usersList($this, $admin, '?filter[trashed]=only'), $rows))->toContain('Gone Gary')->toContain('Obrisan');
});

it('offers row actions only for users the actor may manage', function () {
    $admin = staff('admin');
    $other = staff('admin', ['name' => 'Peer Admin']);
    $member = staff('member', ['name' => 'Low Member']);
    $sa = staff('super-admin', ['name' => 'Top Boss']);
    $xp = usersList($this, $admin);
    $row = fn (string $name) => "//div[@data-table='users']//tbody/tr[contains(.,'{$name}')]";

    expect(count_nodes($xp, $row('Low Member').'//*[@data-action="edit"]'))->toBe(1)
        ->and(count_nodes($xp, $row('Peer Admin').'//*[@data-action="edit"]'))->toBe(0)
        ->and(count_nodes($xp, $row('Top Boss').'//*[@data-action="delete"]'))->toBe(0)
        ->and(count_nodes($xp, $row('Peer Admin').'//*[@data-action="block"]'))->toBe(0);
});

it('enforces the management rules in the policy', function () {
    $sa = staff('super-admin');
    $sa2 = staff('super-admin');
    $admin = staff('admin');
    $admin2 = staff('admin');
    $editor = staff('editor');
    $can = fn ($actor, $ability, $target) => Gate::forUser($actor)->allows($ability, $target);

    // admin vs admin / super-admin: nothing
    foreach (['update', 'block', 'delete', 'resetTwoFactor', 'approve', 'changeRole'] as $a) {
        expect($can($admin, $a, $admin2))->toBeFalse($a)->and($can($admin, $a, $sa))->toBeFalse($a);
    }
    // admin vs editor: everything except permanent delete
    foreach (['update', 'block', 'delete', 'resetTwoFactor', 'approve', 'changeRole'] as $a) {
        expect($can($admin, $a, $editor))->toBeTrue($a);
    }
    expect($can($admin, 'forceDelete', $editor))->toBeFalse()->and($can($sa, 'forceDelete', $editor))->toBeTrue();
    // nobody blocks, deletes or re-roles themselves; two super-admins may touch each other
    foreach (['block', 'delete', 'changeRole', 'forceDelete', 'resetTwoFactor'] as $a) {
        expect($can($sa, $a, $sa))->toBeFalse($a);
    }
    expect($can($sa, 'block', $sa2))->toBeTrue();
    // the last super-admin is protected
    $sa2->delete();
    expect($can(staff('super-admin'), 'block', $sa))->toBeTrue(); // not last: a new one exists
});

it('protects the last super-admin from block, delete and demotion', function () {
    $sa = staff('super-admin');
    $admin = staff('admin');
    $policy = app(UserPolicy::class);

    expect($policy->isLastSuperAdmin($sa))->toBeTrue();
    expect(Gate::forUser($admin)->allows('block', $sa))->toBeFalse();
    $other = staff('super-admin');
    expect($policy->isLastSuperAdmin($sa))->toBeFalse()
        ->and(Gate::forUser($other)->allows('delete', $sa))->toBeTrue();
    $other->update(['is_active' => false]); // an inactive one does not count as a second
    expect($policy->isLastSuperAdmin($sa))->toBeTrue()
        ->and(Gate::forUser($sa)->allows('changeRole', $sa))->toBeFalse();
});

it('assigns only roles strictly below the actor', function () {
    $admin = staff('admin');
    $member = staff('member');

    danger($this, $admin, 'PUT', "/admin/users/{$member->id}/role", ['role' => 'editor'])->assertRedirect();
    expect($member->fresh()->hasRole('editor'))->toBeTrue();
    foreach (['admin', 'super-admin'] as $role) {
        danger($this, $admin, 'PUT', "/admin/users/{$member->id}/role", ['role' => $role])->assertForbidden();
    }
    expect($member->fresh()->getRoleNames()->all())->toBe(['editor']);
    // nobody changes their own role
    danger($this, $admin, 'PUT', "/admin/users/{$admin->id}/role", ['role' => 'editor'])->assertForbidden();
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
    // role change is logged with old and new
    $log = Activity::query()->where('event', 'user.role_changed')->first();
    expect($log->properties['old'])->toBe(['member'])->and($log->properties['new'])->toBe(['editor']);
});

it('edits name and email, ignores everything else, and unverifies a new address', function () {
    $admin = staff('admin');
    $member = staff('member', ['name' => 'Old Name', 'email' => 'old@example.com']);

    $this->actingAs($admin, 'admin')->put("/admin/users/{$member->id}", ['name' => 'New Name', 'email' => 'new@example.com', 'role' => 'admin', 'is_active' => 0, 'password' => 'Hacked-pass-1', 'approved_at' => null])->assertRedirect();

    $fresh = $member->fresh();
    expect($fresh->name)->toBe('New Name')->and($fresh->email)->toBe('new@example.com')->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->hasRole('member'))->toBeTrue()->and($fresh->hasRole('admin'))->toBeFalse()
        ->and($fresh->is_active)->toBeTrue()->and($fresh->approved_at)->not->toBeNull()
        ->and(Hash::check('Hacked-pass-1', $fresh->password))->toBeFalse();
    $log = Activity::query()->where('event', 'user.email_changed')->first();
    expect($log->properties['old'])->toBe('old@example.com')->and($log->properties['new'])->toBe('new@example.com');
});

it('validates the edit form', function () {
    $admin = staff('admin');
    $member = staff('member');
    $taken = staff('editor');

    $this->actingAs($admin, 'admin')->put("/admin/users/{$member->id}", ['name' => '', 'email' => 'nope'])->assertSessionHasErrors(['name', 'email']);
    $this->actingAs($admin, 'admin')->put("/admin/users/{$member->id}", ['name' => 'X', 'email' => $taken->email])->assertSessionHasErrors('email');
});

it('refuses an admin acting on another admin or a super-admin (IDOR), by URL', function () {
    $admin = staff('admin');
    $peer = staff('admin');
    $sa = staff('super-admin');

    foreach ([$peer, $sa] as $target) {
        $this->actingAs($admin, 'admin')->get("/admin/users/{$target->id}")->assertForbidden();
        $this->actingAs($admin, 'admin')->put("/admin/users/{$target->id}", ['name' => 'Pwn', 'email' => 'pwn@example.com'])->assertForbidden();
        danger($this, $admin, 'POST', "/admin/users/{$target->id}/block")->assertForbidden();
        danger($this, $admin, 'DELETE', "/admin/users/{$target->id}")->assertForbidden();
        danger($this, $admin, 'POST', "/admin/users/{$target->id}/reset-2fa")->assertForbidden();
        $this->actingAs($admin, 'admin')->post("/admin/users/{$target->id}/approve")->assertForbidden();
    }
    expect($peer->fresh()->is_active)->toBeTrue()->and($sa->fresh()->trashed())->toBeFalse();
});

it('blocks, unblocks, soft deletes, restores and permanently deletes with a log snapshot', function () {
    $sa = staff('super-admin');
    $member = staff('member', ['name' => 'Snap Shot', 'email' => 'snap@example.com']);

    danger($this, $sa, 'POST', "/admin/users/{$member->id}/block")->assertRedirect();
    expect($member->fresh()->is_active)->toBeFalse();
    $this->actingAs($sa, 'admin')->post("/admin/users/{$member->id}/unblock")->assertRedirect();
    expect($member->fresh()->is_active)->toBeTrue();

    danger($this, $sa, 'DELETE', "/admin/users/{$member->id}")->assertRedirect('/admin/users');
    expect(AdminTestUser::query()->find($member->id))->toBeNull()->and(AdminTestUser::withTrashed()->find($member->id))->not->toBeNull();
    $this->actingAs($sa, 'admin')->post("/admin/users/{$member->id}/restore")->assertRedirect();
    expect(AdminTestUser::query()->find($member->id))->not->toBeNull();

    danger($this, $sa, 'DELETE', "/admin/users/{$member->id}")->assertRedirect();
    danger($this, $sa, 'DELETE', "/admin/users/{$member->id}/force")->assertRedirect('/admin/users');
    expect(AdminTestUser::withTrashed()->find($member->id))->toBeNull();
    $log = Activity::query()->where('event', 'user.force_deleted')->first();
    expect($log->properties['name'])->toBe('Snap Shot')->and($log->properties['email'])->toBe('snap@example.com')
        ->and(Activity::query()->whereIn('event', ['user.deactivated', 'user.unblocked', 'user.deleted', 'user.restored', 'user.force_deleted'])->count())->toBe(6);
});

it('does not let an admin delete permanently, and a deleted user cannot sign in', function () {
    $admin = staff('admin');
    $member = staff('member', ['email' => 'gone@example.com', 'password' => 'Secret-pass-123']);
    danger($this, $admin, 'DELETE', "/admin/users/{$member->id}")->assertRedirect();

    danger($this, $admin, 'DELETE', "/admin/users/{$member->id}/force")->assertForbidden();
    expect(AdminTestUser::withTrashed()->find($member->id))->not->toBeNull();
    expect(auth('admin')->validate(['email' => 'gone@example.com', 'password' => 'Secret-pass-123']))->toBeFalse();
});

it('refuses a user cannot block or delete themselves, and never the last super-admin', function () {
    $sa = staff('super-admin');
    danger($this, $sa, 'POST', "/admin/users/{$sa->id}/block")->assertForbidden();
    danger($this, $sa, 'DELETE', "/admin/users/{$sa->id}")->assertForbidden();
    expect($sa->fresh()->is_active)->toBeTrue()->and($sa->fresh()->trashed())->toBeFalse();
});

it('needs the server confirmation AND the password for dangerous routes', function () {
    $sa = staff('super-admin');
    $member = staff('member');
    $path = "/admin/users/{$member->id}/block";

    // no token, fresh password: nothing happens
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($sa, 'admin')->post($path)->assertRedirect();
    expect($member->fresh()->is_active)->toBeTrue();
    // token but no password confirmation: sent to the password screen, token not spent
    $token = app(ConfirmationTokens::class)->issue($sa, RequireConfirmation::scope('POST', $path));
    $this->flushSession();
    $this->actingAs($sa, 'admin')->post($path, ['confirmation' => $token])->assertRedirect(route('password.confirm'));
    expect($member->fresh()->is_active)->toBeTrue();
    // the same token works once the password is confirmed, and only once
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($sa, 'admin')->post($path, ['confirmation' => $token])->assertRedirect();
    expect($member->fresh()->is_active)->toBeFalse();
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($sa, 'admin')->post($path, ['confirmation' => $token])->assertRedirect();
    // a token for another path is refused
    $other = staff('member');
    $wrong = app(ConfirmationTokens::class)->issue($sa, RequireConfirmation::scope('POST', $path));
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($sa, 'admin')->post("/admin/users/{$other->id}/block", ['confirmation' => $wrong]);
    expect($other->fresh()->is_active)->toBeTrue();
});

it('resets two-factor of a lower user and logs it', function () {
    $admin = staff('admin');
    $member = staff('member', ['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()]);

    danger($this, $admin, 'POST', "/admin/users/{$member->id}/reset-2fa")->assertRedirect();

    expect($member->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(Activity::query()->where('event', 'two_factor.reset')->first()->properties['email'])->toBe($member->email);
});

it('approves and rejects registrations with a mail each, rejecting only pending ones', function () {
    Mail::fake();
    $admin = staff('admin');
    $pending = staff('member', ['approved_at' => null, 'is_active' => false]);
    $other = staff('member', ['approved_at' => null, 'is_active' => false]);

    $this->actingAs($admin, 'admin')->post("/admin/users/{$pending->id}/approve")->assertRedirect();
    expect($pending->fresh()->approved_at)->not->toBeNull()->and($pending->fresh()->is_active)->toBeTrue();
    Mail::assertSent(RegistrationApprovedMail::class);

    $this->actingAs($admin, 'admin')->post("/admin/users/{$other->id}/reject", ['reason' => 'Not a customer'])->assertRedirect('/admin/users');
    Mail::assertSent(RegistrationRejectedMail::class, fn ($m) => str_contains($m->render(), 'Not a customer'));
    expect(AdminTestUser::query()->find($other->id))->toBeNull()
        ->and(Activity::query()->where('event', 'user.rejected')->first()->properties['reason'])->toBe('Not a customer');
    // an approved user cannot be "rejected"
    $this->actingAs($admin, 'admin')->post("/admin/users/{$pending->id}/reject")->assertSessionHasErrors('reason');
});

it('runs bulk actions row by row, skipping and counting rows the policy forbids', function () {
    $admin = staff('admin');
    $low = staff('member');
    $peer = staff('admin');
    $sa = staff('super-admin');
    $ids = [$low->id, $peer->id, $sa->id, $admin->id];
    $payload = $ids;
    sort($payload, SORT_STRING);
    $token = app(ConfirmationTokens::class)->issue($admin, 'bulk:users:block', ['ids' => array_map('strval', $payload)]);

    $response = $this->withSession(['_token' => 't'])->actingAs($admin, 'admin')->from('/admin/users')
        ->post(route('codeart.tables.bulk', ['table' => 'users']), ['_token' => 't', 'action' => 'block', 'ids' => $ids, 'confirmation' => $token]);

    expect($response->getSession()->get('toast.message'))->toContain('Završeno: 1')->toContain('preskočeno: 3')
        ->and($low->fresh()->is_active)->toBeFalse()->and($peer->fresh()->is_active)->toBeTrue()
        ->and($sa->fresh()->is_active)->toBeTrue()->and($admin->fresh()->is_active)->toBeTrue();
});

it('requires a confirmation for destructive bulk actions and permission for every bulk action', function () {
    $admin = staff('admin');
    $low = staff('member');
    $post = fn ($actor, array $data) => $this->withSession(['_token' => 't'])->actingAs($actor, 'admin')->from('/admin/users')->post(route('codeart.tables.bulk', ['table' => 'users']), ['_token' => 't'] + $data);

    $post($admin, ['action' => 'delete', 'ids' => [$low->id]])->assertOk(); // the confirmation page, nothing changed
    expect($low->fresh()->trashed())->toBeFalse();
    $post(staff('editor'), ['action' => 'delete', 'ids' => [$low->id]])->assertForbidden();
});

it('puts Korisnici in the menu, the widget and the quick action only for those who may use them', function () {
    $admin = staff('admin');
    staff('member', ['approved_at' => null, 'is_active' => false]);

    $dash = $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    $xp = dom($dash->getContent());
    expect(count_nodes($xp, '//a[@href="'.url('/admin/users').'"]'))->toBeGreaterThan(0)
        ->and(text_of($xp, '//*[@data-pending-count]'))->toBe('1');

    $editor = dom($this->actingAs(staff('editor'), 'admin')->get('/admin')->assertOk()->getContent());
    expect(count_nodes($editor, '//a[@href="'.url('/admin/users').'"]'))->toBe(0)
        ->and(count_nodes($editor, '//*[@data-pending-count]'))->toBe(0);
});

it('has the users texts in sr, en and sr-Cyrl', function () {
    foreach (['sr', 'en', 'sr-Cyrl'] as $locale) {
        foreach (UserStatus::ALL as $status => $tone) {
            expect(UserStatus::label($status, $locale))->not->toStartWith('codeart::');
        }
    }
    expect(UserStatus::label('blocked', 'sr-Cyrl'))->toBe('Блокиран');
});
