@props(['title' => null])
@php
    use Codeart\Core\Admin\Navigation\AdminMenu;
    use Codeart\Core\Admin\Search\SearchRegistry;
    use Codeart\Core\Admin\Version;
    use Codeart\Core\Auth\Admin;
    use Codeart\Core\Theme\ThemeSettings;

    $user = Admin::guard()->user();
    $tree = app(AdminMenu::class)->tree($user, request()->route()?->getName());
    $search = app(SearchRegistry::class)->results($user);
    $theme = app(ThemeSettings::class);
    $favicon = $theme->favicon() !== null ? app(\Codeart\Core\Branding\BrandingImageUploader::class)->url('theme.favicon') : null;
    $version = Version::cms();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('codeart::partials.theme-attributes')>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($favicon) <link rel="icon" href="{{ $favicon }}"> @endif
    {{-- Applied before first paint (no flash): the browser's own theme choice and the collapsed sidebar. --}}
    <script>try{var d=document.documentElement,t=localStorage.getItem('codeart.theme');if(t==='light'||t==='dark'||t==='auto')d.setAttribute('data-theme',t);d.setAttribute('data-sidebar',localStorage.getItem('codeart.sidebar')==='collapsed'?'collapsed':'expanded')}catch(e){}</script>
    <x-codeart::assets />
    @include('codeart::partials.theme-style')
    <noscript><style>[x-cloak]{display:block !important}</style></noscript>
</head>
<body class="min-h-screen bg-surface-alt text-text antialiased" x-data="codeartShell" data-theme-default="{{ $theme->mode() }}"
    x-on:keydown.window="onKeydown($event)" x-on:pageshow.window="drawer = false">
    <a href="#main" class="sr-only rounded-control bg-surface px-4 py-2 text-text focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-60 focus-visible:outline-2 focus-visible:outline-focus">{{ __('codeart::ui.skip_to_content') }}</a>

    <div x-show="drawer" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-scrim lg:hidden" x-on:click="closeDrawer()" aria-hidden="true"></div>

    <x-codeart::admin.sidebar :tree="$tree" :version="$version" :mark="$favicon" />

    <div class="flex min-h-screen flex-col lg:ps-64 lg:in-data-[sidebar=collapsed]:ps-18">
        <x-codeart::admin.topbar :user="$user" />

        <main id="main" tabindex="-1" class="w-full max-w-(--content-max) flex-1 px-4 py-6 focus:outline-none sm:px-6 lg:px-8 lg:py-8">
            @if (session('status'))
                <x-codeart::alert type="success" class="mb-6">{{ session('status') }}</x-codeart::alert>
            @endif
            {{ $slot }}
        </main>
    </div>

    <x-codeart::admin.command-palette :items="$search" />
</body>
</html>
