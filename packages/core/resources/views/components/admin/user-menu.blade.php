@props(['user'])
@php
    $name = trim((string) ($user->name ?? ''));
    $initials = collect(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [])->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
    usort($roles, fn ($a, $b) => \Codeart\Core\Auth\RoleRank::of($b) <=> \Codeart\Core\Auth\RoleRank::of($a));
    $role = $roles[0] ?? null;
    $roleLabel = $role === null ? __('codeart::admin.user.no_role') : (\Illuminate\Support\Facades\Lang::has('codeart::admin.user.roles.'.$role) ? __('codeart::admin.user.roles.'.$role) : \Illuminate\Support\Str::headline($role));
    // The two-step page only makes sense while two-step verification is offered at all.
    $showSecurity = \Illuminate\Support\Facades\Route::has('admin.two-factor.setup') && app(\Codeart\Core\Auth\TwoFactorPolicy::class)->available();
@endphp
<details class="relative" x-data x-on:keydown.escape="$el.open = false" x-on:click.outside="$el.open = false">
    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-control py-1 ps-1 pe-2 hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-focus [&::-webkit-details-marker]:hidden" aria-label="{{ __('codeart::admin.user.menu') }}">
        <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-on-primary" aria-hidden="true">{{ $initials }}</span>
        <span class="hidden min-w-0 text-start leading-tight md:block">
            <span class="block max-w-40 truncate text-sm font-semibold text-text">{{ $name }}</span>
            <span class="block max-w-40 truncate text-xs text-text-muted">{{ $roleLabel }}</span>
        </span>
        <x-codeart::icon name="chevron-down" class="hidden size-4 text-text-muted md:block" />
    </summary>
    <div class="absolute end-0 z-20 mt-2 w-64 rounded-control border border-border bg-surface p-1 shadow-card">
        <div class="border-b border-border px-3 py-2.5 md:hidden">
            <p class="truncate text-sm font-semibold text-text">{{ $name }}</p>
            <p class="truncate text-xs text-text-muted">{{ $roleLabel }}</p>
        </div>
        @if ($showSecurity)
            <a href="{{ route('admin.two-factor.setup') }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                <x-codeart::icon name="shield-check" class="size-4" /> {{ __('codeart::admin.user.security') }}
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                <x-codeart::icon name="log-out" class="size-4" /> {{ __('codeart::admin.user.logout') }}
            </button>
        </form>
    </div>
</details>
