<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Publishing\Publishable;
use Codeart\Core\Publishing\PublishableModel;
use Illuminate\Database\Eloquent\Model;

class PubItem extends Model implements PublishableModel
{
    use Publishable;

    protected $table = 'pub_items';

    protected $guarded = [];

    public function translatedAttributes(): array
    {
        return ['title', 'body', 'slug'];
    }

    public function permissionPrefix(): string
    {
        return 'pubitems';
    }

    public function formFields(): array
    {
        return ['title' => 'text', 'body' => 'textarea'];
    }
}
