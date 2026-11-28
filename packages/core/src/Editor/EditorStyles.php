<?php

namespace Codeart\Core\Editor;

/** `codeart.editor_css`: stylesheets of the client site that the editor's content area loads (inside its shadow root). */
final class EditorStyles
{
    /** @return list<string> only absolute http(s) or root-relative URLs; anything else (javascript:, data:, //host) is ignored */
    public static function urls(): array
    {
        $urls = [];
        foreach ((array) config('codeart.editor_css', []) as $url) {
            if (is_string($url) && preg_match('~^(https?://[^\s"\'<>\\\\]+|/(?!/)[^\s"\'<>\\\\]*)$~i', trim($url)) === 1) {
                $urls[] = trim($url);
            }
        }

        return $urls;
    }
}
