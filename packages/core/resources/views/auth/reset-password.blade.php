@php $min = (int) app(\Codeart\Core\Settings\Settings::class)->get('security.password.min_length'); @endphp
<x-codeart::auth-layout :title="__('codeart::ui.reset_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.reset_title')" :text="__('codeart::ui.reset_text')" />
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-codeart::field name="email" id="reset-email" type="email" icon="mail" :label="__('codeart::ui.email')" :value="$email" autocomplete="username" readonly required />
            <x-codeart::field name="password" id="reset-password" type="password" :label="__('codeart::ui.new_password')" :hint="__('codeart::ui.password_hint', ['min' => $min])" autocomplete="new-password" required autofocus />
            <x-codeart::field name="password_confirmation" id="reset-password-confirmation" type="password" :label="__('codeart::ui.password_confirmation')" autocomplete="new-password" required />
            <x-codeart::button icon="arrow-right">{{ __('codeart::ui.reset_submit') }}</x-codeart::button>
        </form>
        <p class="text-center text-sm"><x-codeart::link :href="route('login')">{{ __('codeart::ui.back_to_login') }}</x-codeart::link></p>
    </div>
</x-codeart::auth-layout>
