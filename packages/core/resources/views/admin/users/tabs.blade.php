@php
    $items = [['users', 'admin.users.index', 'users.view', 'users'], ['invitations', 'admin.users.invitations', 'users.create', 'invitations']];
@endphp
<nav class="mb-6 flex gap-1 border-b border-border" aria-label="{{ __('codeart::users.title') }}" data-users-tabs>
    @foreach ($items as [$key, $route, $permission, $label])
        @if (Route::has($route) && auth(\Codeart\Core\Auth\Admin::guardName())->user()?->can($permission))
            <a href="{{ route($route) }}" @if ($active === $key) aria-current="page" @endif
                class="-mb-px border-b-[3px] px-4 pb-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-focus {{ $active === $key ? 'border-primary font-bold text-text' : 'border-transparent text-text-muted hover:text-text' }}">{{ __('codeart::users.tab.'.$label) }}</a>
        @endif
    @endforeach
</nav>
