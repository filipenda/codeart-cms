<?php

use Codeart\Core\Editor\EditorStyles;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;

const EDITOR_DIST = __DIR__.'/../../resources/dist';

function renderEditor(string $attributes = ''): string
{
    return Blade::render('<x-codeart::form.editor name="translations[sr][body]" label="Tekst" :value="$value" :bag="$bag" '.$attributes.' />', [
        'value' => '<p>Zdravo</p>', 'bag' => new MessageBag,
    ]);
}

it('renders a textarea fallback with the toolbar, labels and no source button for staff', function () {
    $html = renderEditor();

    expect($html)->toContain('data-editor')->toContain('x-data="codeartEditor(')
        ->toContain('<textarea')->toContain('name="translations[sr][body]"')->toContain('&lt;p&gt;Zdravo&lt;/p&gt;')
        ->toContain('role="toolbar"')->toContain('aria-label="Text formatting"')
        ->toContain('aria-label="Bold"')->toContain('>Heading 2<')->not->toContain('data-editor-source');
});

it('shows the HTML source button only to a super-admin', function () {
    $this->actingAs(staff('super-admin'), 'admin');

    expect(renderEditor())->toContain('data-editor-source');

    $this->actingAs(staff('admin'), 'admin');
    expect(renderEditor())->not->toContain('data-editor-source');
});

it('has an accessible label for every toolbar button in each language', function (string $locale) {
    app()->setLocale($locale);
    $html = renderEditor();
    $keys = ['bold', 'italic', 'underline', 'strike', 'code', 'bulletList', 'orderedList', 'blockquote', 'rule', 'link', 'image', 'table', 'clear', 'undo', 'redo'];

    foreach ($keys as $key) {
        expect(__('codeart::editor.'.$key))->not->toBe('codeart::editor.'.$key)->and($html)->toContain('aria-label="'.__('codeart::editor.'.$key).'"');
    }
})->with(['sr', 'en', 'sr-Cyrl']);

it('shows the validation error and wires it to the field', function () {
    $html = Blade::render('<x-codeart::form.editor name="translations[sr][body]" label="Tekst" :bag="$bag" />', ['bag' => new MessageBag(['translations[sr][body]' => ['Prekratko']])]);

    expect($html)->toContain('aria-invalid="true"')->toContain('Prekratko')->toContain('aria-describedby=');
});

it('renders one independent editor per language in the same form', function () {
    $html = Blade::render('@foreach (["sr", "en"] as $l)<x-codeart::form.editor :name="\'translations[\'.$l.\'][body]\'" :id="\'f-\'.$l" label="Tekst" :bag="$bag" />@endforeach', ['bag' => new MessageBag]);

    expect(substr_count($html, 'x-data="codeartEditor('))->toBe(2)->and($html)->toContain('name="translations[en][body]"')->toContain('id="f-en"');
});

it('accepts only http(s) and root-relative editor stylesheets', function () {
    config()->set('codeart.editor_css', ['https://site.rs/a.css', '/css/site.css', '//evil.com/x.css', 'javascript:alert(1)', 'data:text/css,a{}', '/ok.css" onload="x', 5]);

    expect(EditorStyles::urls())->toBe(['https://site.rs/a.css', '/css/site.css']);
    expect(renderEditor())->toContain('site.rs')->toContain('site.css')->not->toContain('evil.com')->not->toContain('javascript:');
});

it('ships the editor in the built assets, with TipTap bundled and no CDN', function () {
    $js = (string) file_get_contents(EDITOR_DIST.'/codeart.js');

    expect($js)->toContain('codeartEditor')->toContain('data-media-id')->not->toContain('cdn.')->not->toContain('unpkg.com');
});

it('marks the publishing form with the unsaved-changes guard and the editor field type', function () {
    $form = (string) file_get_contents(__DIR__.'/../../resources/views/components/publishing/form.blade.php');

    expect($form)->toContain('data-dirty-guard')->toContain("\$type === 'editor'")->toContain('x-codeart::form.editor');
});
