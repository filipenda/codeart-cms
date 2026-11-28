<x-codeart::install.layout :title="__('codeart::install.done.title')">
    <x-codeart::heading :title="__('codeart::install.done.title')" :text="__('codeart::install.done.text')" />
    <ul class="space-y-2">
        @foreach ($results as $result)
            <li class="flex items-start gap-3 text-sm"><span class="text-success-text"><x-codeart::icon name="check" class="size-5" /></span>
                <div><p class="font-medium">{{ __('codeart::install.run.'.$result->step) }}</p>
                    @if ($result->warning) <p class="text-warning-text">{{ __('codeart::install.run.'.$result->message) }}</p> @endif</div></li>
        @endforeach
    </ul>
    <x-codeart::button :href="$adminUrl" icon="arrow-right">{{ __('codeart::install.done.login') }}</x-codeart::button>
    <section class="space-y-2">
        <h2 class="text-lg font-bold">{{ __('codeart::install.done.cron_title') }}</h2>
        <p class="text-sm text-text-muted">{{ __('codeart::install.done.cron_text') }}</p>
        <p class="flex flex-wrap items-center gap-3"><code class="select-all break-all rounded-control bg-surface-alt px-3 py-2 font-mono text-sm" data-cron-line>{{ $cron }}</code><x-codeart::copy-button :value="$cron" /></p>
    </section>
    <section class="space-y-2">
        <h2 class="text-lg font-bold">{{ __('codeart::install.done.next_title') }}</h2>
        <ul class="list-inside list-disc text-sm">@foreach (__('codeart::install.done.next') as $item)<li>{{ $item }}</li>@endforeach</ul>
    </section>
    <x-codeart::alert type="warning">{{ __('codeart::install.done.key_deleted') }}</x-codeart::alert>
</x-codeart::install.layout>
