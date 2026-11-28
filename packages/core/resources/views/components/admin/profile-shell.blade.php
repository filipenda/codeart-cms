@props(['tab' => 'personal', 'me', 'role' => null, 'avatarUrl' => null])
@php
    $roleLabel = $role === null ? __('codeart::admin.user.no_role') : (\Illuminate\Support\Facades\Lang::has('codeart::admin.user.roles.'.$role) ? __('codeart::admin.user.roles.'.$role) : \Illuminate\Support\Str::headline($role));
    $tabs = [
        'personal' => [__('codeart::profile.tab_personal'), route('admin.profile'), 'circle-user'],
        'password' => [__('codeart::profile.tab_password'), route('admin.profile.password.show'), 'lock'],
        'two-factor' => [__('codeart::profile.tab_two_factor'), route('admin.profile.two-factor'), 'shield'],
    ];
    // The session list needs the database session driver; without it the tab is not drawn (no dead tabs).
    if (\Codeart\Core\Profile\SessionsController::enabled()) {
        $tabs['sessions'] = [__('codeart::profile.tab_sessions'), route('admin.profile.sessions'), 'monitor'];
    }
    $time = \Codeart\Core\Support\DisplayTime::current();
    $lastLogin = $time->convert($me->last_login_at);
@endphp
<x-codeart::admin.layout :title="__('codeart::profile.title')">
    <div class="mb-6 flex items-center gap-4">
        <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-card bg-primary-soft text-link" aria-hidden="true"><x-codeart::icon name="circle-user" class="size-6" /></span>
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-bold text-text sm:text-3xl">{{ __('codeart::profile.title') }}</h1>
            <p class="text-text-muted">{{ __('codeart::profile.description') }}</p>
        </div>
    </div>

    <nav aria-label="{{ __('codeart::profile.title') }}" class="mb-6 overflow-x-auto border-b border-border" data-profile-tabs>
        <ul class="flex min-w-max gap-1">
            @foreach ($tabs as $key => [$label, $href, $icon])
                <li>
                    <a href="{{ $href }}" data-tab="{{ $key }}" @if ($key === $tab) aria-current="page" @endif
                        class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-[3px] px-4 pb-3 pt-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus {{ $key === $tab ? 'border-primary font-bold text-text' : 'border-transparent text-text-muted hover:text-text' }}">
                        <x-codeart::icon :name="$icon" class="size-4" />{{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="grid items-start gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-card border border-border bg-surface" data-profile-card aria-label="{{ __('codeart::profile.card') }}">
            {{-- Decorative header: a soft vertical gradient of the primary color, glows and three translucent waves. Everything is
                 derived from the primary token through CSS variables (color-mix / fill-primary), no raster, no external file. --}}
            <div class="relative h-32 overflow-hidden" aria-hidden="true" data-profile-banner
                style="background-image: radial-gradient(120% 90% at 15% 0%, color-mix(in srgb, var(--color-primary) 38%, transparent), transparent 60%), radial-gradient(90% 80% at 90% 10%, color-mix(in srgb, var(--color-primary) 22%, transparent), transparent 65%), linear-gradient(to bottom, color-mix(in srgb, var(--color-primary) 26%, var(--color-surface)), var(--color-surface-alt))">
                <svg class="absolute inset-x-0 bottom-0 h-24 w-full" viewBox="0 0 340 96" preserveAspectRatio="none" focusable="false">
                    <path class="fill-primary opacity-10" d="M0 38 C60 6 120 66 190 36 S300 4 340 30 V96 H0 Z" />
                    <path class="fill-primary opacity-15" d="M0 58 C70 34 130 82 210 56 S312 40 340 60 V96 H0 Z" />
                    <path class="fill-primary opacity-25" d="M0 78 C80 60 140 92 220 74 S316 64 340 80 V96 H0 Z" />
                </svg>
                <div class="absolute inset-x-0 bottom-0 h-10 bg-linear-to-b from-transparent to-surface"></div>
            </div>
            <div class="-mt-14 flex flex-col items-center px-5 pb-5 text-center">
                <div class="relative">
                    <x-codeart::avatar :user="$me" class="size-28 text-3xl ring-4 ring-surface outline-2 outline-offset-0 outline-primary/40" data-profile-avatar />
                    <form method="POST" action="{{ route('admin.profile.avatar') }}" enctype="multipart/form-data" data-avatar-form
                        class="absolute bottom-0 end-0" x-data>
                        @csrf
                        <label class="inline-flex size-9 cursor-pointer items-center justify-center rounded-full border border-border bg-surface text-text shadow-card hover:bg-surface-alt focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus" title="{{ __('codeart::profile.avatar_change') }}">
                            <x-codeart::icon name="camera" class="size-4" />
                            <span class="sr-only">{{ __('codeart::profile.avatar_change') }}</span>
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" x-on:change="$el.form.requestSubmit()">
                        </label>
                        <noscript><button type="submit" class="mt-2 text-xs text-link underline">{{ __('codeart::profile.avatar_upload') }}</button></noscript>
                    </form>
                </div>
                @error('avatar')<p class="mt-3 text-sm text-danger-text" role="alert" data-avatar-error>{{ $message }}</p>@enderror
                @if ($avatarUrl)
                    <form method="POST" action="{{ route('admin.profile.avatar.remove') }}" class="mt-2">@csrf @method('DELETE')
                        <button type="submit" data-avatar-remove class="rounded-sm text-xs font-medium text-text-muted underline hover:text-text focus-visible:outline-2 focus-visible:outline-focus">{{ __('codeart::profile.avatar_remove') }}</button>
                    </form>
                @endif
                <h2 class="mt-3 max-w-full truncate text-lg font-semibold text-text" data-profile-name>{{ $me->name }}</h2>
                <p class="max-w-full truncate text-sm text-text-muted" data-profile-card-email>{{ $me->email }}</p>
                <x-codeart::badge tone="primary" class="mt-3" :dot="false" data-profile-role>{{ $roleLabel }}</x-codeart::badge>
            </div>
            <dl class="divide-y divide-border border-t border-border text-sm">
                <div class="flex items-center justify-between gap-3 px-5 py-3"><dt class="text-text-muted">{{ __('codeart::profile.member_since') }}</dt><dd class="text-end font-medium text-text" data-member-since>{{ $time->format($me->created_at, 'd.m.Y.') }}</dd></div>
                <div class="flex items-center justify-between gap-3 px-5 py-3"><dt class="text-text-muted">{{ __('codeart::profile.last_sign_in') }}</dt><dd class="text-end font-medium text-text" data-last-sign-in @if ($lastLogin) title="{{ $lastLogin->translatedFormat('d.m.Y. H:i') }}" @endif>{{ $lastLogin?->diffForHumans() ?? '—' }}</dd></div>
                @if (app(\Codeart\Core\Privacy\IpPrivacy::class)->enabled())
                    <div class="flex items-center justify-between gap-3 px-5 py-3"><dt class="text-text-muted">{{ __('codeart::profile.ip_address') }}</dt><dd class="text-end font-medium text-text" data-last-ip>{{ app(\Codeart\Core\Privacy\IpPrivacy::class)->display($me->last_login_ip) ?: '—' }}</dd></div>
                @endif
            </dl>
        </aside>

        <div class="min-w-0 space-y-6" data-profile-content>{{ $slot }}</div>
    </div>
</x-codeart::admin.layout>
