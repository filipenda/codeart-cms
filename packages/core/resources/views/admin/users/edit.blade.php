@php
    $fmt = fn ($d) => \Codeart\Core\Support\DisplayTime::current()->format($d);
    $tone = \Codeart\Core\Users\UserStatus::ALL[$status];
@endphp
<x-codeart::admin.layout :title="$target->name">
    <x-codeart::admin.page-header :title="$target->name" :breadcrumbs="[['label' => __('codeart::users.title'), 'url' => route('admin.users.index')], ['label' => $target->name]]" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-codeart::admin.card :title="__('codeart::users.edit.details')" icon="user">
                <form method="POST" action="{{ route('admin.users.update', $target->getKey()) }}" class="space-y-4" data-edit-form>
                    @csrf @method('PUT')
                    <x-codeart::field name="name" :label="__('codeart::auth.attributes.name')" :value="old('name', $target->name)" required maxlength="255" />
                    <x-codeart::field name="email" type="email" :label="__('codeart::auth.attributes.email')" :value="old('email', $target->email)" required maxlength="255" :hint="__('codeart::users.edit.email_hint')" />
                    <x-codeart::button size="md" :full="false">{{ __('codeart::users.edit.save') }}</x-codeart::button>
                </form>
            </x-codeart::admin.card>

            @if ($can['changeRole'] && count($roles) > 0)
                <x-codeart::admin.card :title="__('codeart::users.edit.role')" icon="shield">
                    <form method="POST" action="{{ route('admin.users.role', $target->getKey()) }}" class="flex flex-wrap items-end gap-3" data-role-form>
                        @csrf @method('PUT')
                        <div class="min-w-56"><x-codeart::select name="role" :label="__('codeart::users.col.role')" :options="$roles" :value="$target->getRoleNames()->first()" /></div>
                        <x-codeart::button size="md" :full="false" variant="secondary">{{ __('codeart::users.edit.change_role') }}</x-codeart::button>
                    </form>
                </x-codeart::admin.card>
            @endif

            @if ($status === 'pending' && $can['approve'])
                <x-codeart::admin.card :title="__('codeart::users.edit.registration')" icon="user-plus">
                    <div class="flex flex-wrap items-start gap-4">
                        <x-codeart::confirm :action="route('admin.users.approve', $target->getKey())" method="POST" :button="__('codeart::users.action.approve')" :text="__('codeart::users.confirm.approve')" variant="primary" icon="check" />
                        <form method="POST" action="{{ route('admin.users.reject', $target->getKey()) }}" class="min-w-64 flex-1 space-y-3" data-reject-form>
                            @csrf
                            <x-codeart::textarea name="reason" :label="__('codeart::users.edit.reject_reason')" maxlength="500" rows="2" />
                            <x-codeart::button size="md" :full="false" variant="danger">{{ __('codeart::users.action.reject') }}</x-codeart::button>
                        </form>
                    </div>
                </x-codeart::admin.card>
            @endif

            <x-codeart::admin.card :title="__('codeart::users.edit.danger')" icon="shield" data-danger-zone>
                <div class="flex flex-wrap gap-3">
                    @if ($status === 'deleted')
                        @if ($can['restore']) <x-codeart::confirm :action="route('admin.users.restore', $target->getKey())" method="POST" :button="__('codeart::users.action.restore')" :text="__('codeart::users.confirm.restore')" variant="secondary" /> @endif
                        @if ($can['forceDelete']) <x-codeart::confirm :action="route('admin.users.force-destroy', $target->getKey())" method="DELETE" :button="__('codeart::users.action.force_delete')" :text="__('codeart::users.confirm.force_delete')" /> @endif
                    @else
                        @if ($can['block'] && $target->is_active) <x-codeart::confirm :action="route('admin.users.block', $target->getKey())" method="POST" :button="__('codeart::users.action.block')" :text="__('codeart::users.confirm.block')" /> @endif
                        @if ($can['block'] && ! $target->is_active && $target->approved_at) <x-codeart::confirm :action="route('admin.users.unblock', $target->getKey())" method="POST" :button="__('codeart::users.action.unblock')" :text="__('codeart::users.confirm.unblock')" variant="secondary" /> @endif
                        @if ($can['resetTwoFactor'] && $target->hasTwoFactorEnabled()) <x-codeart::confirm :action="route('admin.users.reset-2fa', $target->getKey())" method="POST" :button="__('codeart::users.action.reset_2fa')" :text="__('codeart::users.confirm.reset_2fa')" variant="secondary" /> @endif
                        @if ($can['delete']) <x-codeart::confirm :action="route('admin.users.destroy', $target->getKey())" method="DELETE" :button="__('codeart::users.action.delete')" :text="__('codeart::users.confirm.delete')" /> @endif
                    @endif
                </div>
                <p class="mt-3 text-sm text-text-muted">{{ __('codeart::users.edit.danger_hint') }}</p>
            </x-codeart::admin.card>
        </div>

        <x-codeart::admin.card :title="__('codeart::users.edit.info')" icon="circle-user" data-user-info>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::users.col.status') }}</dt><dd><x-codeart::badge :tone="$tone">{{ __('codeart::users.status.'.$status) }}</x-codeart::badge></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::users.col.two_factor') }}</dt><dd>{{ $target->hasTwoFactorEnabled() ? __('codeart::table.yes') : __('codeart::table.no') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::users.col.last_login') }}</dt><dd>{{ $fmt($target->last_login_at) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">{{ __('codeart::users.col.created') }}</dt><dd>{{ $fmt($target->created_at) }}</dd></div>
            </dl>
        </x-codeart::admin.card>
    </div>
</x-codeart::admin.layout>
