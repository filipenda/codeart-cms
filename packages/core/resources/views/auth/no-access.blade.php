<x-codeart::auth-layout :title="__('codeart::ui.no_access_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.no_access_title')" :text="$message" />
        <x-codeart::alert type="warning" :title="__('codeart::ui.no_access_error')">{{ __('codeart::ui.no_access_text') }}</x-codeart::alert>
        <x-codeart::logout />
    </div>
</x-codeart::auth-layout>
