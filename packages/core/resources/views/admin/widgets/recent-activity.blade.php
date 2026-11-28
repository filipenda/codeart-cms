<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    @if ($data['entries'] === [])
        <p class="text-sm text-text-muted">{{ __('codeart::admin.widgets.no_activity') }}</p>
    @else
        <ul class="divide-y divide-border">
            @foreach ($data['entries'] as $entry)
                <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-text">{{ $entry['description'] }}</p>
                        <p class="truncate text-xs text-text-muted">{{ $entry['causer'] !== null ? __('codeart::admin.widgets.by', ['name' => $entry['causer']]) : __('codeart::admin.widgets.system') }}</p>
                    </div>
                    <time datetime="{{ $entry['at'] }}" title="{{ $entry['exact'] }}" class="shrink-0 text-xs text-text-muted">{{ $entry['when'] }}</time>
                </li>
            @endforeach
        </ul>
    @endif
</x-codeart::admin.card>
