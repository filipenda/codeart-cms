@php
    $bot = app(\Codeart\Core\Auth\BotProtection::class);
    $min = (int) app(\Codeart\Core\Settings\Settings::class)->get('security.password.min_length');
    $tabs = [['key' => 'login', 'label' => __('codeart::ui.tab_login'), 'href' => route('login')]];
    if ($registration) {
        $tabs[] = ['key' => 'register', 'label' => __('codeart::ui.tab_register'), 'href' => route('register')];
    }
    // Errors belong to the form that was submitted (both forms post back to this page).
    $none = new \Illuminate\Support\MessageBag;
    $loginBag = $active === 'login' ? $errors->getBag('default') : $none;
    $registerBag = $active === 'register' ? $errors->getBag('default') : $none;
@endphp
<x-codeart::auth-layout :title="$active === 'register' ? __('codeart::ui.tab_register') : __('codeart::ui.tab_login')">
    <x-codeart::tabs :tabs="$tabs" :active="$active" :label="__('codeart::ui.tabs_label')">
        <x-slot:login>
            <div class="space-y-8">
                <x-codeart::heading :title="__('codeart::ui.login_title')" :text="__('codeart::ui.login_text')" />
                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    @if ($loginBag->any()) <x-codeart::alert type="danger" :title="__('codeart::ui.error_summary')" /> @endif
                    <x-codeart::field name="email" id="login-email" type="email" icon="mail" :label="__('codeart::ui.email')" :bag="$loginBag" autocomplete="username" required autofocus />
                    <x-codeart::field name="password" id="login-password" type="password" :label="__('codeart::ui.password')" :bag="$loginBag" autocomplete="current-password" required />
                    <div class="flex items-center justify-between gap-4">
                        <x-codeart::checkbox id="login-remember" name="remember" value="1" :label="__('codeart::ui.remember')" />
                        <x-codeart::link :href="route('password.request')" class="text-sm">{{ __('codeart::ui.forgot_link') }}</x-codeart::link>
                    </div>
                    <x-codeart::button icon="arrow-right">{{ __('codeart::ui.login_submit') }}</x-codeart::button>
                </form>
                @if ($registration)
                    <p class="text-center text-sm text-text-muted">{{ __('codeart::ui.no_account') }}
                        <x-codeart::link :href="route('register')" x-on:click.prevent="tab = 'register'">{{ __('codeart::ui.register_link') }}</x-codeart::link></p>
                @endif
            </div>
        </x-slot:login>

        @if ($registration)
            <x-slot:register>
                <div class="space-y-8">
                    <x-codeart::heading :title="__('codeart::ui.register_title')" :text="__('codeart::ui.register_text')" />
                    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="form" value="register">
                        <input type="hidden" name="{{ $bot->tokenField() }}" value="{{ $bot->issueToken() }}">
                        {{-- Honeypot: invisible to people, tempting to bots. --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="register-{{ $bot->honeypotField() }}">{{ __('codeart::ui.honeypot') }}</label>
                            <input type="text" id="register-{{ $bot->honeypotField() }}" name="{{ $bot->honeypotField() }}" value="" tabindex="-1" autocomplete="off">
                        </div>
                        @if ($registerBag->any()) <x-codeart::alert type="danger" :title="__('codeart::ui.error_summary')" /> @endif
                        <x-codeart::field name="name" id="register-name" icon="user" :label="__('codeart::ui.name')" :bag="$registerBag" autocomplete="name" required />
                        <x-codeart::field name="email" id="register-email" type="email" icon="mail" :label="__('codeart::ui.email')" :bag="$registerBag" autocomplete="username" required />
                        <x-codeart::field name="password" id="register-password" type="password" :label="__('codeart::ui.password')" :hint="__('codeart::ui.password_hint', ['min' => $min])" :bag="$registerBag" autocomplete="new-password" required />
                        <x-codeart::field name="password_confirmation" id="register-password-confirmation" type="password" :label="__('codeart::ui.password_confirmation')" :bag="$registerBag" autocomplete="new-password" required />
                        <x-codeart::button icon="arrow-right">{{ __('codeart::ui.register_submit') }}</x-codeart::button>
                    </form>
                    <p class="text-center text-sm text-text-muted">{{ __('codeart::ui.have_account') }}
                        <x-codeart::link :href="route('login')" x-on:click.prevent="tab = 'login'">{{ __('codeart::ui.login_link') }}</x-codeart::link></p>
                </div>
            </x-slot:register>
        @endif
    </x-codeart::tabs>

    @isset($social) {{-- reserved: social sign-in buttons arrive in a later task --}} @endisset
</x-codeart::auth-layout>
