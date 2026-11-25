@php
    $min = (int) app(\Codeart\Core\Settings\Settings::class)->get('security.password.min_length');
    $state = $invitation === null && $state === 'valid' ? 'invalid' : $state; // e.g. the person already has an account
@endphp
<x-codeart::auth-layout :title="__('codeart::ui.invitation_title')">
    <div class="space-y-8">
        @if ($state === 'valid' && $invitation)
            <x-codeart::heading :title="__('codeart::ui.invitation_title')" :text="__('codeart::ui.invitation_text', ['name' => $invitation->name])" />
            <form method="POST" action="{{ url()->full() }}" class="space-y-5">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                <x-codeart::field name="email" id="invitation-email" type="email" icon="mail" :label="__('codeart::ui.email')" :value="$invitation->email" autocomplete="username" readonly />
                <x-codeart::field name="password" id="invitation-password" type="password" :label="__('codeart::ui.password')" :hint="__('codeart::ui.password_hint', ['min' => $min])" autocomplete="new-password" required autofocus />
                <x-codeart::field name="password_confirmation" id="invitation-password-confirmation" type="password" :label="__('codeart::ui.password_confirmation')" autocomplete="new-password" required />
                <x-codeart::button icon="arrow-right">{{ __('codeart::ui.invitation_submit') }}</x-codeart::button>
            </form>
        @else
            <x-codeart::heading :title="__('codeart::ui.invitation_'.$state.'_title')" />
            <x-codeart::alert type="danger" :title="__('codeart::ui.invitation_problem')" data-state="{{ $state }}">{{ __('codeart::ui.invitation_'.$state.'_text') }}</x-codeart::alert>
            <x-codeart::button variant="secondary" :href="route('login')" icon="arrow-right">{{ __('codeart::ui.back_to_login') }}</x-codeart::button>
        @endif
    </div>
</x-codeart::auth-layout>
