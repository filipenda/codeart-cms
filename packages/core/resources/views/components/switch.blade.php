@props(['label', 'id', 'hint' => null, 'bag' => null, 'checked' => false])
@php
    // On/off toggle: a real checkbox with role="switch", styled by CSS only (works without JS).
    $name = $attributes->get('name');
    $error = $name ? ($bag ?? $errors)->first((string) $name) : null;
    $isChecked = $name && session()->hasOldInput() ? (bool) old((string) $name) : (bool) $checked;
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="space-y-1">
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-3 text-sm text-text has-disabled:cursor-not-allowed has-disabled:opacity-60">
        <input type="checkbox" role="switch" id="{{ $id }}" class="peer sr-only" @checked($isChecked) @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif {{ $attributes }}>
        <span class="relative h-6 w-11 shrink-0 rounded-full border border-border bg-surface-sunken transition-colors after:absolute after:start-0.5 after:top-0.5 after:size-4.5 after:rounded-full after:bg-text-muted after:transition-all peer-checked:border-primary peer-checked:bg-primary peer-checked:after:translate-x-5 peer-checked:after:bg-on-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus rtl:peer-checked:after:-translate-x-5 peer-aria-invalid:border-danger" aria-hidden="true"></span>
        <span>{{ $label }}</span>
    </label>
    @if ($hint) <p id="{{ $id }}-hint" class="text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($error) <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm font-medium text-danger-text"><x-codeart::icon name="alert" class="mt-0.5 size-4" /><span>{{ $error }}</span></p> @endif
</div>
