@props(['user' => null, 'class' => 'size-9 text-sm'])
@php
    $name = trim((string) ($user?->getAttribute('name') ?? ''));
    $initials = collect(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [])->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $url = \Codeart\Core\Profile\Avatar::url($user);
@endphp
{{-- Round avatar: the user's picture (object-cover) or initials on the primary color. Decorative: the name sits next to it. --}}
@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" data-avatar-image {{ $attributes->merge(['class' => 'shrink-0 rounded-full bg-surface-sunken object-cover '.$class]) }}>
@else
    <span data-avatar-initials aria-hidden="true" {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-on-primary '.$class]) }}>{{ $initials }}</span>
@endif
