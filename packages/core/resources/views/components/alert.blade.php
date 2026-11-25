@props(['type' => 'info', 'title' => null, 'messages' => []])
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
{{-- The icon and the optional title carry the meaning too, never the color alone. --}}
<div {{ $attributes->merge($live)->class(['flex items-start gap-3 rounded-control border px-4 py-3 text-sm', $tone]) }}>
    <x-codeart::icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0 flex-1 space-y-1">
        @if ($title) <p class="font-medium">{{ $title }}</p> @endif
        @if ($slot->isNotEmpty()) <div>{{ $slot }}</div> @endif
        @if (count($messages) > 0)
            <ul class="list-inside list-disc">@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endif
    </div>
</div>
