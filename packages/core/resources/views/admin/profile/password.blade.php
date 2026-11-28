<x-codeart::admin.profile-shell tab="password" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <x-codeart::admin.card :title="__('codeart::profile.password')" icon="lock">
        <form method="POST" action="{{ route('admin.profile.password') }}" class="max-w-xl space-y-4" data-password-form>
            @csrf @method('PUT')
            <x-codeart::field name="current_password" type="password" :label="__('codeart::profile.current_password')" required autocomplete="current-password" :bag="$errors->updatePassword" />
            <x-codeart::field name="password" type="password" :label="__('codeart::profile.new_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
            <x-codeart::field name="password_confirmation" type="password" :label="__('codeart::profile.confirm_password')" required autocomplete="new-password" :bag="$errors->updatePassword" />
            <div class="flex justify-end border-t border-border pt-4">
                <x-codeart::button size="md" :full="false">{{ __('codeart::profile.change_password') }}</x-codeart::button>
            </div>
        </form>
    </x-codeart::admin.card>
</x-codeart::admin.profile-shell>
