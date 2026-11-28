<x-codeart::admin.layout :title="__('codeart::publishing.preview')">
    <x-codeart::admin.page-header :title="__('codeart::publishing.preview')" :description="__('codeart::publishing.preview_hint', ['time' => \Codeart\Core\Support\DisplayTime::current()->format($expires)])">
        <x-slot:actions>
            <x-codeart::button :href="$back" variant="secondary" size="md" :full="false" icon-left="arrow-left">{{ __('codeart::publishing.back_to_edit') }}</x-codeart::button>
            <x-codeart::button :href="$url" target="_blank" rel="noopener noreferrer" variant="ghost" size="md" :full="false" icon-left="external-link">{{ __('codeart::publishing.open_in_tab') }}</x-codeart::button>
        </x-slot:actions>
    </x-codeart::admin.page-header>

    <div x-data="{ width: '100%' }" data-preview-frame-page>
        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __('codeart::publishing.device') }}">
            @foreach (['desktop' => ['100%', 'monitor', 'device_desktop'], 'tablet' => ['768px', 'tablet', 'device_tablet'], 'mobile' => ['375px', 'smartphone', 'device_mobile']] as $key => [$width, $icon, $label])
                <button type="button" data-device="{{ $key }}" data-width="{{ $width }}" x-on:click="width = '{{ $width }}'" x-bind:aria-pressed="width === '{{ $width }}'"
                        class="inline-flex items-center gap-2 rounded-control border border-border px-4 py-2 text-sm font-semibold text-text-muted aria-pressed:bg-primary aria-pressed:text-on-primary focus-visible:outline-2 focus-visible:outline-focus">
                    <x-codeart::icon :name="$icon" class="size-4" />{{ __('codeart::publishing.'.$label) }}
                </button>
            @endforeach
        </div>
        <div class="overflow-x-auto rounded-card border border-border bg-surface-alt p-4">
            <iframe src="{{ $url }}" title="{{ __('codeart::publishing.preview') }}" x-bind:style="'width:' + width" style="width:100%;height:75vh;margin:0 auto;display:block"
                    class="rounded-control border border-border bg-surface" referrerpolicy="no-referrer" data-preview-iframe></iframe>
        </div>
    </div>
</x-codeart::admin.layout>
