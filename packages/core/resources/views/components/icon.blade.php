@props(['name', 'class' => 'size-5'])
{{-- Inline SVG icons (stroke = currentColor), decorative: aria-hidden. Add icons here; no icon CDN. --}}
<svg {{ $attributes->merge(['class' => 'shrink-0 '.$class]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6.5L20.5 7"/>@break
    @case('lock')<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>@break
    @case('eye')<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.75"/>@break
    @case('eye-off')<path d="M3 3l18 18"/><path d="M10.6 5.6A9.6 9.6 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a17 17 0 0 1-3.2 4"/><path d="M6.5 6.9C3.7 8.6 2 12 2 12s3.6 6.5 10 6.5c1.5 0 2.8-.3 4-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>@break
    @case('arrow-right')<path d="M4.5 12h15"/><path d="m13.5 6 6 6-6 6"/>@break
    @case('check')<path d="m4.5 12.5 5 5 10-11"/>@break
    @case('alert')<path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4.5"/><path d="M12 17.25v.01"/>@break
    @case('globe')<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3Z"/>@break
    @case('shield')<path d="M12 3 4.5 6v5.5c0 4.4 3 8 7.5 9.5 4.5-1.5 7.5-5.1 7.5-9.5V6L12 3Z"/><path d="m9 12 2.2 2.2L15.5 10"/>@break
    @case('chevron-down')<path d="m6 9.5 6 6 6-6"/>@break
    @case('user')<circle cx="12" cy="8" r="3.75"/><path d="M4.5 20c.8-3.6 3.8-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/>@break
@default{!! app(\Codeart\Core\Admin\Icons::class)->get($name) !!}
@endswitch
</svg>
