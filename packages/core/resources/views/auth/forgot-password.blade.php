<x-codeart::auth-layout :title="__('codeart::ui.forgot_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.forgot_title')" :text="__('codeart::ui.forgot_text')" />
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <x-codeart::field name="email" id="forgot-email" type="email" icon="mail" :label="__('codeart::ui.email')" autocomplete="username" required autofocus />
            <x-codeart::button icon="arrow-right">{{ __('codeart::ui.forgot_submit') }}</x-codeart::button>
        </form>
        <p class="text-center text-sm"><x-codeart::link :href="route('login')">{{ __('codeart::ui.back_to_login') }}</x-codeart::link></p>
    </div>
</x-codeart::auth-layout>
