<x-codeart::admin.layout :title="__('codeart::users.invite.title')">
    <x-codeart::admin.page-header :title="__('codeart::users.invite.title')" :description="__('codeart::users.invite.description')"
        :breadcrumbs="[['label' => __('codeart::users.title'), 'url' => route('admin.users.index')], ['label' => __('codeart::users.invite.title')]]" />
    <x-codeart::admin.card icon="user-plus" class="max-w-2xl">
        <form method="POST" action="{{ route('admin.users.invite.store') }}" class="space-y-4" data-invite-form>
            @csrf
            <x-codeart::field name="name" :label="__('codeart::auth.attributes.name')" required maxlength="255" />
            <x-codeart::field name="email" type="email" :label="__('codeart::auth.attributes.email')" required maxlength="255" />
            <x-codeart::select name="role" :label="__('codeart::users.col.role')" :options="$roles" required />
            <x-codeart::textarea name="message" :label="__('codeart::users.invite.message')" :hint="__('codeart::users.invite.message_hint')" maxlength="500" rows="3" />
            <x-codeart::button size="md" :full="false" icon-left="mail">{{ __('codeart::users.invite.send') }}</x-codeart::button>
        </form>
    </x-codeart::admin.card>
</x-codeart::admin.layout>
