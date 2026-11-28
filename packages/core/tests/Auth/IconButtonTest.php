<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Blade;

function icon_button(string $attrs): DOMElement
{
    $html = Blade::render('<x-codeart::icon-button '.$attrs.' />');
    $node = dom($html)->query('//a|//button')->item(0);
    expect($node)->toBeInstanceOf(DOMElement::class);

    return $node;
}

it('renders a square ghost button with label, tooltip and focus ring', function (string $size, string $box) {
    $b = icon_button('label="Edit" icon="pencil" size="'.$size.'"');
    $class = $b->getAttribute('class');

    expect($b->tagName)->toBe('button')->and($b->getAttribute('type'))->toBe('button')
        ->and($b->getAttribute('aria-label'))->toBe('Edit')->and($b->getAttribute('title'))->toBe('Edit')
        ->and($class)->toContain($box)->toContain('bg-transparent')->toContain('hover:bg-surface-sunken')->toContain('focus-visible:outline-focus')
        ->and($class)->not->toContain('bg-danger ')->not->toContain('bg-primary');
})->with([['sm', 'size-8'], ['md', 'size-9']]);

it('makes danger a color change only, never a filled button at rest', function () {
    $class = icon_button('label="Delete" icon="trash-2" variant="danger"')->getAttribute('class');

    expect($class)->toContain('text-danger')->toContain('bg-transparent')->toContain('hover:bg-danger-bg')->toContain('focus-visible:bg-danger-bg')
        ->and($class)->not->toMatch('/(^|\s)bg-danger(\s|$)/');
});

it('renders a link, a submit button, and the loading and disabled states', function () {
    $a = icon_button('label="Open" icon="eye" href="/x"');
    expect($a->tagName)->toBe('a')->and($a->getAttribute('href'))->toBe('/x')->and($a->getAttribute('aria-label'))->toBe('Open');

    expect(icon_button('label="Go" icon="check" type="submit"')->getAttribute('type'))->toBe('submit');
    $d = icon_button('label="Go" icon="check" :disabled="true"');
    expect($d->hasAttribute('disabled'))->toBeTrue();
    $l = icon_button('label="Go" icon="check" :loading="true"');
    expect($l->getAttribute('aria-busy'))->toBe('true')->and($l->getAttribute('aria-disabled'))->toBe('true')
        ->and($l->getElementsByTagName('svg')->item(0)->getAttribute('class'))->toContain('animate-spin');
    $la = icon_button('label="Go" icon="check" href="/x" :disabled="true"');
    expect($la->getAttribute('aria-disabled'))->toBe('true')->and($la->getAttribute('tabindex'))->toBe('-1');
});

it('uses the component for every row action, aligned from the right with no stray spacing', function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    $this->actingAs(staff('super-admin'), 'admin');
    staff('editor');
    $xp = dom($this->get('/admin/users')->assertOk()->getContent());

    $cells = $xp->query('//td[@data-actions-cell]');
    expect($cells->length)->toBeGreaterThan(1);
    foreach ($cells as $cell) {
        expect($cell->getAttribute('class'))->toBe('whitespace-nowrap px-4 py-2 text-end');
        $wrap = $xp->query('./div', $cell)->item(0);
        expect($wrap->getAttribute('class'))->toBe('flex items-center justify-end gap-1');
    }
    foreach ($xp->query('//td[@data-actions-cell]//*[@data-action and (self::a or self::button)]') as $btn) {
        expect($btn->getAttribute('class'))->toContain('size-8')->and($btn->getAttribute('aria-label'))->not->toBe('');
    }
    // no filled red button at rest, no text-labelled button inside the actions column
    expect(count_nodes($xp, '//td[@data-actions-cell]//button[@data-confirm-trigger][contains(@class,"bg-danger ") or contains(@class,"px-3.5")]'))->toBe(0);
});

it('has one table view for users, invitations and the activity demo, built on icon-button only', function () {
    $view = file_get_contents(__DIR__.'/../../resources/views/components/table.blade.php');
    expect($view)->toContain('<x-codeart::icon-button')->and($view)->toContain(':icon-only="true"')->not->toContain('size-9 items-center');
    foreach (['Users/UsersTable', 'Users/InvitationsTable', 'Admin/Demo/ActivityLogTable'] as $file) {
        expect(file_get_contents(__DIR__.'/../../src/'.$file.'.php'))->toContain('RowAction::')->not->toContain('<a ');
    }
});
