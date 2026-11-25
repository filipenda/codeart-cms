@props(['title' => null])
@php
    // Flash messages: Fortify sends a key (verification-link-sent, ...) or a finished sentence; unknown keys are not shown raw.
    $status = session('status');
    $statusText = null;
    if (is_string($status) && $status !== '') {
        $statusText = \Illuminate\Support\Facades\Lang::has('codeart::ui.status.'.$status)
            ? __('codeart::ui.status.'.$status)
            : (str_contains($status, ' ') ? $status : null);
    }
    $favicon = app(\Codeart\Core\Theme\ThemeSettings::class)->favicon() !== null ? app(\Codeart\Core\Branding\BrandingImageUploader::class)->url('theme.favicon') : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @include('codeart::partials.theme-attributes')>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($favicon) <link rel="icon" href="{{ $favicon }}"> @endif
    <x-codeart::assets />
    @include('codeart::partials.theme-style')
</head>
<body class="min-h-screen bg-surface-alt text-text antialiased">
    <a href="#main" class="sr-only rounded-control bg-surface px-4 py-2 text-text focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-30">{{ __('codeart::ui.skip_to_content') }}</a>

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-20 max-[480px]:items-start max-[480px]:p-0">
        {{-- Decorative shapes: soft primary tint, drawn with the token color, partly outside the viewport. --}}
        <svg class="pointer-events-none absolute -bottom-48 -start-48 size-[36rem] fill-primary-soft max-[480px]:-bottom-32 max-[480px]:-start-32 max-[480px]:size-72" viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle cx="50" cy="50" r="50" /></svg>
        <svg class="pointer-events-none absolute -end-40 -top-40 size-[30rem] fill-primary-soft max-[480px]:-end-28 max-[480px]:-top-28 max-[480px]:size-60" viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle cx="50" cy="50" r="50" /></svg>

        <div class="absolute end-4 top-4 z-20"><x-codeart::language-dropdown /></div>

        <main id="main" class="relative z-10 flex w-full justify-center">
            <x-codeart::card>
                @if ($statusText) <x-codeart::alert type="success">{{ $statusText }}</x-codeart::alert> @endif
                {{ $slot }}
                @if (isset($social) && $social->isNotEmpty())
                    <x-codeart::divider :text="__('codeart::ui.or')" />
                    <div class="space-y-3">{{ $social }}</div>
                @endif
            </x-codeart::card>
        </main>
    </div>
</body>
</html>
