<x-codeart::admin.profile-shell tab="personal" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <x-codeart::admin.card :title="__('codeart::profile.details')" icon="circle-user">
        <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4" data-profile-form>
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <x-codeart::field name="name" :label="__('codeart::profile.full_name')" :value="old('name', $me->name)" required maxlength="255" autocomplete="name" />
                <div>
                    <label for="profile-email" class="mb-1.5 block text-sm font-medium text-text">{{ __('codeart::auth.attributes.email') }}</label>
                    <input id="profile-email" type="email" value="{{ $me->email }}" readonly aria-readonly="true" aria-describedby="profile-email-hint" data-profile-email
                        class="h-13 w-full cursor-not-allowed rounded-control border border-border bg-surface-sunken px-4 text-base text-text-muted">
                    <p id="profile-email-hint" class="mt-1 text-xs text-text-muted">{{ __('codeart::profile.email_hint') }}</p>
                </div>
                <x-codeart::field name="phone" type="tel" :label="__('codeart::profile.phone')" :value="old('phone', $me->phone)" maxlength="30" autocomplete="tel" inputmode="tel" />
                <x-codeart::select name="locale" :label="__('codeart::profile.language')" :options="$locales" :value="old('locale', $me->locale)" :placeholder="__('codeart::profile.browser_default')" />
                <x-codeart::select name="theme_preference" :label="__('codeart::admin.theme.label')" :value="old('theme_preference', $me->theme_preference)" :placeholder="__('codeart::profile.browser_default')"
                    :options="['light' => __('codeart::admin.theme.light'), 'dark' => __('codeart::admin.theme.dark'), 'auto' => __('codeart::admin.theme.auto')]" />
            </div>
            <p class="text-xs text-text-muted">{{ __('codeart::profile.avatar_hint', ['mb' => $avatarMaxMb]) }}</p>
            <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-4">
                <x-codeart::button :href="route('admin.profile')" variant="secondary" size="md" :full="false">{{ __('codeart::profile.cancel') }}</x-codeart::button>
                <x-codeart::button size="md" :full="false">{{ __('codeart::profile.save') }}</x-codeart::button>
            </div>
        </form>
    </x-codeart::admin.card>
</x-codeart::admin.profile-shell>
