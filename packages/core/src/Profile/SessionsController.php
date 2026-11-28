<?php

namespace Codeart\Core\Profile;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserSessions;
use Codeart\Core\Support\DisplayTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The "Sesije" tab of Moj profil. Works only with the database session driver (404 otherwise, and the tab is not drawn).
 * A user sees and ends only their OWN sessions; the current session is never ended here (that is "log out"); the real
 * session id never reaches the HTML (see SessionHandle). Ending sessions needs the server confirmation and a fresh password.
 */
final class SessionsController
{
    public function __construct(private readonly UserSessions $sessions, private readonly ActivityLog $log) {}

    public static function enabled(): bool
    {
        return config('session.driver') === 'database';
    }

    public function show(Request $request, DisplayTime $time): View
    {
        $user = $this->me($request);
        $current = $request->session()->getId();
        $rows = array_map(function (array $row) use ($time, $user): array {
            $ua = UserAgent::parse($row['user_agent']);

            return $row + [
                'ua' => $ua,
                'handle' => SessionHandle::for($row['id'], $user->getKey()),
                'relative' => $time->relative($row['last_active']),
                'exact' => $time->format($row['last_active']),
            ];
        }, $this->sessions->list($user->getKey(), $current));
        usort($rows, fn (array $a, array $b): int => [$b['current'], $b['last_active']->getTimestamp()] <=> [$a['current'], $a['last_active']->getTimestamp()]);

        return view('codeart::admin.profile.sessions', ['sessions' => $rows, 'others' => count(array_filter($rows, fn (array $r): bool => ! $r['current']))] + $this->shell($request));
    }

    public function destroy(Request $request, string $handle): RedirectResponse
    {
        $user = $this->me($request);
        $current = $request->session()->getId();
        foreach ($this->sessions->list($user->getKey(), $current) as $row) {
            if (! SessionHandle::matches($handle, $row['id'], $user->getKey())) {
                continue;
            }
            abort_if($row['current'], 422, __('codeart::profile.ses_current_protected'));
            $this->sessions->revoke($user->getKey(), $row['id']);
            $ua = UserAgent::parse($row['user_agent']);
            $this->log->record('session.revoked', 'Session terminated', $user, $user, ['browser' => $ua['browser'], 'os' => $ua['os'], 'ip' => $row['ip_address']], 'users');

            return $this->back('ses_ended');
        }

        abort(404); // not this user's session (or already gone)
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $user = $this->me($request);
        $count = $this->sessions->revokeOthers($user->getKey(), $request->session()->getId());
        // "Remember me" cookies of the other devices stop working too.
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        $this->log->record('session.others_revoked', 'Signed out of other devices', $user, $user, ['sessions' => $count], 'users');

        return $this->back('ses_all_ended');
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('admin.profile.sessions')->with('toast', ['type' => 'success', 'message' => __('codeart::profile.'.$message)]);
    }

    /** @return array<string, mixed> */
    private function shell(Request $request): array
    {
        $user = $this->me($request);
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        usort($roles, fn ($a, $b) => RoleRank::of($b) <=> RoleRank::of($a));

        return ['me' => $user, 'role' => $roles[0] ?? null, 'avatarUrl' => Avatar::url($user)];
    }

    private function me(Request $request): Model
    {
        abort_unless(self::enabled(), 404);
        $user = $request->user(Admin::guardName());

        return $user instanceof Model ? $user : throw new \RuntimeException('No signed-in user.');
    }
}
