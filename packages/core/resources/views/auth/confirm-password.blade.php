<x-codeart::auth-layout :title="__('codeart::ui.confirm_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.confirm_title')" :text="__('codeart::ui.confirm_text')" />
        <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
            @csrf
            <x-codeart::field name="password" id="confirm-password" type="password" :label="__('codeart::ui.password')" autocomplete="current-password" required autofocus />
            <x-codeart::button icon="arrow-right">{{ __('codeart::ui.confirm_submit') }}</x-codeart::button>
        </form>
    </div>
</x-codeart::auth-layout>
