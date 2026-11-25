<?php

namespace Codeart\Example\Listeners;

use Codeart\Core\Events\ModuleEnabled;
use Illuminate\Support\Facades\DB;

/** Runs when THIS module is enabled; ignores the enabling of any other module. */
class SeedWelcomeItem
{
    public function handle(ModuleEnabled $event): void
    {
        if ($event->module->name !== 'example') {
            return;
        }

        if (DB::table('example_items')->doesntExist()) {
            DB::table('example_items')->insert([
                'title' => 'Welcome item',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
