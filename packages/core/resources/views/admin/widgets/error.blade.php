{{-- Shown instead of a widget that threw. The details are in the log, never on the page. --}}
<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    <div role="status" class="flex items-start gap-3 rounded-control bg-warning-bg p-4 text-warning-text">
        <x-codeart::icon name="triangle-alert" class="mt-0.5 size-5" />
        <div class="text-sm">
            <p class="font-semibold">{{ __('codeart::admin.widgets.error_title') }}</p>
            <p class="mt-1">{{ __('codeart::admin.widgets.error_text') }}</p>
        </div>
    </div>
</x-codeart::admin.card>
