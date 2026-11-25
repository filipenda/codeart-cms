<?php

use Codeart\Core\Translations\SerbianLatinToCyrillic;

function cyr(string $text): string
{
    return (new SerbianLatinToCyrillic)->transliterate($text);
}

it('converts digraphs in every case form', function (string $in, string $out) {
    expect(cyr($in))->toBe($out);
})->with([
    ['Ljubav', 'Љубав'],
    ['LJUBAV', 'ЉУБАВ'],
    ['ljubav', 'љубав'],
    ['NJIVA', 'ЊИВА'],
    ['Njiva', 'Њива'],
    ['njiva', 'њива'],
    ['Džemper', 'Џемпер'],
    ['DŽEMPER', 'ЏЕМПЕР'],
    ['džep', 'џеп'],
]);

it('handles đ č ć š ž and capitals', function () {
    expect(cyr('đak Đurđevdan'))->toBe('ђак Ђурђевдан')
        ->and(cyr('čačak ćuprija šuma žaba'))->toBe('чачак ћуприја шума жаба')
        ->and(cyr('ČĆŠŽ'))->toBe('ЧЋШЖ');
});

it('keeps a plain dj as д+ј', function () {
    expect(cyr('nadjačati'))->toBe('надјачати');
});

it('handles mixed case at the start of a word', function () {
    expect(cyr('Ljubav Njiva Džem Šuma'))->toBe('Љубав Њива Џем Шума');
});

it('transliterates only the text of HTML, never tags or attribute values', function () {
    expect(cyr('<a href="/njiva" title="njiva">njiva</a>'))->toBe('<a href="/njiva" title="njiva">њива</a>')
        ->and(cyr('<p class="lj">ljubav</p>'))->toBe('<p class="lj">љубав</p>')
        ->and(cyr('<script>var njiva = 1;</script>njiva'))->toBe('<script>var njiva = 1;</script>њива');
});

it('leaves urls, e-mail addresses and entities alone', function () {
    expect(cyr('Posetite https://njiva.rs/ljubav?x=1 danas'))->toBe('Посетите https://njiva.rs/ljubav?x=1 данас')
        ->and(cyr('Pišite na info.njiva@primer.rs sada'))->toBe('Пишите на info.njiva@primer.rs сада')
        ->and(cyr('ljubav&nbsp;njiva &amp; &#269;'))->toBe('љубав&nbsp;њива &amp; &#269;');
});

it('leaves placeholders alone', function () {
    expect(cyr('Zdravo :name, imate :count poruka'))->toBe('Здраво :name, имате :count порука')
        ->and(cyr('Cena %s i %1$d i %.2f'))->toBe('Цена %s и %1$d и %.2f')
        ->and(cyr('Dobrodošli {name} u {{ $njiva }} i {!! $ljubav !!}'))->toBe('Добродошли {name} у {{ $njiva }} и {!! $ljubav !!}');
});

it('passes characters outside Serbian Latin through', function () {
    expect(cyr('w q x y 2026, (!?)'))->toBe('w q x y 2026, (!?)');
});

it('does not mangle invalid UTF-8', function () {
    expect(cyr("\xB1\x31"))->toBe("\xB1\x31");
});
