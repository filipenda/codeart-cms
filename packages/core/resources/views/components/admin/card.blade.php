@props(['title' => null, 'icon' => null, 'description' => null])
{{-- Admin card: rounded surface with a border; optional header (icon tile + title + one-line description) and an optional
     `footer` slot (the form buttons). --}}
<section {{ $attributes->class('flex flex-col rounded-card border border-border bg-surface') }} @if ($title) aria-labelledby="{{ $id = 'card-'.\Illuminate\Support\Str::random(6) }}" @endif>
    @if ($title)
        <header class="flex items-center gap-3 border-b border-border px-5 py-4" data-card-header>
            @if ($icon)
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-control bg-primary-soft text-link" aria-hidden="true"><x-codeart::icon :name="$icon" class="size-5" /></span>
            @endif
            <div class="min-w-0">
                <h2 id="{{ $id }}" class="text-base font-semibold text-text">{{ $title }}</h2>
                @if ($description) <p class="text-sm text-text-muted" data-card-description>{{ $description }}</p> @endif
            </div>
        </header>
    @endif
    <div class="flex-1 p-5">{{ $slot }}</div>
    @isset($footer)
        <footer class="flex flex-wrap justify-end gap-3 border-t border-border px-5 py-4" data-card-footer>{{ $footer }}</footer>
    @endisset
</section>
