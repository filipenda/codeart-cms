@php
    $locales = app(\Codeart\Core\Translations\Locales::class);
    $enabled = $locales->enabled();
    $current = $locales->current();
@endphp
{{-- A <details> pill: works without JS; Alpine only closes it on Escape / outside click. Hidden with one enabled language. --}}
@if (count($enabled) > 1)
    <details {{ $attributes->class('relative') }} x-data x-on:keydown.escape="$el.open = false" x-on:click.outside="$el.open = false">
        <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-medium text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus [&::-webkit-details-marker]:hidden" aria-label="{{ __('codeart::ui.language') }}">
            <x-codeart::icon name="globe" class="size-4" />
            <span lang="{{ $current }}">{{ $locales->name($current) }}</span>
            <x-codeart::icon name="chevron-down" class="size-4" />
        </summary>
        <form method="POST" action="{{ route('codeart.locale') }}" class="absolute end-0 z-20 mt-2 min-w-44 rounded-control border border-border bg-surface p-1 shadow-card">
            @csrf
            @foreach ($enabled as $code)
                <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" @if ($code === $current) aria-current="true" @endif
                    class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus {{ $code === $current ? 'font-bold' : '' }}">
                    <span>{{ $locales->name($code) }}</span>
                    @if ($code === $current) <x-codeart::icon name="check" class="size-4" /> @endif
                </button>
            @endforeach
        </form>
    </details>
@endif
