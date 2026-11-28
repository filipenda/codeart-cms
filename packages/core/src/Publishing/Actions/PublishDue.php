<?php

namespace Codeart\Core\Publishing\Actions;

use Codeart\Core\Publishing\Publishables;
use Codeart\Core\Publishing\PublishStatus;

/** Publishes every scheduled model whose time has come. Called by the scheduler every minute; returns how many. */
final class PublishDue
{
    public function __construct(private readonly Publishables $publishables, private readonly PublishContent $publish) {}

    public function __invoke(): int
    {
        $count = 0;
        foreach ($this->publishables->all() as $class) {
            $class::query()->where('status', PublishStatus::Scheduled->value)->where('scheduled_at', '<=', now())
                ->chunkById(100, function ($models) use (&$count): void {
                    foreach ($models as $model) {
                        ($this->publish)($model);
                        $count++;
                    }
                });
        }

        return $count;
    }
}
