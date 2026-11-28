<x-codeart::install.layout :title="__('codeart::install.run.title')" step="run">
    <x-codeart::heading :title="$retry ? __('codeart::install.run.failed_title') : __('codeart::install.run.title')" :text="$retry ? __('codeart::install.run.failed_text') : __('codeart::install.run.text')" />
    @if ($results)
        <ul class="space-y-2" data-run-results>
            @foreach ($results as $result)
                <li class="flex items-start gap-3 text-sm" data-step="{{ $result->step }}" data-ok="{{ $result->ok ? '1' : '0' }}">
                    <span class="{{ $result->ok ? 'text-success-text' : 'text-danger-text' }}"><x-codeart::icon :name="$result->ok ? 'check' : 'alert'" class="size-5" /></span>
                    <div><p class="font-medium">{{ __('codeart::install.run.'.$result->step) }}</p>
                        @if ($result->message) <p class="text-text-muted">{{ __('codeart::install.run.'.$result->message, ['module' => $result->detail ?? '']) }}</p> @endif</div>
                </li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('install.run.execute') }}">
        @csrf
        <x-codeart::button icon="arrow-right">{{ $retry ? __('codeart::install.run.retry') : __('codeart::install.run.start') }}</x-codeart::button>
    </form>
</x-codeart::install.layout>
