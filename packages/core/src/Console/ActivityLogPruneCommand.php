<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPruneCommand extends Command
{
    protected $signature = 'activity-log:prune';

    protected $description = 'Delete activity log entries older than security.activity_log.retention_days (scheduled daily)';

    public function handle(Settings $settings): int
    {
        $days = (int) $settings->get('security.activity_log.retention_days');
        $deleted = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();
        $this->components->info("Removed {$deleted} entries older than {$days} days.");

        return self::SUCCESS;
    }
}
