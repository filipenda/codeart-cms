<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    <dl class="space-y-1 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::publishing.status.draft') }}</dt><dd class="font-semibold text-text" data-count-drafts>{{ $data['drafts'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::publishing.status.scheduled') }}</dt><dd class="font-semibold text-text" data-count-scheduled>{{ $data['scheduled'] }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::publishing.status.published') }}</dt><dd class="font-semibold text-text" data-count-published>{{ $data['published'] }}</dd></div>
    </dl>
    @if ($widget->module && Route::has($widget->module.'.index'))
        <a href="{{ route($widget->module.'.index', ['filter' => ['status' => 'draft']]) }}" class="mt-3 inline-block text-sm font-semibold text-text underline focus-visible:outline-2 focus-visible:outline-focus">{{ __('codeart::publishing.show_drafts') }}</a>
    @endif
</x-codeart::admin.card>
