<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    <p class="text-3xl font-bold text-text" data-pending-count>{{ $data['count'] }}</p>
    <p class="mt-1 text-sm text-text-muted">{{ $data['count'] === 0 ? __('codeart::users.widget.none') : __('codeart::users.widget.pending') }}</p>
    @if ($data['count'] > 0)
        <a href="{{ route('admin.users.index', ['filter' => ['status' => 'pending']]) }}" class="mt-3 inline-block text-sm font-semibold text-text underline focus-visible:outline-2 focus-visible:outline-focus">{{ __('codeart::users.widget.review') }}</a>
    @endif
</x-codeart::admin.card>
