<?php

namespace Codeart\Example\Listeners;

use Codeart\Core\Events\ModuleEnabled;
use Codeart\Example\Models\ExampleItem;

/** Runs when THIS module is enabled; ignores the enabling of any other module. */
class SeedWelcomeItem
{
    public function handle(ModuleEnabled $event): void
    {
        if ($event->module->name !== 'example') {
            return;
        }

        if (ExampleItem::query()->doesntExist()) {
            $item = ExampleItem::create();
            $item->setTranslation('sr', ['title' => 'Dobrodošla stavka', 'description' => 'Ljubav prema njivi.']);
            $item->setTranslation('en', ['title' => 'Welcome item', 'description' => 'Love for the field.']);
        }
    }
}
