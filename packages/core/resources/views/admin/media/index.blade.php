<x-codeart::admin.layout :title="__('codeart::media.title')">
    <x-codeart::admin.page-header :title="__('codeart::media.title')" :description="__('codeart::media.subtitle')" :breadcrumbs="[['label' => __('codeart::media.title')]]" />

    <div class="space-y-6">
        <x-codeart::media.browser mode="page" :locales="$locales" :accept="'.'.implode(',.', $allowed)" />

        @if ($can['update'])
            <x-codeart::admin.card :title="__('codeart::media.settings.title')" icon="sliders-horizontal" data-media-settings>
                <div class="mb-4 space-y-1 text-sm" data-limit-explanation>
                    <p class="font-semibold text-text">{{ __('codeart::media.limit.effective', ['limit' => $limit['effective']]) }}</p>
                    <p class="text-text-muted">{{ __('codeart::media.limit.detail', ['setting' => $limit['setting'], 'upload' => $limit['upload_max_filesize'], 'post' => $limit['post_max_size']]) }}</p>
                    @if ($limit['too_high'])
                        <p class="text-warning" role="status" data-limit-warning>{{ __('codeart::media.limit.too_small.'.$limit['bottleneck'], ['limit' => $limit['effective']]) }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.media.settings') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-codeart::field name="max_kb" type="number" min="1" :label="__('codeart::media.settings.max_kb')" :value="old('max_kb', $maxKb)" :hint="__('codeart::media.settings.max_kb_hint')" />
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium text-text">{{ __('codeart::media.settings.types') }}</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            @foreach ($allTypes as $ext)
                                <label class="inline-flex items-center gap-2"><input type="checkbox" name="types[]" value="{{ $ext }}" class="size-4" @checked(in_array($ext, $allowed, true))> .{{ $ext }}</label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-sm text-text-muted">{{ __('codeart::media.settings.types_hint') }}</p>
                    </fieldset>
                    <x-codeart::button type="submit" size="md" :full="false" icon-left="save">{{ __('codeart::publishing.save') }}</x-codeart::button>
                </form>
            </x-codeart::admin.card>
        @endif
    </div>
</x-codeart::admin.layout>
