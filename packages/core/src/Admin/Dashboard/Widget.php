<?php

namespace Codeart\Core\Admin\Dashboard;

/**
 * A dashboard widget. `view` is a Blade view that receives `$widget`, `$data` and `$user`;
 * `provider` is a class implementing WidgetData. `size`: sm (3 of 12 columns), md (6) or lg (12) on desktop.
 */
final readonly class Widget
{
    public const array SIZES = ['sm', 'md', 'lg'];

    /**
     * @param  class-string<WidgetData>  $provider
     * @param  string  $permission  null = everyone who may enter the admin
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $view,
        public string $provider,
        public ?string $permission = null,
        public string $size = 'md',
        public int $order = 100,
        public ?string $module = null,
    ) {
        if (! in_array($size, self::SIZES, true)) {
            throw new \InvalidArgumentException("Widget \"{$key}\": size must be sm, md or lg, got \"{$size}\".");
        }
    }
}
