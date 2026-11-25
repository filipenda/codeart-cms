<?php

namespace Codeart\Core\ActivityLog;

use Codeart\Core\Auth\Admin;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * The one way core and modules write to the activity log (a thin layer over spatie/laravel-activitylog).
 *
 * Adds the request's ip and user agent and removes anything that looks like a secret from the properties,
 * so a careless caller cannot put a password, token or recovery code into the log.
 */
class ActivityLog
{
    private const string SECRET_KEYS = '/pass|token|secret|recovery|code|credential|authorization|cookie|key|hash/i';

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, string $description, ?Model $subject = null, ?Model $causer = null, array $properties = [], string $log = 'auth'): void
    {
        try {
            $causer ??= Admin::currentUser() instanceof Model ? Admin::currentUser() : null;
            $request = request();
            $http = $request->route() !== null; // a real, routed request (not a console command)

            $logger = activity($log)->event($event)->withProperties($this->clean($properties))->tap(function (Activity $activity) use ($request, $http): void {
                $activity->ip_address = $http ? $request->ip() : null;
                $activity->user_agent = $http ? mb_substr((string) $request->userAgent(), 0, 255) : null;
            });
            if ($subject !== null) {
                $logger->performedOn($subject);
            }
            if ($causer !== null) {
                $logger->causedBy($causer);
            }
            $logger->log($description);
        } catch (Throwable $e) {
            // Never let logging break a sign-in, but do not swallow the problem either.
            report($e);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEYS, $key) === 1) {
                continue;
            }
            $out[$key] = is_array($value) ? $this->clean($value) : $value;
        }

        return $out;
    }
}
