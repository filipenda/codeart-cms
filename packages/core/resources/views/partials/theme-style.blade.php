@php($themeCss = app(\Codeart\Core\Theme\ThemeSettings::class)->css())
@if ($themeCss !== '')
<style>{!! $themeCss !!}</style>
@endif
