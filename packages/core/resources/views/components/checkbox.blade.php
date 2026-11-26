@props(['label', 'id', 'hint' => null, 'bag' => null, 'checked' => false])
@php
    // `name` (plain attribute) ties it to old() and to the error bag. Checked = old value after a failed submit, else `checked`.
    $name = $attributes->get('name');
    $error = $name ? ($bag ?? $errors)->first(rtrim((string) $name, '[]')) : null;
    $isChecked = $name && session()->hasOldInput() ? (bool) old(rtrim((string) $name, '[]')) : (bool) $checked;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="space-y-1">
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-3 text-sm text-text">
        <input type="checkbox" id="{{ $id }}" @checked($isChecked) @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class('size-5 shrink-0 cursor-pointer rounded-sm border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:outline aria-invalid:outline-danger') }}>
        <span>{{ $label }}</span>
    </label>
    @if ($hint) <p id="{{ $id }}-hint" class="ps-8 text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($error) <p id="{{ $id }}-error" class="flex items-start gap-1.5 ps-8 text-sm font-medium text-danger-text"><x-codeart::icon name="alert" class="mt-0.5 size-4" /><span>{{ $error }}</span></p> @endif
</div>
