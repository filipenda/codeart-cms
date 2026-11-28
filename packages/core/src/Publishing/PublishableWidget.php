<?php

namespace Codeart\Core\Publishing;

use Codeart\Core\Admin\Dashboard\WidgetData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Real numbers only: drafts and scheduled items of one publishable model. View: `codeart::admin.widgets.publishable`. */
abstract class PublishableWidget implements WidgetData
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    public function data(?Authenticatable $user): array
    {
        $class = $this->model();
        $counts = $class::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'drafts' => (int) ($counts[PublishStatus::Draft->value] ?? 0),
            'scheduled' => (int) ($counts[PublishStatus::Scheduled->value] ?? 0),
            'published' => (int) ($counts[PublishStatus::Published->value] ?? 0),
        ];
    }
}
