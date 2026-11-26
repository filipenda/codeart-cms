<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    <dl class="space-y-3 text-sm">
        @foreach ($data['checks'] as $check)
            <div class="flex items-start justify-between gap-4">
                <dt class="shrink-0 whitespace-nowrap text-text-muted">{{ __('codeart::admin.widgets.check.'.$check['label']) }}</dt>
                <dd class="min-w-0 text-end font-medium text-text [overflow-wrap:anywhere]">
                    @if ($check['ok'] === null)
                        {{ $check['value'] }}
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $check['ok'] ? 'bg-success-bg text-success-text' : 'bg-danger-bg text-danger-text' }}">
                            <x-codeart::icon :name="$check['ok'] ? 'circle-check' : 'circle-alert'" class="size-3.5" />
                            {{ $check['ok'] ? __('codeart::admin.widgets.ok') : __('codeart::admin.widgets.fail') }}
                        </span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
</x-codeart::admin.card>
