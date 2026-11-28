<?php

namespace Codeart\Core\Publishing;

use Illuminate\Database\Eloquent\Model;

/** The models that use `Publishable`, so the scheduler knows what to look at. Modules register theirs in their provider. */
class Publishables
{
    /** @var array<class-string<Model>, true> */
    private array $models = [];

    /** @param  class-string<Model>  ...$models */
    public function register(string ...$models): void
    {
        foreach ($models as $model) {
            $this->models[$model] = true;
        }
    }

    /** @return list<class-string<Model>> */
    public function all(): array
    {
        return array_keys($this->models);
    }
}
