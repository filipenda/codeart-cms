@php
    $locales = app(\Codeart\Core\Translations\Locales::class);
    $enabled = $locales->enabled();
    $current = $locales->current();
    $badge = fn (string $code): string => $code === 'sr-Cyrl' ? 'СР' : mb_strtoupper(explode('-', $code)[0]);
@endphp
{{-- Admin UI language (not the content language). Posts to the same route as the sign-in screens; works without JS. --}}
@if (count($enabled) > 1)
    <details class="relative" x-data x-on:keydown.escape="$el.open = false" x-on:click.outside="$el.open = false">
        <summary class="flex h-10 cursor-pointer list-none items-center gap-2 rounded-control border border-border bg-surface px-3 text-sm font-medium text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus [&::-webkit-details-marker]:hidden" aria-label="{{ __('codeart::admin.language') }}">
            <span class="rounded-sm bg-surface-sunken px-1.5 py-0.5 text-xs font-semibold" aria-hidden="true">{{ $badge($current) }}</span>
            <span class="hidden sm:inline" lang="{{ $current }}">{{ $locales->name($current) }}</span>
            <x-codeart::icon name="chevron-down" class="size-4" />
        </summary>
        <form method="POST" action="{{ route('codeart.locale') }}" class="absolute end-0 z-20 mt-2 min-w-48 rounded-control border border-border bg-surface p-1 shadow-card">
            @csrf
            @foreach ($enabled as $code)
                <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" @if ($code === $current) aria-current="true" @endif
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus {{ $code === $current ? 'font-bold' : '' }}">
                    <span class="w-7 rounded-sm bg-surface-sunken px-1.5 py-0.5 text-center text-xs font-semibold" aria-hidden="true">{{ $badge($code) }}</span>
                    <span class="flex-1">{{ $locales->name($code) }}</span>
                    @if ($code === $current) <x-codeart::icon name="check" class="size-4" /> @endif
                </button>
            @endforeach
        </form>
    </details>
@endif
