<x-codeart::install.layout :title="__('codeart::install.modules.title')" step="modules">
    <x-codeart::heading :title="__('codeart::install.modules.title')" :text="__('codeart::install.modules.text')" />
    <form method="POST" action="{{ route('install.modules.store') }}" class="space-y-5">
        @csrf
        @forelse ($modules as $name => $module)
            <x-codeart::checkbox :id="'module-'.$name" name="modules[]" :value="$name" :label="$module->manifest->title" :hint="$module->manifest->description" :checked="in_array($name, $chosen, true)" />
        @empty
            <p class="text-text-muted">{{ __('codeart::install.modules.none') }}</p>
        @endforelse
        <x-codeart::button icon="arrow-right">{{ __('codeart::install.continue') }}</x-codeart::button>
    </form>
</x-codeart::install.layout>
