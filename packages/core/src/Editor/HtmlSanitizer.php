<?php

namespace Codeart\Core\Editor;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Throwable;

/**
 * Server-side allowlist sanitizer for rich text (docs/editor.md). Nothing the browser sends is trusted.
 *
 * The input is parsed by PHP's native HTML5 parser (Dom\HTMLDocument, the same algorithm browsers use) and the output is
 * written from scratch: only allowed elements and attributes are emitted, text and attribute values are escaped by us.
 * It never copies markup through, so there is no serialization round trip to exploit (mXSS), and running it on its own
 * output returns the same string. Unknown elements are unwrapped (their text stays), dangerous ones are dropped with their
 * content, anything outside the HTML namespace (svg, math) is dropped entirely.
 *
 * Images may carry `data-media-id`; when that media exists, src/srcset/width/height are rewritten from the media library
 * (so content survives renamed or moved files), otherwise the id is dropped and the image is kept only if its own src is safe.
 */
final class HtmlSanitizer
{
    private const string HTML_NS = 'http://www.w3.org/1999/xhtml';

    /** @var list<string> */
    private const array VOID = ['br', 'hr', 'img', 'col'];

    private const array ALLOWED = [
        'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ul', 'ol', 'li', 'blockquote', 'a', 'img',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col', 'caption', 'hr', 'code', 'pre', 'figure', 'figcaption', 'span',
    ];

