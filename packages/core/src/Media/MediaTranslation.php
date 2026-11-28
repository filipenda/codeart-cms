<?php

namespace Codeart\Core\Media;

use Codeart\Core\Translations\Translation;

/**
 * @property string|null $alt
 * @property string|null $title
 */
class MediaTranslation extends Translation
{
    protected $table = 'media_translations';
}
