<x-codeart::admin.layout :title="__('codeart::users.title')">
    <x-codeart::admin.page-header :title="__('codeart::users.title')" :description="__('codeart::users.description')">
        <x-slot:actions>
            @if (Route::has('admin.users.invite') && auth(\Codeart\Core\Auth\Admin::guardName())->user()?->can('users.create'))
                <x-codeart::button :href="route('admin.users.invite')" size="md" :full="false" icon-left="user-plus" data-invite>{{ __('codeart::users.invite.title') }}</x-codeart::button>
            @endif
        </x-slot:actions>
    </x-codeart::admin.page-header>

    @include('codeart::admin.users.tabs', ['active' => 'users'])

    @if ($pending > 0 && auth(\Codeart\Core\Auth\Admin::guardName())->user()?->can('users.approve'))
        <x-codeart::alert type="warning" class="mb-4" data-pending>
            {{ trans_choice('codeart::users.pending_notice', $pending, ['count' => $pending]) }}
            <a href="{{ route('admin.users.index', ['filter' => ['status' => 'pending']]) }}" class="font-semibold underline">{{ __('codeart::users.pending_show') }}</a>
        </x-codeart::alert>
    @endif

    <x-codeart::table :result="$result" :label="__('codeart::users.title')" />
</x-codeart::admin.layout>
