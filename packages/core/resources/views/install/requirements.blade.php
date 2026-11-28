<x-codeart::install.layout :title="__('codeart::install.req.title')" step="requirements">
    <x-codeart::heading :title="__('codeart::install.req.title')" :text="__('codeart::install.req.text')" />
    <ul class="divide-y divide-border rounded-control border border-border" data-requirements>
        @foreach ($list as $item)
            @php($state = $item->ok ? 'ok' : ($item->required ? 'bad' : 'warn'))
            <li class="flex items-start gap-3 px-4 py-3 text-sm" data-state="{{ $state }}" data-key="{{ $item->key }}">
                <span class="mt-0.5 shrink-0 {{ $state === 'ok' ? 'text-success-text' : ($state === 'bad' ? 'text-danger-text' : 'text-warning-text') }}"><x-codeart::icon :name="$state === 'ok' ? 'check' : 'alert'" class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ __('codeart::install.req.'.$item->key.'.title', $item->replace) }}</p>
                    @unless ($item->ok) <p class="text-text-muted">{{ __('codeart::install.req.'.$item->key.'.fix', $item->replace) }}</p> @endunless
                </div>
                <span class="shrink-0 font-medium {{ $state === 'ok' ? 'text-success-text' : ($state === 'bad' ? 'text-danger-text' : 'text-warning-text') }}">{{ __('codeart::install.req.'.$state) }}</span>
            </li>
        @endforeach
    </ul>
    @unless ($passes) <x-codeart::alert type="danger">{{ __('codeart::install.req.blocked') }}</x-codeart::alert> @endunless
    <div class="flex flex-col gap-3 sm:flex-row">
        <x-codeart::button variant="secondary" :href="route('install.requirements')" icon="refresh-cw">{{ __('codeart::install.req.recheck') }}</x-codeart::button>
        @if ($passes) <x-codeart::button :href="route('install.database')" icon="arrow-right">{{ __('codeart::install.continue') }}</x-codeart::button> @endif
    </div>
</x-codeart::install.layout>
