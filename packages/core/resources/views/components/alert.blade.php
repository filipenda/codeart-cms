@props(['type' => 'info', 'title' => null, 'messages' => [], 'dismissible' => false])
@php
    $icon = match ($type) { 'success' => 'check', 'danger' => 'alert', 'warning' => 'alert', default => 'shield' };
    $tone = match ($type) {
        'success' => 'border-success bg-success-bg text-success-text',
        'danger' => 'border-danger bg-danger-bg text-danger-text',
        'warning' => 'border-warning bg-warning-bg text-warning-text',
        default => 'border-info bg-info-bg text-info-text',
    };
    // Errors interrupt (role=alert); other messages are announced politely (status / aria-live).
    $live = $type === 'danger' ? ['role' => 'alert'] : ['role' => 'status', 'aria-live' => 'polite'];
@endphp
{{-- The icon and the optional title carry the meaning too, never the color alone. `dismissible` adds a close button (needs JS, so it stays hidden without it). --}}
<div {{ $attributes->merge($live)->class(['flex items-start gap-3 rounded-control border px-4 py-3 text-sm', $tone]) }}
    @if ($dismissible) x-data="{ shown: true }" x-show="shown" @endif>
    <x-codeart::icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0 flex-1 space-y-1">
        @if ($title) <p class="font-medium">{{ $title }}</p> @endif
        @if ($slot->isNotEmpty()) <div>{{ $slot }}</div> @endif
        @if (count($messages) > 0)
            <ul class="list-inside list-disc">@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endif
    </div>
    @if ($dismissible)
        <button type="button" hidden x-bind:hidden="false" x-on:click="shown = false" aria-label="{{ __('codeart::ui.c.dismiss') }}"
            class="-m-1 shrink-0 rounded-md p-1 hover:opacity-70 focus-visible:outline-2 focus-visible:outline-focus"><x-codeart::icon name="x" class="size-4" /></button>
    @endif
</div>
