<x-codeart::admin.profile-shell tab="personal" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <form method="POST" action="{{ route('admin.profile.update') }}" class="block" data-profile-form>
        @csrf @method('PUT')
        <x-codeart::admin.card :title="__('codeart::profile.details')" :description="__('codeart::profile.details_text')" icon="circle-user">
            <div class="grid gap-4 md:grid-cols-2">
                <x-codeart::field name="first_name" icon="user" :label="__('codeart::profile.first_name')" :value="old('first_name', $nameParts[0])" required maxlength="120" autocomplete="given-name" />
                <x-codeart::field name="last_name" icon="user" :label="__('codeart::profile.last_name')" :value="old('last_name', $nameParts[1])" required maxlength="120" autocomplete="family-name" />
                <x-codeart::field-shell id="profile-email" :label="__('codeart::auth.attributes.email')" :hint="__('codeart::profile.email_hint')">
                    <x-codeart::input id="profile-email" type="email" icon="mail" :value="$me->email" readonly aria-readonly="true" aria-describedby="profile-email-hint" data-profile-email class="cursor-not-allowed text-text-muted" />
                </x-codeart::field-shell>
                <x-codeart::field name="phone" type="tel" icon="phone" :label="__('codeart::profile.phone')" :value="old('phone', $me->phone)" maxlength="30" autocomplete="tel" inputmode="tel" />
                <x-codeart::select name="timezone" icon="clock" :label="__('codeart::profile.timezone')" :options="$timezones" :value="old('timezone', $me->timezone)" :placeholder="__('codeart::profile.timezone_default', ['zone' => $siteZoneLabel])" />
                <x-codeart::select name="locale" icon="languages" :label="__('codeart::profile.language')" :options="$locales" :value="old('locale', $me->locale)" :placeholder="__('codeart::profile.browser_default')" />
                <x-codeart::select name="theme_preference" icon="sun-moon" :label="__('codeart::admin.theme.label')" :value="old('theme_preference', $me->theme_preference)" :placeholder="__('codeart::profile.browser_default')"
                    :options="['light' => __('codeart::admin.theme.light'), 'dark' => __('codeart::admin.theme.dark'), 'auto' => __('codeart::admin.theme.auto')]" />
            </div>
            <p class="mt-4 text-xs text-text-muted">{{ __('codeart::profile.avatar_hint', ['mb' => $avatarMaxMb]) }}</p>
            <x-slot:footer>
                <x-codeart::button :href="route('admin.profile')" variant="secondary" size="md" :full="false">{{ __('codeart::profile.cancel') }}</x-codeart::button>
                <x-codeart::button size="md" :full="false">{{ __('codeart::profile.save') }}</x-codeart::button>
            </x-slot:footer>
        </x-codeart::admin.card>
    </form>
</x-codeart::admin.profile-shell>
