<?php

use Codeart\Core\Editor\HtmlSanitizer;
use Codeart\Core\Translations\SerbianLatinToCyrillic;

function clean(?string $html): string
{
    return HtmlSanitizer::clean($html);
}

it('keeps the allowed formatting', function () {
    $html = '<h2>Naslov</h2><p>Tekst <strong>bold</strong> <em>kurziv</em> <u>pod</u> <s>pre</s> <code>x</code></p>'
        .'<ul><li>a</li><li>b</li></ul><ol start="3"><li>c</li></ol><blockquote><p>q</p></blockquote><hr>'
        .'<table><thead><tr><th colspan="2">h</th></tr></thead><tbody><tr><td>1</td><td style="text-align: center">2</td></tr></tbody></table>'
        .'<pre><code>a &lt; b</code></pre><p><a href="https://example.com/x?a=1&amp;b=2" title="T">link</a></p>';

    expect(clean($html))->toBe($html);
});

it('removes scripts, handlers and dangerous containers with their content', function (string $payload) {
    $out = clean($payload);

    expect($out)->not->toMatch('/<\s*(script|iframe|object|embed|svg|math|style|form|input|link|meta|base|template|textarea|noscript)/i')
        ->not->toMatch('/\son\w+\s*=/i')
        ->not->toMatch('/javascript\s*:/i')
        ->not->toMatch('/data\s*:/i');
    preg_match_all('/<\/?([a-z0-9]+)/i', $out, $tags);
    expect(array_diff(array_map('strtolower', $tags[1]), ['p', 'br', 'a', 'img', 'b', 'i', 'em', 'strong', 'table', 'tbody', 'tr', 'td', 'th', 'thead', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'hr', 'pre', 'code', 'span', 'u', 's']))->toBe([]);
})->with([
    '<script>alert(1)</script>',
    '<p>a</p><script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '<img src="x" onerror="alert(1)" alt="a">',
    '<a href="javascript:alert(1)">x</a>',
    '<a href=" JaVa&#x09;ScRiPt:alert(1)">x</a>',
    '<a href="java&#10;script:alert(1)">x</a>',
    '<a href="&#106;avascript:alert(1)">x</a>',
    '<a href="jav&#x61;script&colon;alert(1)">x</a>',
    "<a href=\"\x01javascript:alert(1)\">x</a>",
    "<a href=\"\u{200B}javascript:alert(1)\">x</a>",
    '<a href="vbscript:msgbox(1)">x</a>',
    '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>',
    '<img src="data:image/svg+xml;base64,PHN2Zz48c2NyaXB0PmFsZXJ0KDEpPC9zY3JpcHQ+PC9zdmc+">',
    '<svg><script>alert(1)</script></svg>',
    '<svg onload=alert(1)>',
    '<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>',
    '<math><mtext><table><mglyph><style><!--</style><img title="--&gt;&lt;img src=1 onerror=alert(1)&gt;">',
    '<math href="javascript:alert(1)">x</math>',
    '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">',
    '<p style="background:url(javascript:alert(1))">x</p>',
    '<p style="width:expression(alert(1))">x</p>',
    '<style>@import "javascript:alert(1)";</style>',
    '<iframe src="javascript:alert(1)"></iframe>',
    '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
    '<object data="javascript:alert(1)"></object>',
    '<embed src="javascript:alert(1)">',
    '<form action="javascript:alert(1)"><input type="submit"></form>',
    '<button formaction="javascript:alert(1)">x</button>',
    '<base href="javascript:alert(1)//">',
    '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
    '<link rel="stylesheet" href="javascript:alert(1)">',
    '<body onload=alert(1)>',
    '<div onmouseover="alert(1)">x</div>',
    '<p onclick="alert(1)">x</p>',
    '<<script>alert(1)//<</script>',
    '<scr<script>ipt>alert(1)</scr</script>ipt>',
    '<img src=`x` onerror=alert(1)>',
    '<img/src="x"/onerror=alert(1)>',
    '<a href="x" onclick="alert(1)" style="x">x</a>',
    '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
    '<template><script>alert(1)</script></template>',
    '<textarea></textarea><script>alert(1)</script>',
    '<p><b><i></b></i><script>alert(1)</script>',
    '<table><tr><td><script>alert(1)</script></td></tr></table>',
    '<!--<img src="--><img src=x onerror=alert(1)//">',
    '<![CDATA[<script>alert(1)</script>]]>',
    '<video><source onerror=alert(1)></video>',
    '<a href="//evil.com\\@x.com">x</a>',
    "<p>\u{202E}<img src=x onerror=alert(1)></p>",
]);

