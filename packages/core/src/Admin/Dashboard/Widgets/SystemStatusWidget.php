<?php

namespace Codeart\Core\Admin\Dashboard\Widgets;

use Codeart\Core\Admin\Dashboard\WidgetData;
use Codeart\Core\Admin\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Real facts only: the installed version, whether the database answers, whether storage is writable. */
class SystemStatusWidget implements WidgetData
{
    public function data(?Authenticatable $user): array
    {
        return ['checks' => [
            ['label' => 'version', 'value' => Version::cms(), 'ok' => null],
            ['label' => 'php', 'value' => PHP_VERSION, 'ok' => null],
            ['label' => 'database', 'value' => null, 'ok' => $this->databaseReachable()],
            ['label' => 'storage', 'value' => null, 'ok' => $this->storageWritable()],
        ]];
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->selectOne('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function storageWritable(): bool
    {
        foreach ([storage_path('app'), storage_path('framework'), storage_path('logs')] as $dir) {
            if (! is_dir($dir) || ! is_writable($dir)) {
                return false;
            }
        }

        return true;
    }
}
