<x-codeart::admin.layout :title="__('codeart::profile.title')">
    <x-codeart::admin.page-header :title="__('codeart::profile.title')" :description="__('codeart::profile.description')" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-codeart::admin.card :title="__('codeart::profile.details')" icon="circle-user">
            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4" data-profile-form>
                @csrf @method('PUT')
                <x-codeart::field name="name" :label="__('codeart::auth.attributes.name')" :value="old('name', $me->name)" required maxlength="255" />
                <div>
                    <p class="mb-1 text-sm font-medium text-text">{{ __('codeart::auth.attributes.email') }}</p>
                    <p class="text-sm text-text-muted" data-profile-email>{{ $me->email }}</p>
                    <p class="mt-1 text-xs text-text-muted">{{ __('codeart::profile.email_hint') }}</p>
                </div>
                <x-codeart::select name="locale" :label="__('codeart::profile.language')" :options="$locales" :value="old('locale', $me->locale)" :placeholder="__('codeart::profile.browser_default')" />
                <x-codeart::select name="theme_preference" :label="__('codeart::admin.theme.label')" :value="old('theme_preference', $me->theme_preference)" :placeholder="__('codeart::profile.browser_default')"
                    :options="['light' => __('codeart::admin.theme.light'), 'dark' => __('codeart::admin.theme.dark'), 'auto' => __('codeart::admin.theme.auto')]" />
                <x-codeart::button size="md" :full="false">{{ __('codeart::users.edit.save') }}</x-codeart::button>
            </form>
        </x-codeart::admin.card>

        <div class="space-y-6">
            <x-codeart::admin.card :title="__('codeart::profile.password')" icon="lock">
                <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-4" data-password-form>
                    @csrf @method('PUT')
                    <x-codeart::field name="current_password" type="password" :label="__('codeart::profile.current_password')" required autocomplete="current-password" :bag="$errors->updatePassword" />
                    <x-codeart::field name="password" type="password" :label="__('codeart::profile.new_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
                    <x-codeart::field name="password_confirmation" type="password" :label="__('codeart::profile.confirm_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
                    <x-codeart::button size="md" :full="false">{{ __('codeart::profile.change_password') }}</x-codeart::button>
                </form>
            </x-codeart::admin.card>

            @if ($twoFactorAvailable)
                <x-codeart::admin.card :title="__('codeart::admin.user.security')" icon="shield" data-two-factor>
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
                </x-codeart::admin.card>
            @endif
        </div>
    </div>
</x-codeart::admin.layout>
