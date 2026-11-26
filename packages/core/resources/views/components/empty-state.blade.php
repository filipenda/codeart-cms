@props(['title', 'text' => null, 'icon' => 'inbox'])
{{-- Nothing here yet: icon, title, optional text and an optional action (default slot). --}}
<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-card border border-dashed border-border px-6 py-12 text-center') }}>
    <span class="inline-flex size-12 items-center justify-center rounded-full bg-surface-alt text-text-muted"><x-codeart::icon :name="$icon" class="size-6" /></span>
    <h3 class="text-base font-semibold text-text">{{ $title }}</h3>
    @if ($text) <p class="max-w-md text-sm text-text-muted">{{ $text }}</p> @endif
    @if ($slot->isNotEmpty()) <div class="mt-2 flex flex-wrap justify-center gap-3">{{ $slot }}</div> @endif
</div>
