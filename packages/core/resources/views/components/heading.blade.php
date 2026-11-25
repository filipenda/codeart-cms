@props(['title', 'text' => null])
<div class="space-y-2 text-center">
    <h1 {{ $attributes->class('text-[2rem] font-bold leading-10 text-text') }}>{{ $title }}</h1>
    @if ($text) <p class="text-base text-text-muted">{{ $text }}</p> @endif
</div>
