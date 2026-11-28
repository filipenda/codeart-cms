<?php

namespace Codeart\Core\Publishing;

/** Where a piece of content is in its life: only `published` is visible on the public site. */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return (string) __('codeart::publishing.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Scheduled => 'warning',
        };
    }

    /** @return array<string, array{0: string, 1: string}> value => [label, tone], for table columns */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = [$case->label(), $case->tone()];
        }

        return $out;
    }
}
