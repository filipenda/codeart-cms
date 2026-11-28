<x-codeart::admin.profile-shell tab="two-factor" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <x-codeart::admin.card :title="__('codeart::profile.tf_title')" :description="__('codeart::profile.tf_desc')" icon="shield" data-two-factor-card>
        @if (! $available)
            <p class="text-sm text-text-muted" data-two-factor-unavailable>{{ __('codeart::profile.two_factor_unavailable') }}</p>
        @elseif ($enabled)
            <div class="space-y-6" data-two-factor data-two-factor-state="on">
                <div class="flex items-start gap-3 rounded-control border border-success bg-success-bg p-4 text-success-text" role="status" data-two-factor-status>
                    <x-codeart::icon name="shield-check" class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">{{ __('codeart::profile.tf_active') }}</p>
                        <p class="text-sm">{{ __('codeart::profile.tf_active_text') }}</p>
                    </div>
                </div>

                <section aria-labelledby="tf-codes-title" data-recovery-section>
                    <h3 id="tf-codes-title" class="text-base font-semibold text-text">{{ __('codeart::ui.codes_title') }}</h3>
                    <p class="mt-1 text-sm text-text-muted">{{ __('codeart::profile.tf_codes_text') }}</p>
                    @if ($codes !== [])
                        <div class="mt-4 space-y-3" x-data="codeartRecoveryCodes('recovery-codes.txt')" data-recovery-codes>
                            <ul x-ref="codes" class="grid grid-cols-2 gap-2 rounded-control bg-surface-alt p-4 font-mono text-base text-text sm:grid-cols-4" aria-label="{{ __('codeart::ui.codes_title') }}">
                                @foreach ($codes as $code) <li>{{ $code }}</li> @endforeach
                            </ul>
                            <div class="flex flex-wrap gap-3" hidden x-bind:hidden="false">
                                <x-codeart::button type="button" variant="secondary" size="md" :full="false" icon-left="copy" x-on:click="copy()"><span x-text="copied ? @js(__('codeart::ui.codes_copied')) : @js(__('codeart::ui.codes_copy'))">{{ __('codeart::ui.codes_copy') }}</span></x-codeart::button>
                                <x-codeart::button type="button" variant="secondary" size="md" :full="false" icon-left="download" x-on:click="download()">{{ __('codeart::ui.codes_download') }}</x-codeart::button>
                            </div>
                            <p class="sr-only" aria-live="polite" x-text="copied ? @js(__('codeart::ui.codes_copied')) : ''"></p>
                        </div>
                    @else
                        <div class="mt-4">
                            <x-codeart::button :href="route('admin.profile.two-factor', ['codes' => 1])" variant="secondary" size="md" :full="false" icon-left="key-round" data-show-codes>{{ __('codeart::profile.tf_codes_show') }}</x-codeart::button>
                        </div>
                    @endif
                    <div class="mt-4" data-regenerate>
                        <x-codeart::confirm :action="route('admin.profile.two-factor.recovery')" method="POST" :title="__('codeart::profile.tf_regen_title')" :text="__('codeart::profile.tf_regen_text')" :confirm="__('codeart::profile.tf_regen')" :button="__('codeart::profile.tf_regen')" variant="secondary" size="md" icon="refresh-cw" />
                    </div>
                </section>

                <section class="border-t border-border pt-6" aria-labelledby="tf-off-title">
                    <h3 id="tf-off-title" class="text-base font-semibold text-text">{{ __('codeart::profile.tf_disable_title') }}</h3>
                    @if ($required)
                        <p class="mt-1 flex items-start gap-2 text-sm text-text-muted" data-required-note><x-codeart::icon name="lock" class="mt-0.5 size-4 shrink-0" />{{ __('codeart::profile.two_factor_required') }}</p>
                    @else
                        <p class="mt-1 text-sm text-text-muted">{{ __('codeart::profile.tf_disable_text') }}</p>
                        <div class="mt-4" data-disable>
                            <x-codeart::confirm :action="route('admin.profile.two-factor.disable')" method="DELETE" :title="__('codeart::profile.tf_disable_confirm_title')" :text="__('codeart::profile.tf_disable_confirm_text')" :confirm="__('codeart::profile.tf_disable')" :button="__('codeart::profile.tf_disable')" variant="danger" size="md" icon="shield-off" />
                        </div>
                    @endif
                </section>
            </div>
        @else
            <div class="space-y-6" data-two-factor data-two-factor-state="{{ $pending ? 'pending' : 'off' }}">
                <ol class="grid gap-3 md:grid-cols-3" data-two-factor-guide>
                    @foreach ([1 => ['qr-code', 'tf_step1'], 2 => ['key-round', 'tf_step2'], 3 => ['badge-check', 'tf_step3']] as $n => [$icon, $key])
                        <li class="flex items-start gap-3 rounded-control border border-border bg-surface-alt p-4" data-step="{{ $n }}">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-on-primary" aria-hidden="true">{{ $n }}</span>
                            <div class="min-w-0">
                                <p class="font-semibold text-text">{{ __('codeart::profile.'.$key.'_title') }}</p>
                                <p class="text-sm text-text-muted">{{ __('codeart::profile.'.$key.'_text') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if (! $pending)
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-codeart::button size="md" :full="false" icon-left="shield">{{ __('codeart::profile.two_factor_setup') }}</x-codeart::button>
                    </form>
                @else
                    <div class="grid gap-6 md:grid-cols-[auto_minmax(0,1fr)] md:items-start">
                        {{-- Inline SVG from Fortify, re-wrapped with the quiet zone and the fixed QR tokens: always dark on white, in every theme. --}}
                        <div class="mx-auto w-fit rounded-control border border-border bg-qr-bg [&_svg]:block [&_svg]:size-52" style="color-scheme: only light" role="img" aria-label="{{ __('codeart::ui.setup_qr_label') }}" data-two-factor-qr>{!! $qr !!}</div>
                        <div class="min-w-0 space-y-4">
                            <div>
                                <p class="text-sm text-text-muted">{{ __('codeart::profile.tf_manual') }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-3">
                                    <code class="break-all font-mono text-base font-medium tracking-wider text-text" data-two-factor-secret>{{ $secret }}</code>
                                    <x-codeart::copy-button :value="$secret" />
                                </div>
                            </div>
                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4" data-two-factor-confirm>
                                @csrf
                                <x-codeart::field name="code" id="profile-2fa-code" type="otp" :label="__('codeart::profile.tf_code')" :bag="$confirmErrors" />
                                <x-codeart::button size="md" :full="false" icon-left="check">{{ __('codeart::profile.tf_activate') }}</x-codeart::button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-codeart::admin.card>
</x-codeart::admin.profile-shell>
