@props(['module', 'result', 'canCreate' => false])
{{-- The list page of a publishable module: header with the "new" button and the data table registered under the module name. --}}
<x-codeart::admin.layout :title="__($module.'::module.title')">
    <x-codeart::admin.page-header :title="__($module.'::module.title')" :description="__($module.'::module.description')">
        <x-slot:actions>
            @if ($canCreate && Route::has($module.'.create'))
                <x-codeart::button :href="route($module.'.create')" size="md" :full="false" icon-left="plus" data-new>{{ __($module.'::module.new') }}</x-codeart::button>
            @endif
        </x-slot:actions>
    </x-codeart::admin.page-header>

    <x-codeart::table :result="$result" :label="__($module.'::module.title')" />
</x-codeart::admin.layout>
