<?php

/** Parse a rendered page for assertions (HTML5 tags and inline SVG are tolerated). */
function dom(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

/** First node's attribute value, or null. */
function attr(DOMXPath $xp, string $query, string $attribute): ?string
{
    $node = $xp->query($query)->item(0);

    return $node instanceof DOMElement && $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : null;
}

/** Number of nodes matching. */
function count_nodes(DOMXPath $xp, string $query): int
{
    return $xp->query($query)->length;
}

/** Text of the first matching node (whitespace collapsed). */
function text_of(DOMXPath $xp, string $query): string
{
    return trim((string) preg_replace('/\s+/', ' ', (string) $xp->query($query)->item(0)?->textContent));
}

/** Every visible form control must have a <label for> or an aria-label: returns the controls that do not. @return list<string> */
function unlabelled_controls(DOMXPath $xp): array
{
    $missing = [];
    foreach ($xp->query('//input[not(@type="hidden") and not(@type="submit") and not(ancestor::template)]|//select|//textarea') as $control) {
        /** @var DOMElement $control */
        $id = $control->getAttribute('id');
        $hasLabel = $id !== '' && $xp->query('//label[@for="'.$id.'"]')->length > 0;
        $wrapped = $xp->query('ancestor::label', $control)->length > 0;
        $bound = $control->hasAttribute('x-bind:aria-label') || $control->hasAttribute(':aria-label');
        if (! $hasLabel && ! $wrapped && ! $bound && ! $control->hasAttribute('aria-label')) {
            $missing[] = $control->getAttribute('name') ?: $control->getAttribute('type');
        }
    }

    return $missing;
}
