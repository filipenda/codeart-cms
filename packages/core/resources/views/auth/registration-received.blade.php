<x-codeart::auth-layout :title="__('codeart::ui.received_title')">
    <div class="space-y-8">
        <x-codeart::heading :title="__('codeart::ui.received_title')" :text="__('codeart::ui.received_text')" />
        @if ($needsVerification)
            <x-codeart::alert type="info" :title="__('codeart::ui.received_verify_title')">{{ __('codeart::ui.received_verify') }}</x-codeart::alert>
            <x-codeart::button variant="secondary" :href="route('verification.notice')" icon="mail">{{ __('codeart::ui.verify_resend') }}</x-codeart::button>
        @endif
        @if ($pendingApproval)
            <x-codeart::alert type="info" :title="__('codeart::ui.received_approval_title')">{{ __('codeart::ui.received_approval') }}</x-codeart::alert>
        @endif
        <x-codeart::logout />
    </div>
</x-codeart::auth-layout>
