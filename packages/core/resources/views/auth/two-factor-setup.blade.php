<x-codeart::auth-layout :title="__('codeart::ui.setup_title')">
    <div class="space-y-8">
        @if ($state === 'off')
            <x-codeart::heading :title="__('codeart::ui.setup_title')" />
            <x-codeart::alert type="info">{{ __('codeart::ui.setup_off') }}</x-codeart::alert>

        @elseif ($state === 'start')
            <x-codeart::heading :title="__('codeart::ui.setup_title')" :text="__('codeart::ui.setup_text')" />
            <form method="POST" action="{{ route('two-factor.enable') }}">
                @csrf
                <x-codeart::button icon="shield">{{ __('codeart::ui.setup_start') }}</x-codeart::button>
            </form>

        @elseif ($state === 'confirm')
            <x-codeart::heading :title="__('codeart::ui.setup_scan_title')" :text="__('codeart::ui.setup_scan_text')" />
            {{-- Inline SVG from Fortify (no external image, no request to a QR service). --}}
            <div class="mx-auto w-fit rounded-control border border-border bg-surface p-3 [&_svg]:size-48" role="img" aria-label="{{ __('codeart::ui.setup_qr_label') }}">{!! $qr !!}</div>
            <div class="space-y-1 text-center">
                <p class="text-sm text-text-muted">{{ __('codeart::ui.setup_manual') }}</p>
                <p class="break-all font-mono text-base font-medium tracking-wider text-text" id="two-factor-secret">{{ $secret }}</p>
            </div>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-5">
                @csrf
                <x-codeart::field name="code" id="setup-code" type="otp" :label="__('codeart::ui.code')" :bag="$errors" autofocus />
                <x-codeart::button icon="check">{{ __('codeart::ui.setup_confirm') }}</x-codeart::button>
            </form>

        @else
            <x-codeart::heading :title="__('codeart::ui.setup_done_title')" :text="__('codeart::ui.setup_done_text')" />
            @if ($codes !== [])
                <div class="space-y-4" x-data="codeartRecoveryCodes('recovery-codes.txt')">
                    <x-codeart::alert type="warning" :title="__('codeart::ui.codes_title')">{{ __('codeart::ui.codes_text') }}</x-codeart::alert>
                    <ul x-ref="codes" class="grid grid-cols-2 gap-2 rounded-control bg-surface-alt p-4 font-mono text-base text-text" aria-label="{{ __('codeart::ui.codes_title') }}">
                        @foreach ($codes as $code) <li>{{ $code }}</li> @endforeach
                    </ul>
                    <div class="flex gap-3" hidden x-bind:hidden="false">
                        <x-codeart::button type="button" variant="secondary" x-on:click="copy()"><span x-text="copied ? @js(__('codeart::ui.codes_copied')) : @js(__('codeart::ui.codes_copy'))">{{ __('codeart::ui.codes_copy') }}</span></x-codeart::button>
                        <x-codeart::button type="button" variant="secondary" x-on:click="download()">{{ __('codeart::ui.codes_download') }}</x-codeart::button>
                    </div>
                    <p class="sr-only" aria-live="polite" x-text="copied ? @js(__('codeart::ui.codes_copied')) : ''"></p>
                </div>
            @else
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                    @csrf
                    <x-codeart::button variant="secondary">{{ __('codeart::ui.codes_regenerate') }}</x-codeart::button>
                </form>
            @endif
            <x-codeart::button :href="url(config('fortify.home', '/'))" icon="arrow-right">{{ __('codeart::ui.continue') }}</x-codeart::button>
        @endif
        <x-codeart::logout />
    </div>
</x-codeart::auth-layout>
