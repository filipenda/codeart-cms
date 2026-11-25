@props(['icon' => null, 'type' => 'text'])
{{-- Text input, 52px high, optional leading icon. aria-invalid / aria-describedby come from <x-codeart::field>. --}}
<div class="relative">
    @if ($icon)
        <span class="pointer-events-none absolute inset-y-0 start-4 flex items-center text-text-muted"><x-codeart::icon :name="$icon" /></span>
    @endif
    <input type="{{ $type }}" {{ $attributes->class([
        'block h-13 w-full rounded-control border border-border bg-surface text-base text-text placeholder:text-text-muted',
        'focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        'aria-invalid:border-danger read-only:bg-surface-alt disabled:opacity-60',
        $icon ? 'ps-12 pe-4' : 'px-4',
    ]) }}>
</div>
