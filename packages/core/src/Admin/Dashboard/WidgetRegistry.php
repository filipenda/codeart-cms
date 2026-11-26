<?php

namespace Codeart\Core\Admin\Dashboard;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Dashboard widgets registered by core and by the providers of enabled modules. */
class WidgetRegistry
{
    /** @var array<string, Widget> */
    private array $widgets = [];

    public function __construct(private readonly Gate $gate, private readonly Container $container) {}

    public function add(Widget ...$widgets): void
    {
        foreach ($widgets as $widget) {
            $this->widgets[$widget->key] = $widget;
        }
    }

    /** @return list<Widget> the widgets the user may see, by order */
    public function visible(?Authenticatable $user): array
    {
        $widgets = array_filter($this->widgets, fn (Widget $w): bool => $w->permission === null
            || ($user !== null && $this->gate->forUser($user)->allows($w->permission)));
        usort($widgets, fn (Widget $a, Widget $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $widgets;
    }

    /**
     * Render the visible widgets. A widget that throws is logged and replaced by a small error card; one that
     * returns null from its provider is left out. The rest of the dashboard is never affected.
     *
     * @return list<RenderedWidget>
     */
    public function render(?Authenticatable $user): array
    {
        $out = [];
        foreach ($this->visible($user) as $widget) {
            try {
                $data = $this->container->make($widget->provider)->data($user);
                if ($data === null) {
                    continue;
                }
                $html = view($widget->view, ['widget' => $widget, 'data' => $data, 'user' => $user])->render();
                $out[] = new RenderedWidget($widget, $html);
            } catch (Throwable $e) {
                Log::error('Dashboard widget "'.$widget->key.'" failed: '.$e->getMessage(), ['widget' => $widget->key, 'exception' => $e]);
                $out[] = new RenderedWidget($widget, view('codeart::admin.widgets.error', ['widget' => $widget])->render(), true);
            }
        }

        return $out;
    }
}
