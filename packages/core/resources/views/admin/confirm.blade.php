@php
    $verb = strtoupper($method);
@endphp
{{-- Server-side confirmation step (TB11): shown instead of running a destructive action. Works without JavaScript: the Confirm
     button posts the same request plus the signed, expiring, single-use token. --}}
<x-codeart::admin.layout :title="$title">
    <x-codeart::admin.page-header :title="__('codeart::table.confirm_title')" :description="$title" />
    <x-codeart::admin.card>
        <div class="space-y-5" data-confirm-page>
            @if ($expired)
                <x-codeart::alert type="warning">{{ __('codeart::table.confirm_expired') }}</x-codeart::alert>
            @endif
            <p class="text-text" data-confirm-text>{{ $text }}</p>
            <dl class="space-y-1 text-sm">
                @if ($count !== null)
                    <div class="flex gap-2"><dt class="font-semibold text-text">{{ __('codeart::table.confirm_rows') }}:</dt><dd class="text-text-muted" data-confirm-count>{{ number_format($count, 0, ',', '.') }}</dd></div>
                @endif
                @if ($filter !== null)
                    <div class="flex gap-2"><dt class="font-semibold text-text">{{ __('codeart::table.confirm_filter') }}:</dt><dd class="text-text-muted" data-confirm-filter>{{ $filter }}</dd></div>
                @endif
            </dl>
            <form method="POST" action="{{ $target }}" class="flex flex-wrap gap-3" data-confirm-run>
                @csrf
                @if (! in_array($verb, ['GET', 'POST'], true)) @method($verb) @endif
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="confirmation" value="{{ $token }}">
                <x-codeart::button variant="danger" size="md" :full="false" icon-left="trash-2" data-confirm-submit>{{ $confirmLabel ?? __('codeart::table.confirm_run') }}</x-codeart::button>
                <x-codeart::button variant="secondary" size="md" :full="false" :href="$back" data-confirm-cancel>{{ __('codeart::ui.c.cancel') }}</x-codeart::button>
            </form>
        </div>
    </x-codeart::admin.card>
</x-codeart::admin.layout>
