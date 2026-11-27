<?php

namespace Codeart\Core\Admin\Demo;

use Codeart\Core\ActivityLog\ActivityDescription;
use Codeart\Core\Tables\BulkAction;
use Codeart\Core\Tables\Column;
use Codeart\Core\Tables\DataTable;
use Codeart\Core\Tables\Filter;
use Codeart\Core\Tables\RowAction;
use Codeart\Core\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Demo table of the component showcase (`/admin/_ui`), backed by the activity log: read-only apart from a no-op bulk action.
 * Registered as `ui-activity`; like the showcase it answers 404 outside the local environment (checked when it is used).
 */
final class ActivityLogTable implements TableDefinition
{
    public const string KEY = 'ui-activity';

    public function table(): DataTable
    {
        abort_unless(app()->environment('local'), 404);

        $logs = Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all();

        return DataTable::make(self::KEY, Activity::query())
            ->columns(
                Column::number('id', 'ID'),
                Column::titleSubtitle('description', 'Activity')->using(fn (Activity $a): array => ['title' => ActivityDescription::for($a->event, (string) $a->description), 'subtitle' => $a->log_name]),
                Column::badge('event', 'Event'),
                Column::avatar('causer_id', 'User')->using(function (Activity $a): array {
                    $causer = $a->causer;

                    return ['name' => $causer instanceof Model ? (string) ($causer->getAttribute('name') ?? '') : '', 'subtitle' => $causer instanceof Model ? (string) ($causer->getAttribute('email') ?? '') : ''];
                })->searchable(false),
                Column::text('ip_address', 'IP address'),
                Column::date('created_at', 'When'),
            )
            ->filters(Filter::select('log_name', 'Log', $logs), Filter::dateRange('created_at', 'When'))
            ->with('causer')
            ->defaultSort('created_at', 'desc')
            ->permission('manage-modules')
            ->export('activity-log')
            ->rowActions(RowAction::make('details', 'Details', 'eye', fn (Model $a): string => route('codeart.ui-showcase').'#activity-'.$a->getKey(), 'manage-modules'))
            ->bulkActions(BulkAction::make('review', 'Mark as reviewed (does nothing)', fn (): bool => true, 'manage-modules', 'This demo action changes nothing. Run it?'));
    }
}
