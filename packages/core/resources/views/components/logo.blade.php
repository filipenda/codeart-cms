@props(['class' => 'h-12 w-auto'])
@php
    $theme = app(\Codeart\Core\Theme\ThemeSettings::class);
    $uploader = app(\Codeart\Core\Branding\BrandingImageUploader::class);
    $light = $theme->logoLight() !== null ? $uploader->url('theme.logo_light') : null;
    $dark = $theme->logoDark() !== null ? $uploader->url('theme.logo_dark') : null;
    $name = (string) config('app.name');
@endphp
@if ($light !== null || $dark !== null)
    {{-- Light logo on light themes, dark logo on dark (CSS by data-theme, also "auto"). One logo only = used for both. --}}
    @if ($light !== null && $dark !== null)
        <img src="{{ $light }}" alt="{{ $name }}" class="codeart-logo-light {{ $class }}">
        <img src="{{ $dark }}" alt="" class="codeart-logo-dark {{ $class }}" aria-hidden="true">
    @else
        <img src="{{ $light ?? $dark }}" alt="{{ $name }}" class="{{ $class }}">
    @endif
@else
    <span {{ $attributes->class(['inline-block text-2xl font-bold uppercase tracking-[0.35em] text-text']) }}>CODEART</span>
@endif
