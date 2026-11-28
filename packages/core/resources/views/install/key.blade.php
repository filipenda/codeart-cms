<x-codeart::install.layout :title="__('codeart::install.key.title')" step="key">
    <x-codeart::heading :title="__('codeart::install.key.title')" :text="__('codeart::install.key.text')" />
    @if ($missing)
        <x-codeart::alert type="danger">{{ __('codeart::install.key.missing') }}</x-codeart::alert>
    @elseif ($locked)
        <x-codeart::alert type="danger">{{ __('codeart::install.key.locked', ['minutes' => $wait]) }}</x-codeart::alert>
    @else
        <x-codeart::alert>{{ __('codeart::install.key.where', ['path' => $path]) }}</x-codeart::alert>
        <form method="POST" action="{{ route('install.key.store') }}" class="space-y-5">
            @csrf
            <x-codeart::field name="key" id="install-key" icon="key-round" :label="__('codeart::install.key.label')" autocomplete="off" required autofocus />
            <x-codeart::button icon="arrow-right">{{ __('codeart::install.key.submit') }}</x-codeart::button>
        </form>
    @endif
</x-codeart::install.layout>
