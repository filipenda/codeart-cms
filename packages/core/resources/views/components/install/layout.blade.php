@props(['title', 'step' => null])
@php
    $steps = ['key', 'requirements', 'database', 'site', 'admin', 'modules', 'run'];
    $index = $step === null ? null : array_search($step, $steps, true);
    $locales = (array) config('codeart.locales.available', []);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ __('codeart::install.title') }}</title>
    <x-codeart::assets />
    @include('codeart::partials.theme-style')
</head>
<body class="min-h-screen bg-surface-alt text-text antialiased">
    <a href="#main" class="sr-only rounded-control bg-surface px-4 py-2 text-text focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-30">{{ __('codeart::ui.skip_to_content') }}</a>
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col gap-6 px-4 py-10">
        <header class="flex items-center justify-between gap-4">
            <span class="text-lg font-bold">{{ __('codeart::install.title') }}</span>
            <nav aria-label="{{ __('codeart::ui.language') }}" class="flex gap-2 text-sm">
                @foreach ($locales as $code => $info)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" @if ($code === app()->getLocale()) aria-current="true" class="font-bold" @endif>{{ $info['name'] ?? $code }}</a>
                @endforeach
            </nav>
        </header>
        <main id="main" class="space-y-6 rounded-card bg-surface p-8 shadow-card max-[480px]:p-5">
            @if ($index !== null)
                <p class="text-sm text-text-muted">{{ __('codeart::install.step_of', ['n' => $index + 1, 'total' => count($steps)]) }} · {{ __('codeart::install.steps.'.$step) }}</p>
                <div class="h-2 w-full rounded-full bg-surface-alt" role="progressbar" aria-valuemin="1" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $index + 1 }}"><div class="h-2 rounded-full bg-primary" style="width: {{ (int) round(($index + 1) / count($steps) * 100) }}%"></div></div>
            @endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
