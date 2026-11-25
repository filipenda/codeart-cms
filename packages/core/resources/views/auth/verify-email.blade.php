<x-codeart::auth-layout :title="__('codeart::ui.verify_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.verify_title')" :text="__('codeart::ui.verify_text')" />
        <form method="POST" action="{{ route('verification.send') }}" class="space-y-5">
            @csrf
            <x-codeart::button icon="mail">{{ __('codeart::ui.verify_resend') }}</x-codeart::button>
        </form>
        <x-codeart::logout />
    </div>
</x-codeart::auth-layout>