    /** Dropped together with everything inside them. */
    private const array DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'noframes', 'object', 'embed', 'applet', 'template', 'noscript', 'textarea',
        'form', 'select', 'option', 'input', 'button', 'head', 'title', 'meta', 'link', 'base', 'audio', 'video', 'canvas', 'xmp',
        'plaintext', 'listing', 'dialog', 'portal', 'slot', 'datalist', 'fieldset', 'optgroup', 'map', 'area', 'param', 'source', 'track',
    ];

    private const array URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const array REL = ['nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc'];

    /** @var list<int> media ids found in the last run (existing ones only) */
    private array $ids = [];

    /** @var array<int, object|null> */
    private array $mediaCache = [];

    public static function clean(?string $html): string
    {
        return (new self)->run($html)['html'];
    }

    /** @return list<int> ids of the media library files the (sanitized) content really uses */
    public static function mediaIds(?string $html): array
    {
        return (new self)->run($html)['ids'];
    }

    /**
     * Sanitizes the fields typed `editor` of a `translations[locale][field]` payload.
     *
     * @param  array<string, mixed>  $translations
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function cleanTranslations(array $translations, array $fields): array
    {
        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }
            foreach ($fields as $field) {
                if (isset($values[$field]) && is_string($values[$field])) {
                    $translations[$locale][$field] = self::clean($values[$field]);
                }
            }
        }

        return $translations;
    }

    /** @return array{html: string, ids: list<int>} */
    public function run(?string $html): array
    {
        $this->ids = [];
        $html = trim((string) $html);
        if ($html === '') {
            return ['html' => '', 'ids' => []];
        }
        $html = mb_scrub($html, 'UTF-8');
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>', LIBXML_NOERROR);
        $body = $document->body;
        $out = $body === null ? '' : $this->children($body);

        return ['html' => trim($out), 'ids' => array_values(array_unique($this->ids))];
    }

    private function children(Node $parent): string
    {
        $out = '';
        foreach ($parent->childNodes as $child) {
            $out .= $this->node($child);
        }

        return $out;
    }

    private function node(Node $node): string
    {
        if ($node instanceof Text) {
            return $this->text($node->data);
        }
        if (! $node instanceof Element) {
            return ''; // comments, processing instructions, CDATA
        }
        if ($node->namespaceURI !== self::HTML_NS) {
            return ''; // svg, math and friends: namespace confusion is the root of mXSS
        }
        $tag = strtolower($node->localName);
        if (in_array($tag, self::DROP, true)) {
            return '';
        }
        if (! in_array($tag, self::ALLOWED, true)) {
            return $this->children($node); // unwrap
        }

        $attributes = $this->attributes($tag, $node);
        if ($attributes === null) {
            return '';
        }
        if ($tag === 'a' && ! str_contains($attributes, ' href=')) {
            return $this->children($node); // a link without a safe address is just its text
        }
        $open = '<'.$tag.$attributes.'>';
        if (in_array($tag, self::VOID, true)) {
            return $open;
        }
        $inner = $this->children($node);
        if ($tag === 'pre' && str_starts_with($inner, "\n")) {
            $inner = "\n".$inner; // the parser drops one leading newline of <pre>
        }

        return $open.$inner.'</'.$tag.'>';
    }

    private function text(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    /** @return string|null the attribute string (with a leading space), null = drop the element */
    private function attributes(string $tag, Element $node): ?string
    {
        $get = fn (string $name): ?string => $node->hasAttribute($name) ? $node->getAttribute($name) : null;
        $out = [];

        if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'td', 'th'], true) && ($align = $this->align($get('style'))) !== null) {
            $out['style'] = 'text-align: '.$align;
        }

        switch ($tag) {
            case 'a':
                $href = $this->url($get('href'), allowMailTel: true);
                if ($href !== null) {
                    $out['href'] = $href;
                    if (($title = $this->plain($get('title'), 300)) !== '') {
                        $out['title'] = $title;
                    }
                    $blank = $get('target') !== null && strtolower(trim((string) $get('target'))) === '_blank';
                    $rel = array_filter(preg_split('/\s+/', strtolower((string) $get('rel')), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn (string $r): bool => in_array($r, self::REL, true));
                    if ($blank) {
                        $out['target'] = '_blank';
                        $rel = [...$rel, 'noopener', 'noreferrer'];
                    }
                    $rel = array_values(array_filter(self::REL, fn (string $r): bool => in_array($r, $rel, true)));
                    if ($rel !== []) {
                        $out['rel'] = implode(' ', $rel);
                    }
                }
                break;
            case 'img':
                $image = $this->image($node);
                if ($image === null) {
                    return null;
                }
                $out = [...$out, ...$image];
                break;
            case 'td':
            case 'th':
                foreach (['colspan', 'rowspan'] as $name) {
                    $v = $get($name);
                    if ($v !== null && preg_match('/^\d{1,4}$/', trim($v)) === 1 && (int) $v >= 1) {
                        $out[$name] = (string) (int) $v;
                    }
                }
                break;
            case 'ol':
                $start = $get('start');
                if ($start !== null && preg_match('/^-?\d{1,6}$/', trim($start)) === 1) {
                    $out['start'] = (string) (int) $start;
                }
                break;
        }

        $string = '';
        foreach ($out as $name => $value) {
            $string .= ' '.$name.'="'.str_replace(['&', '"', '<', '>'], ['&amp;', '&quot;', '&lt;', '&gt;'], $value).'"';
        }

        return $string;
    }

    /** @return array<string, string>|null */
    private function image(Element $node): ?array
    {
        $get = fn (string $name): ?string => $node->hasAttribute($name) ? $node->getAttribute($name) : null;
        $out = [];
        $media = null;
        $id = $get('data-media-id');
        if ($id !== null && preg_match('/^\d{1,18}$/', trim($id)) === 1) {
            $media = $this->media((int) $id);
        }
        $alt = $this->plain($get('alt'), 500);

        if ($media !== null) {
            $out['data-media-id'] = (string) $media->getKey();
            $out['src'] = (string) $media->url();
            $srcset = $media->srcset();
            if (is_string($srcset) && $srcset !== '') {
                $out['srcset'] = $this->srcset($srcset) ?? '';
            }
            if ($media->width && $media->height) {
                $out['width'] = (string) (int) $media->width;
                $out['height'] = (string) (int) $media->height;
            }
            $this->ids[] = (int) $media->getKey();
        } else {
            $src = $this->url($get('src'), allowMailTel: false);
            if ($src === null) {
                return null;
            }
            $out['src'] = $src;
            if (($srcset = $this->srcset($get('srcset'))) !== null && $srcset !== '') {
                $out['srcset'] = $srcset;
            }
            foreach (['width', 'height'] as $name) {
                $v = $get($name);
                if ($v !== null && preg_match('/^\d{1,5}$/', trim($v)) === 1 && (int) $v > 0) {
                    $out[$name] = (string) (int) $v;
                }
            }
        }
        if (($out['srcset'] ?? null) === '') {
            unset($out['srcset']);
        }
        $out['alt'] = $alt;
        $sizes = $get('sizes');
        if ($sizes !== null && preg_match('/^[a-z0-9\s.,()%<>=\-+\/*]{1,200}$/i', trim($sizes)) === 1 && ! str_contains($sizes, '/*')) {
            $out['sizes'] = trim($sizes);
        }
        if (($title = $this->plain($get('title'), 300)) !== '') {
            $out['title'] = $title;
        }
        $loading = strtolower(trim((string) $get('loading')));
        if (in_array($loading, ['lazy', 'eager'], true)) {
            $out['loading'] = $loading;
        }

        return $out;
    }

    private function media(int $id): ?object
    {
        if (array_key_exists($id, $this->mediaCache)) {
            return $this->mediaCache[$id];
        }
        $class = 'Codeart\\Core\\Media\\Media';
        $media = null;
        if (class_exists($class)) {
            try {
                $media = $class::query()->find($id);
            } catch (Throwable) {
                $media = null; // no media table (yet): treat as missing
            }
        }

        return $this->mediaCache[$id] = $media;
    }

    private function srcset(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $items = [];
        foreach (explode(',', $value) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts === [] || count($parts) > 2) {
                return null;
            }
            $url = $this->url($parts[0], allowMailTel: false);
            if ($url === null || str_contains($url, ',') || str_contains($url, ' ')) {
                return null;
            }
            if (isset($parts[1]) && preg_match('/^\d{1,5}(\.\d+)?[wx]$/', $parts[1]) !== 1) {
                return null;
            }
            $items[] = $url.(isset($parts[1]) ? ' '.$parts[1] : '');
        }

        return implode(', ', $items);
    }

    /** `text-align` is the only inline style that survives. */
    private function align(?string $style): ?string
    {
        if ($style !== null && preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', $style, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function plain(?string $value, int $max): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $value);

        return trim(mb_substr($value, 0, $max));
    }

    /**
     * A URL is accepted when it is relative (path, query, fragment) or uses http, https, mailto (links) or tel (links).
     * The scheme is judged on a copy with every invisible character removed, the way browsers read it.
     */
    private function url(?string $value, bool $allowMailTel): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value));
        if ($value === '' || mb_strlen($value) > 2048) {
            return null;
        }
        if (! $allowMailTel && preg_match('/\s/u', $value) === 1) {
            return null; // image addresses never contain spaces (also keeps srcset lists out of src)
        }
        $probe = (string) preg_replace('/[\s\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{206F}\x{3000}\x{FEFF}]+/u', '', $value);
        $probe = strtolower($probe);
        if (preg_match('/^([^:\/?#]*):/', $probe, $m) === 1) {
            $allowed = $allowMailTel ? self::URL_SCHEMES : ['http', 'https'];
            if (! in_array($m[1], $allowed, true)) {
                return null;
            }
            if (preg_match('/^(https?):\/\/[^\/?#\s]+/', $probe) !== 1 && in_array($m[1], ['http', 'https'], true)) {
                return null;
            }
        } elseif (str_starts_with($probe, '\\') || str_contains($probe, '\\')) {
            return null; // browsers treat a backslash like a slash: "\\evil.com"
        }

        return $value;
    }
}
