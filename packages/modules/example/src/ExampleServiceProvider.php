<?php

namespace Codeart\Example;

use Codeart\Core\Events\ModuleEnabled;
use Codeart\Example\Listeners\SeedWelcomeItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Core registers this provider only while the module is enabled, and loads routes/web.php,
 * resources/views and the migrations path from the manifest. Nothing to wire here but events.
 */
class ExampleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(ModuleEnabled::class, SeedWelcomeItem::class);
    }
}
