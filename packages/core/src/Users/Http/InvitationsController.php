<?php

namespace Codeart\Core\Users\Http;

use Codeart\Core\Auth\Actions\InviteUser;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Users\Actions\ResendInvitation;
use Codeart\Core\Users\Actions\RevokeInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** The only way to add a person. Sending is throttled (route); the token goes into the mail only. */
final class InvitationsController
{
    public function __construct(private readonly TableRegistry $tables) {}

    public function index(Request $request): View
    {
        $user = $request->user(Admin::guardName());
        Gate::forUser($user)->authorize('invite', Admin::userModel());
        $table = $this->tables->get('user-invitations') ?? abort(404);

        return view('codeart::admin.users.invitations', ['result' => $table->resolve($request, $user)]);
    }

    public function create(Request $request): View
    {
        Gate::forUser($request->user(Admin::guardName()))->authorize('invite', Admin::userModel());

        return view('codeart::admin.users.invite', ['roles' => $this->assignable($request->user(Admin::guardName()))]);
    }

    public function store(Request $request, InviteUser $invite): RedirectResponse
    {
        $actor = $request->user(Admin::guardName());
        Gate::forUser($actor)->authorize('invite', Admin::userModel());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_keys($this->assignable($actor)))],
            'message' => ['nullable', 'string', 'max:500'],
        ], [], __('codeart::auth.attributes'));

        $invite($data['name'], $data['email'], [$data['role']], $actor, $data['message'] ?? null);

        // The same answer whether or not the address is known to the system is not possible for a taken address
        // (the admin must be told), but nothing about the account itself is revealed.
        return redirect()->route('admin.users.invitations')->with('toast', ['type' => 'success', 'message' => __('codeart::users.invitation.sent')]);
    }

    public function resend(Request $request, int $id, ResendInvitation $resend): RedirectResponse
    {
        $resend(UserInvitation::query()->whereNull('accepted_at')->findOrFail($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.invitation.resent')]);
    }

    public function revoke(Request $request, int $id, RevokeInvitation $revoke): RedirectResponse
    {
        $revoke(UserInvitation::query()->whereNull('accepted_at')->findOrFail($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.invitation.revoked')]);
    }

    /** @return array<string, string> */
    private function assignable(mixed $actor): array
    {
        $out = [];
        foreach (Role::query()->where('guard_name', Admin::guardName())->pluck('name') as $name) {
            if (RoleRank::canAssign($actor, (string) $name)) {
                $out[(string) $name] = (string) __('codeart::users.roles.'.$name);
            }
        }

        return $out;
    }
}
