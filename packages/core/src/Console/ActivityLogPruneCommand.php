<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Privacy\IpPrivacy;
use Codeart\Core\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPruneCommand extends Command
{
    protected $signature = 'activity-log:prune';

    protected $description = 'Delete activity log entries older than security.activity_log.retention_days (scheduled daily)';

    public function handle(Settings $settings, IpPrivacy $privacy): int
    {
        $days = (int) $settings->get('security.activity_log.retention_days');
        $deleted = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();
        $this->components->info("Removed {$deleted} entries older than {$days} days.");

        $this->applyIpPrivacy($privacy);

        return self::SUCCESS;
    }

    /** Brings IPs stored earlier in line with `security.ip_storage` (removes or anonymizes them in the rows that stay). */
    private function applyIpPrivacy(IpPrivacy $privacy): void
    {
        if ($privacy->mode() === IpPrivacy::FULL) {
            return;
        }
        foreach ([['activity_log', 'id', 'ip_address'], ['sessions', 'id', 'ip_address'], [(new (Admin::userModel()))->getTable(), 'id', 'last_login_ip']] as [$table, $key, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }
            if ($privacy->mode() === IpPrivacy::OFF) {
                DB::table($table)->whereNotNull($column)->update([$column => null]);

                continue;
            }
            DB::table($table)->whereNotNull($column)->orderBy($key)->chunkById(500, function ($rows) use ($table, $key, $column, $privacy): void {
                foreach ($rows as $row) {
                    $new = $privacy->store($row->{$column});
                    if ($new !== $row->{$column}) {
                        DB::table($table)->where($key, $row->{$key})->update([$column => $new]);
                    }
                }
            }, $key);
        }
    }
}