it('allows only http, https, mailto and tel in links and keeps relative links', function () {
    expect(clean('<a href="mailto:a@b.rs">m</a>'))->toBe('<a href="mailto:a@b.rs">m</a>')
        ->and(clean('<a href="tel:+381601234567">t</a>'))->toBe('<a href="tel:+381601234567">t</a>')
        ->and(clean('<a href="/o-nama#tim">r</a>'))->toBe('<a href="/o-nama#tim">r</a>')
        ->and(clean('<a href="ftp://x.rs">f</a>'))->toBe('f')
        ->and(clean('<a href="file:///etc/passwd">f</a>'))->toBe('f')
        ->and(clean('<a href="http:///x">f</a>'))->toBe('f');
});

it('forces noopener noreferrer on target _blank and drops other targets', function () {
    expect(clean('<a href="https://x.rs" target="_blank">x</a>'))->toBe('<a href="https://x.rs" target="_blank" rel="noopener noreferrer">x</a>')
        ->and(clean('<a href="https://x.rs" target="_blank" rel="nofollow">x</a>'))->toContain('rel="nofollow noopener noreferrer"')
        ->and(clean('<a href="https://x.rs" target="_top" rel="evil">x</a>'))->toBe('<a href="https://x.rs">x</a>');
});

it('keeps only the text-align style', function () {
    expect(clean('<p style="text-align:center">a</p>'))->toBe('<p style="text-align: center">a</p>')
        ->and(clean('<p style="text-align:center;color:red">a</p>'))->toBe('<p>a</p>')
        ->and(clean('<span style="text-align:center">a</span>'))->toBe('<span>a</span>');
});

it('unwraps unknown tags and keeps their text', function () {
    expect(clean('<div><font color="red">tekst</font></div><marquee>m</marquee>'))->toBe('tekstm');
});

it('keeps safe images, rejects unsafe ones and strips bad attributes', function () {
    expect(clean('<img src="/storage/a.jpg" alt="Slika" width="10" height="5" loading="lazy" data-media-id="x" class="evil" id="a">'))
        ->toBe('<img src="/storage/a.jpg" width="10" height="5" alt="Slika" loading="lazy">')
        ->and(clean('<img src="https://cdn.rs/a.jpg 1x, javascript:alert(1)" alt="">'))->toBe('')
        ->and(clean('<img srcset="/a.jpg 1x, javascript:alert(1) 2x" src="/a.jpg">'))->toBe('<img src="/a.jpg" alt="">')
        ->and(clean('<img>'))->toBe('');
});

it('is idempotent', function (string $html) {
    $once = clean($html);

    expect(clean($once))->toBe($once);
})->with([
    '<pre>

two newlines</pre>',
    '<p>a &amp;amp; b &lt;script&gt; &nbsp; č ć š đ ž</p>',
    '<table><tr><td>x</td></tr></table>',
    '<p><b><i>a</b></i></p>',
    '<ul><li>a<ul><li>b</li></ul></li></ul>',
    '<a href="https://x.rs/?a=1&amp;b=&quot;2&quot;" target="_blank">x</a>',
    '<p style="text-align:right">r</p><svg><p>x</p></svg><math><p>y</p></math>',
    'plain text <3 and a < b',
    '<h2>x</h2><h1>y</h1><h5>z</h5>',
    "<p>a\r\nb\x00c</p>",
]);

it('handles empty and broken input', function () {
    expect(clean(''))->toBe('')->and(clean(null))->toBe('')->and(clean('   '))->toBe('')
        ->and(clean("<p>\xC3\x28</p>"))->toBeString()
        ->and(clean('<p'))->toBe('')
        ->and(clean(str_repeat('<div>', 500).'x'.str_repeat('</div>', 500)))->toBe('x');
});

it('leaves the media ids out when the media table has no such file', function () {
    expect(HtmlSanitizer::mediaIds('<img data-media-id="999999" src="/storage/a.jpg">'))->toBe([])
        ->and(clean('<img data-media-id="999999" src="/storage/a.jpg" alt="a">'))->toBe('<img src="/storage/a.jpg" alt="a">')
        ->and(clean('<img data-media-id="999999" src="javascript:alert(1)">'))->toBe('');
});

it('cleans only the editor fields of a translations payload', function () {
    $out = HtmlSanitizer::cleanTranslations(
        ['sr' => ['title' => '<b>x</b>', 'body' => '<p onclick="x">t</p><script>1</script>'], 'en' => ['body' => '<img src="javascript:1" onerror=1>']],
        ['body'],
    );

    expect($out['sr']['title'])->toBe('<b>x</b>')->and($out['sr']['body'])->toBe('<p>t</p>')->and($out['en']['body'])->toBe('');
});

it('transliterates sanitized content without touching tags and attributes', function () {
    $html = clean('<p style="text-align:center">Dobar dan <a href="https://x.rs/dan" title="njiva">njiva</a></p><img src="/storage/dan.jpg" alt="dan" width="3" height="3">');
    $cyr = (new SerbianLatinToCyrillic)->transliterate($html);

    expect($cyr)->toContain('<p style="text-align: center">')->toContain('href="https://x.rs/dan"')->toContain('src="/storage/dan.jpg"')
        ->toContain('Добар дан')->toContain('њива');
});
