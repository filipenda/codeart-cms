@props(['title' => null, 'icon' => null])
{{-- Admin card: rounded surface with a border; optional header (icon + title). --}}
<section {{ $attributes->class('flex flex-col rounded-card border border-border bg-surface') }} @if ($title) aria-labelledby="{{ $id = 'card-'.\Illuminate\Support\Str::random(6) }}" @endif>
    @if ($title)
        <header class="flex items-center gap-3 border-b border-border px-5 py-4">
            @if ($icon)
                <span class="inline-flex size-9 items-center justify-center rounded-control bg-primary-soft text-link" aria-hidden="true"><x-codeart::icon :name="$icon" class="size-5" /></span>
            @endif
            <h2 id="{{ $id }}" class="text-base font-semibold text-text">{{ $title }}</h2>
        </header>
    @endif
    <div class="flex-1 p-5">{{ $slot }}</div>
</section>
