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
    $locales = app(\Codeart\Core\Translations\Locales::class);
    $enabled = $locales->enabled();
    $current = $locales->current();
@endphp
<x-codeart::dropdown id="user-menu-panel" :label="__('codeart::admin.user.menu')" width="w-64" trigger-class="flex items-center gap-3 rounded-control py-1 ps-1 pe-2 hover:bg-surface-alt">
    <x-slot:trigger>
        <span class="inline-flex size-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-on-primary" aria-hidden="true">{{ $initials }}</span>
        <span class="hidden min-w-0 text-start leading-tight md:block">
            <span class="block max-w-40 truncate text-sm font-semibold text-text">{{ $name }}</span>
            <span class="block max-w-40 truncate text-xs text-text-muted">{{ $roleLabel }}</span>
        </span>
        <x-codeart::icon name="chevron-down" class="hidden size-4 text-text-muted md:block" />
    </x-slot:trigger>
        <div class="border-b border-border px-3 py-2.5">
            <p class="truncate text-sm font-semibold text-text">{{ $name }}</p>
            <p class="truncate text-xs text-text-muted">{{ $roleLabel }}</p>
        </div>

        <div class="border-b border-border py-1" role="radiogroup" aria-labelledby="user-menu-theme">
            <p id="user-menu-theme" class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('codeart::admin.theme.label') }}</p>
            @foreach (['light' => 'sun', 'dark' => 'moon', 'auto' => 'monitor'] as $mode => $icon)
                <button type="button" role="radio" data-menu-item data-theme-option="{{ $mode }}" x-on:click="setTheme('{{ $mode }}')" aria-checked="false" x-bind:aria-checked="theme === '{{ $mode }}' ? 'true' : 'false'"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                    <x-codeart::icon :name="$icon" class="size-4" />
                    <span class="flex-1">{{ __('codeart::admin.theme.'.$mode) }}</span>
                    <x-codeart::icon name="check" class="size-4" x-show="theme === '{{ $mode }}'" x-cloak />
                </button>
            @endforeach
        </div>

        @if (count($enabled) > 1)
            <form method="POST" action="{{ route('codeart.locale') }}" class="border-b border-border py-1">
                @csrf
                <p id="user-menu-language" class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('codeart::admin.language') }}</p>
                @foreach ($enabled as $code)
                    <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" data-menu-item @if ($code === $current) aria-current="true" @endif
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus {{ $code === $current ? 'font-semibold' : '' }}">
                        <span class="flex-1">{{ $locales->label($code) }}</span>
                        @if ($code === $current) <x-codeart::icon name="check" class="size-4" /> @endif
                    </button>
                @endforeach
            </form>
        @endif

        @if (\Illuminate\Support\Facades\Route::has('admin.profile'))
            <a href="{{ route('admin.profile') }}" data-menu-item data-profile-link class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                <x-codeart::icon name="circle-user" class="size-4 text-text-muted" /> {{ __('codeart::profile.title') }}
            </a>
        @endif
        @if ($showSecurity)
            <a href="{{ route('admin.two-factor.setup') }}" data-menu-item class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                <x-codeart::icon name="shield-check" class="size-4" /> {{ __('codeart::admin.user.security') }}
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" data-menu-item class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                <x-codeart::icon name="log-out" class="size-4" /> {{ __('codeart::admin.user.logout') }}
            </button>
        </form>
</x-codeart::dropdown>
