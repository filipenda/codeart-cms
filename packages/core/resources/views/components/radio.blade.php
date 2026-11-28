@props(['label', 'id', 'value', 'hint' => null, 'checked' => false])
@php
    // `name` (plain attribute) ties it to old(): after a failed submit the radio whose value was sent is checked.
    $name = $attributes->get('name');
    $isChecked = $name && session()->hasOldInput() ? (string) old($name) === (string) $value : (bool) $checked;
@endphp
<div class="space-y-1">
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-3 text-sm text-text">
        <input type="radio" id="{{ $id }}" value="{{ $value }}" @checked($isChecked) @if ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->class('size-5 shrink-0 cursor-pointer border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60') }}>
        <span>{{ $label }}</span>
    </label>
    @if ($hint) <p id="{{ $id }}-hint" class="ps-8 text-sm text-text-muted">{{ $hint }}</p> @endif
</div>
