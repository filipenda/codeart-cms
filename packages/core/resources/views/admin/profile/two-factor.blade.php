<x-codeart::admin.profile-shell tab="two-factor" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <x-codeart::admin.card :title="__('codeart::admin.user.security')" icon="shield" data-two-factor-card>
        @if (! $twoFactorAvailable)
            <p class="text-sm text-text-muted" data-two-factor-unavailable>{{ __('codeart::profile.two_factor_unavailable') }}</p>
        @else
            <div data-two-factor>
                <p class="text-sm text-text" data-two-factor-state>{{ $twoFactorOn ? __('codeart::profile.two_factor_on') : __('codeart::profile.two_factor_off') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    @if (! $twoFactorOn)
                        <x-codeart::button :href="route('admin.two-factor.setup')" size="md" :full="false" icon-left="shield">{{ __('codeart::profile.two_factor_setup') }}</x-codeart::button>
                    @else
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf
                            <x-codeart::button size="md" :full="false" variant="secondary" data-regenerate>{{ __('codeart::profile.recovery_regenerate') }}</x-codeart::button>
                        </form>
                        @unless ($twoFactorRequired)
                            <form method="POST" action="{{ route('two-factor.disable') }}">@csrf @method('DELETE')
                                <x-codeart::button size="md" :full="false" variant="danger" data-disable>{{ __('codeart::profile.two_factor_disable') }}</x-codeart::button>
                            </form>
                        @else
                            <p class="text-sm text-text-muted" data-required-note>{{ __('codeart::profile.two_factor_required') }}</p>
                        @endunless
                    @endif
                </div>
            </div>
        @endif
    </x-codeart::admin.card>
</x-codeart::admin.profile-shell>
