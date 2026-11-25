<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ app(\Codeart\Core\Theme\ThemeSettings::class)->defaultMode() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/codeart/codeart.css') }}">
    @include('codeart::partials.theme-style')
</head>
<body>
    <h1 class="text-text">{{ config('app.name') }}</h1>
</body>
</html>
