@props(['name', 'label', 'id' => null, 'hint' => null, 'bag' => null, 'value' => null, 'rows' => 4])
@php
    // Same contract as <x-codeart::field>: label, hint, counter (maxlength), error from the bag, old() value.
    $id ??= 'field-'.$name;
    $error = ($bag ?? $errors)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $value ??= old($name);
@endphp
<x-codeart::field-shell :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$attributes->has('required')" :max="$attributes->get('maxlength')" :count="mb_strlen((string) $value)">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block w-full rounded-control border border-border bg-surface px-4 py-3 text-base text-text placeholder:text-text-muted',
            'focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
            'aria-invalid:border-danger read-only:bg-surface-alt disabled:opacity-60',
        ]) }}>{{ $value }}</textarea>
</x-codeart::field-shell>
