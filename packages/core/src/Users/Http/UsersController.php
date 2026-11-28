<?php

namespace Codeart\Core\Users\Http;

use Codeart\Core\Auth\Actions\ApproveUser;
use Codeart\Core\Auth\Actions\DeactivateUser;
use Codeart\Core\Auth\Actions\ResetTwoFactor;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Users\Actions\DeleteUser;
use Codeart\Core\Users\Actions\ForceDeleteUser;
use Codeart\Core\Users\Actions\RejectUser;
use Codeart\Core\Users\Actions\RestoreUser;
use Codeart\Core\Users\Actions\UnblockUser;
use Codeart\Core\Users\Actions\UpdateUser;
use Codeart\Core\Users\UserStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Thin: every action is authorized by UserPolicy (403 when called directly) and delegated to an Action class. */
final class UsersController
{
    public function __construct(private readonly TableRegistry $tables) {}

    public function index(Request $request): View
    {
        $user = $request->user(Admin::guardName());
        Gate::forUser($user)->authorize('viewAny', Admin::userModel());
        $table = $this->tables->get('users') ?? abort(404);

        return view('codeart::admin.users.index', ['result' => $table->resolve($request, $user), 'pending' => $this->pendingCount()]);
    }

    public function edit(Request $request, int $id): View
    {
        $actor = $request->user(Admin::guardName());
        $user = $this->find($id);
        Gate::forUser($actor)->authorize('update', $user);

        return view('codeart::admin.users.edit', [
            'target' => $user,
            'status' => UserStatus::of($user),
            'roles' => $this->assignableRoles($actor, $user),
            'can' => collect(['changeRole', 'block', 'delete', 'restore', 'forceDelete', 'resetTwoFactor', 'approve'])
                ->mapWithKeys(fn (string $a): array => [$a => Gate::forUser($actor)->allows($a, $user)])->all(),
        ]);
    }

    public function update(Request $request, int $id, UpdateUser $update): RedirectResponse
    {
        $actor = $request->user(Admin::guardName());
        $user = $this->find($id);
        // Only these two fields are read: a `role`, `password`, `is_active` ... in the request is ignored here (role has its own route).
        $changes = $update($user, $request->only(['name', 'email']), $actor);

        return back()->with('toast', ['type' => 'success', 'message' => $changes === [] ? __('codeart::users.msg.nothing_changed') : __('codeart::users.msg.updated')]);
    }

    public function role(Request $request, int $id, UpdateUser $update): RedirectResponse
    {
        $actor = $request->user(Admin::guardName());
        $user = $this->find($id);
        $update($user, ['name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email'), 'role' => $request->input('role')], $actor);

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.role_changed')]);
    }

    public function block(Request $request, int $id, DeactivateUser $block): RedirectResponse
    {
        $block($this->find($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.blocked')]);
    }

    public function unblock(Request $request, int $id, UnblockUser $unblock): RedirectResponse
    {
        $unblock($this->find($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.unblocked')]);
    }

    public function destroy(Request $request, int $id, DeleteUser $delete): RedirectResponse
    {
        $delete($this->find($id), $request->user(Admin::guardName()));

        return redirect()->route('admin.users.index')->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.deleted')]);
    }

    public function restore(Request $request, int $id, RestoreUser $restore): RedirectResponse
    {
        $restore($this->find($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.restored')]);
    }

    public function forceDestroy(Request $request, int $id, ForceDeleteUser $force): RedirectResponse
    {
        $force($this->find($id), $request->user(Admin::guardName()));

        return redirect()->route('admin.users.index')->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.force_deleted')]);
    }

    public function resetTwoFactor(Request $request, int $id, ResetTwoFactor $reset): RedirectResponse
    {
        $reset($this->find($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.two_factor_reset')]);
    }

    public function approve(Request $request, int $id, ApproveUser $approve): RedirectResponse
    {
        $done = $approve($this->find($id), $request->user(Admin::guardName()));

        return back()->with('toast', ['type' => $done ? 'success' : 'info', 'message' => $done ? __('codeart::users.msg.approved') : __('codeart::users.msg.already_approved')]);
    }

    public function reject(Request $request, int $id, RejectUser $reject): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $done = $reject($this->find($id), $data['reason'] ?? null, $request->user(Admin::guardName()));
        if (! $done) {
            throw ValidationException::withMessages(['reason' => [__('codeart::users.msg.not_pending')]]);
        }

        return redirect()->route('admin.users.index')->with('toast', ['type' => 'success', 'message' => __('codeart::users.msg.rejected')]);
    }

    /** Unknown id: 404, whatever the actor may do (ids are not secret; the policy decides the rest). */
    private function find(int $id): Model
    {
        return Admin::usersWithTrashed()->findOrFail($id);
    }

    /** @return array<string, string> role => label that the actor may give to this user */
    private function assignableRoles(mixed $actor, Model $target): array
    {
        $out = [];
        foreach (Role::query()->where('guard_name', Admin::guardName())->pluck('name') as $name) {
            if (RoleRank::canAssign($actor, (string) $name)) {
                $out[(string) $name] = (string) __('codeart::users.roles.'.$name);
            }
        }

        return $out;
    }

    private function pendingCount(): int
    {
        return Admin::userModel()::query()->whereNull('approved_at')->count();
    }
}
