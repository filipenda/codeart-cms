@props(['tone' => 'neutral', 'dot' => true])
@php
    // Status chip: primary, success, warning, danger, neutral. The label is the meaning; the dot is only decoration.
    $classes = match ($tone) {
        'primary' => 'bg-primary-soft text-text',
        'success' => 'bg-success-bg text-success-text',
        'warning' => 'bg-warning-bg text-warning-text',
        'danger' => 'bg-danger-bg text-danger-text',
        default => 'bg-neutral-bg text-neutral-text',
    };
    $dotClass = match ($tone) {
        'primary' => 'bg-primary',
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'danger' => 'bg-danger',
        default => 'bg-neutral',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', $classes]) }} data-tone="{{ $tone }}">
    @if ($dot) <span class="size-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span> @endif
    {{ $slot }}
</span>
