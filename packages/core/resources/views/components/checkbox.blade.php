@props(['label', 'id'])
<label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-3 text-sm text-text">
    <input type="checkbox" id="{{ $id }}" {{ $attributes->class('size-5 shrink-0 cursor-pointer rounded-sm border-border accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus') }}>
    <span>{{ $label }}</span>
</label>
