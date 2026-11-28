<x-codeart::admin.profile-shell tab="password" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <form method="POST" action="{{ route('admin.profile.password') }}" class="block" data-password-form>
        @csrf @method('PUT')
        <x-codeart::admin.card :title="__('codeart::profile.password_card')" :description="__('codeart::profile.password_card_text')" icon="lock">
            <div class="space-y-4">
                <x-codeart::field name="current_password" type="password" icon="lock" :label="__('codeart::profile.current_password')" required autocomplete="current-password" :bag="$errors->updatePassword" />
                <x-codeart::field name="password" type="password" icon="key-round" :label="__('codeart::profile.new_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
                <x-codeart::form.password-strength for="field-password" :rules="$passwordRules" :breached="$checksBreached" />
                <x-codeart::field name="password_confirmation" type="password" icon="key-round" :label="__('codeart::profile.confirm_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
            </div>
            <x-slot:footer>
                <x-codeart::button :href="route('admin.profile.password.show')" variant="secondary" size="md" :full="false">{{ __('codeart::profile.cancel') }}</x-codeart::button>
                <x-codeart::button size="md" :full="false">{{ __('codeart::profile.change_password') }}</x-codeart::button>
            </x-slot:footer>
        </x-codeart::admin.card>
    </form>
</x-codeart::admin.profile-shell>
