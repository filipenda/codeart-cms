<?php

namespace Codeart\Core\Images;

/**
 * The ONLY door to an image library. Everything else in the code base asks this service
 * (an architecture test enforces it). Works on image bytes, so callers stay storage-agnostic.
 * Later: thumbnails, WebP conversion, watermark.
 */
interface ImageProcessor
{
    /** Type and size from the content (never from a name); null when it is not a readable png/jpg/webp. */
    public function inspect(string $bytes): ?ImageInfo;

    /**
     * Cuts the transparent margins of a PNG/WebP: the tight box of all pixels whose alpha is above the tolerance.
     * Returns the new bytes (same format), or null when nothing should change: JPG, no alpha, nothing to cut,
     * fully transparent, or a tiny result.
     */
    public function trimTransparent(string $bytes): ?string;

    /** Scales down to fit within the box, keeping the ratio. Never enlarges. */
    public function fit(string $bytes, int $maxWidth, int $maxHeight): string;

    /** Encodes into the format; `$quality` (1-100) matters for jpg and webp. */
    public function encode(string $bytes, ImageFormat $format, int $quality = 90): string;

    /** Like inspect(), but GIF is accepted too (media library). Reads only the header, never decodes the pixels. */
    public function probe(string $bytes): ?ImageInfo;

    /**
     * Re-encodes the image so every metadata block (EXIF with GPS, XMP, comments) is gone and the camera rotation is applied.
     * GIF is returned unchanged (no EXIF; re-encoding would drop the animation).
     */
    public function stripMetadata(string $bytes): string;

    /** Name of the driver in use: gd or imagick. */
    public function driver(): string;
}
