@php
    $first = $user !== null ? \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->before(' ')->toString() : '';
    $tablet = ['sm' => 'md:col-span-6', 'md' => 'md:col-span-6', 'lg' => 'md:col-span-12'];
    $cols = ['sm' => 3, 'md' => 6, 'lg' => 12];
    $spans = \Codeart\Core\Admin\Dashboard\WidgetRows::spans(array_map(fn ($r) => $cols[$r->widget->size], $widgets));
@endphp
<x-codeart::admin.layout :title="__('codeart::admin.dashboard.title')">
    <x-codeart::admin.page-header :title="__('codeart::admin.dashboard.title')"
        :description="$first !== '' ? __('codeart::admin.dashboard.welcome', ['name' => $first]) : __('codeart::admin.dashboard.welcome_anonymous')" />

    @if ($widgets === [])
        <x-codeart::admin.card>
            <div class="py-10 text-center">
                <x-codeart::icon name="layout-dashboard" class="mx-auto size-10 text-text-muted" />
                <h2 class="mt-4 text-lg font-semibold text-text">{{ __('codeart::admin.dashboard.empty_title') }}</h2>
                <p class="mx-auto mt-1 max-w-md text-text-muted">{{ __('codeart::admin.dashboard.empty_text') }}</p>
            </div>
        </x-codeart::admin.card>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-12" data-widgets>
            @foreach ($widgets as $i => $rendered)
                <div class="col-span-1 min-w-0 {{ $tablet[$rendered->widget->size] }} lg:col-span-(--span)" style="--span: {{ $spans[$i] }}" data-span="{{ $spans[$i] }}" data-widget="{{ $rendered->widget->key }}" data-size="{{ $rendered->widget->size }}" @if ($rendered->failed) data-failed @endif>
                    {!! $rendered->html !!}
                </div>
            @endforeach
        </div>
    @endif
</x-codeart::admin.layout>
