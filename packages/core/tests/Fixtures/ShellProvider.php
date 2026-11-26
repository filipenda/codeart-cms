<?php

namespace Codeart\Core\Tests\Fixtures;

use Codeart\Core\Admin\Dashboard\QuickAction;
use Codeart\Core\Admin\Dashboard\QuickActions;
use Codeart\Core\Admin\Dashboard\Widget;
use Codeart\Core\Admin\Dashboard\WidgetData;
use Codeart\Core\Admin\Dashboard\WidgetRegistry;
use Codeart\Core\Admin\Navigation\AdminMenu;
use Codeart\Core\Admin\Navigation\NavItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\ServiceProvider;

class ShellProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AdminMenu::class)->add(
            new NavItem('shell', 'Shell content', 'file-text', order: 50, children: [
                new NavItem('shell.list', 'Shell list', 'list', 'shell.index', 'shell.view', 10),
                new NavItem('shell.settings', 'Shell settings', 'settings', 'shell.settings', 'shell.edit', 20),
            ]),
            // a group whose only child the user may not see: the group must disappear
            new NavItem('ghost', 'Ghost group', 'puzzle', order: 60, children: [
                new NavItem('ghost.secret', 'Ghost secret', 'lock', 'shell.settings', 'shell.edit'),
            ]),
            new NavItem('missing', 'Missing page', 'x', 'shell.does-not-exist', order: 70),
        );

        $this->app->make(WidgetRegistry::class)->add(
            new Widget('shell.late', 'Late widget', 'star', 'shell::widget', ShellWidgetData::class, 'shell.view', 'md', 40, 'shell'),
            new Widget('shell.early', 'Early widget', 'bolt', 'shell::widget', ShellWidgetData::class, 'shell.view', 'lg', 5, 'shell'),
            new Widget('shell.broken', 'Broken widget', 'bell', 'shell::widget', BrokenWidgetData::class, 'shell.view', 'sm', 25, 'shell'),
        );

        $this->app->make(QuickActions::class)->add(
            new QuickAction('shell.new', 'New shell thing', 'plus', 'shell.create', 'shell.edit', 10),
        );
    }
}

class ShellWidgetData implements WidgetData
{
    public function data(?Authenticatable $user): array
    {
        return ['text' => 'hello widget'];
    }
}

class BrokenWidgetData implements WidgetData
{
    public function data(?Authenticatable $user): array
    {
        throw new \RuntimeException('secret failure detail');
    }
}
