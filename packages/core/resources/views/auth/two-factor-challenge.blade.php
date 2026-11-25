@php $recovery = $recovery || $errors->has('recovery_code'); @endphp
<x-codeart::auth-layout :title="__('codeart::ui.challenge_title')">
    <div x-data="{ recovery: @js($recovery) }" class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.challenge_title')" />
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
            @csrf
            @if ($errors->any()) <x-codeart::alert type="danger" :title="__('codeart::ui.error_summary')" /> @endif
            <div @if ($recovery) hidden @endif x-bind:hidden="recovery" class="space-y-5">
                <p class="text-center text-base text-text-muted">{{ __('codeart::ui.challenge_text') }}</p>
                <x-codeart::field name="code" id="challenge-code" type="otp" :label="__('codeart::ui.code')" autofocus />
                <p class="text-center text-sm"><x-codeart::link :href="route('two-factor.login', ['recovery' => 1])" x-on:click.prevent="recovery = true">{{ __('codeart::ui.use_recovery') }}</x-codeart::link></p>
            </div>
            <div @if (! $recovery) hidden @endif x-bind:hidden="!recovery" class="space-y-5">
                <p class="text-center text-base text-text-muted">{{ __('codeart::ui.recovery_text') }}</p>
                <x-codeart::field name="recovery_code" id="challenge-recovery" icon="shield" :label="__('codeart::ui.recovery_code')" autocomplete="off" autocapitalize="off" spellcheck="false" />
                <p class="text-center text-sm"><x-codeart::link :href="route('two-factor.login')" x-on:click.prevent="recovery = false">{{ __('codeart::ui.use_code') }}</x-codeart::link></p>
            </div>
            <x-codeart::button icon="arrow-right">{{ __('codeart::ui.verify') }}</x-codeart::button>
        </form>
        <p class="text-center text-sm"><x-codeart::link :href="route('login')">{{ __('codeart::ui.back_to_login') }}</x-codeart::link></p>
    </div>
</x-codeart::auth-layout>
