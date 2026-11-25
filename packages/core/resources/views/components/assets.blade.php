@php($assets = app(\Codeart\Core\Theme\Assets::class))
<link rel="stylesheet" href="{{ $assets->url('codeart.css') }}">
<script src="{{ $assets->url('codeart.js') }}" defer></script>
