<?php

namespace Codeart\Core\Images;

/** What is known about an image from its bytes alone. */
final readonly class ImageInfo
{
    public function __construct(
        public string $mime,
        public int $width,
        public int $height,
    ) {}

    /** png, jpg, webp or null when the type is not one we process. */
    public function extension(): ?string
    {
        return ImageFormat::fromMime($this->mime)?->value;
    }

    /** Only these formats can carry an alpha channel. */
    public function canHaveAlpha(): bool
    {
        return in_array($this->mime, ['image/png', 'image/webp'], true);
    }
}
