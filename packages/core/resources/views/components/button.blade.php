@props(['variant' => 'primary', 'type' => 'submit', 'icon' => null, 'href' => null, 'full' => true])
@php
    $classes = 'group inline-flex h-13 select-none items-center justify-center gap-2 rounded-control px-6 text-base font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-disabled:pointer-events-none aria-disabled:opacity-70 '
        .($full ? 'w-full ' : '')
        .match ($variant) {
            'secondary' => 'border border-border bg-surface text-text hover:bg-surface-alt',
            'ghost' => 'bg-transparent text-text hover:bg-surface-alt',
            default => 'bg-primary text-on-primary hover:bg-primary-hover',
        };
@endphp
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span>{{ $slot }}</span>
        @if ($icon) <x-codeart::icon :name="$icon" /> @endif
    </a>
@else
    {{-- Loading state: after the first submit the button ignores further clicks (no double submit). Without JS it is a plain submit button. --}}
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}
        x-data="{ loading: false }"
        x-init="if ($el.form) { $el.form.addEventListener('submit', () => { loading = true }) }"
        x-on:click="if (loading) $event.preventDefault()"
        x-on:pageshow.window="loading = false"
        x-bind:aria-disabled="loading ? 'true' : null"
        x-bind:aria-busy="loading ? 'true' : null">
        <span>{{ $slot }}</span>
        @if ($icon)
            <x-codeart::icon :name="$icon" x-bind:hidden="loading" />
        @endif
        <svg class="size-5 shrink-0 animate-spin" hidden x-bind:hidden="!loading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" /></svg>
    </button>
@endif
