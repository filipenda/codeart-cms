<?php

namespace Codeart\Core\Auth\Http;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A user's own sessions: list, revoke one, sign out everywhere else. */
class SessionsController
{
    public function __construct(private readonly UserSessions $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user(Admin::guardName());

        return response()->json(['data' => $this->sessions->list($user->getAuthIdentifier(), $request->session()->getId())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user(Admin::guardName());
        abort_if($id === $request->session()->getId(), 422, 'Use logout to end the current session.');
        abort_unless($this->sessions->revoke($user->getAuthIdentifier(), $id), 404);

        return response()->json(['revoked' => true]);
    }

    /** Needs the password again; also rotates the remember token so "remember me" cookies elsewhere stop working. */
    public function destroyOthers(Request $request, ActivityLog $log): JsonResponse
    {
        $user = $request->user(Admin::guardName());
        $request->validate(['password' => ['required', 'string', 'current_password:'.Admin::guardName()]], [], __('codeart::auth.attributes'));

        $count = $this->sessions->revokeOthers($user->getAuthIdentifier(), $request->session()->getId());
        Admin::guard()->logoutOtherDevices((string) $request->input('password'));
        $log->record('session.others_revoked', 'Signed out of other devices', $user, $user, ['sessions' => $count]);

        return response()->json(['revoked' => $count]);
    }
}
