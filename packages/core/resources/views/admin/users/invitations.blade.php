<x-codeart::admin.layout :title="__('codeart::users.tab.invitations')">
    <x-codeart::admin.page-header :title="__('codeart::users.title')" :description="__('codeart::users.invitation.description')">
        <x-slot:actions>
            <x-codeart::button :href="route('admin.users.invite')" size="md" :full="false" icon-left="user-plus">{{ __('codeart::users.invite.title') }}</x-codeart::button>
        </x-slot:actions>
    </x-codeart::admin.page-header>
    @include('codeart::admin.users.tabs', ['active' => 'invitations'])
    <x-codeart::table :result="$result" :label="__('codeart::users.tab.invitations')" />
</x-codeart::admin.layout>
