@props(['column', 'row'])
@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    // One cell, drawn by the column type (see Column for the value shape of each type).
    $value = $column->value($row);
    $initials = fn (string $name): string => Str::of($name)->squish()->explode(' ')->filter()->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('');
@endphp
@switch($column->type)
    @case('number')
        @if ($value === null || $value === '') <span class="text-text-muted">—</span>
        @else <span class="tabular-nums">{{ is_numeric($value) ? number_format((float) $value, (float) $value == (int) $value ? 0 : 2, ',', '.') : $value }}</span> @endif
        @break
    @case('date')
        @php $date = \Codeart\Core\Support\DisplayTime::current()->convert($value); @endphp
        @if ($date) <time datetime="{{ $date->toIso8601String() }}" class="whitespace-nowrap">{{ $date->translatedFormat($column->dateFormat) }}</time>
        @elseif ($value !== null && $value !== '') {{ $value }}
        @else <span class="text-text-muted">—</span> @endif
        @break
    @case('boolean')
        @if ($value)
            <span class="inline-flex items-center gap-1.5 text-success-text"><x-codeart::icon name="check" class="size-4" /><span class="sr-only">{{ __('codeart::table.yes') }}</span></span>
        @else
            <span class="inline-flex items-center gap-1.5 text-text-muted"><x-codeart::icon name="x" class="size-4" /><span class="sr-only">{{ __('codeart::table.no') }}</span></span>
        @endif
        @break
    @case('status')
    @case('badge')
        @if ($value !== null && $value !== '')
            @php [$label, $tone] = $column->options[(string) $value] ?? [(string) $value, 'neutral']; @endphp
            <x-codeart::badge :tone="$tone" class="whitespace-nowrap">{{ $label }}</x-codeart::badge>
        @else <span class="text-text-muted">—</span> @endif
        @break
    @case('avatar')
        @php
            $person = is_array($value) ? $value : ['name' => (string) $value];
            $name = (string) ($person['name'] ?? '');
        @endphp
        <div class="flex items-center gap-3">
            @if (! empty($person['system']))
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-sunken text-text-muted" aria-hidden="true" data-system><x-codeart::icon name="server" class="size-4" /></span>
            @elseif (! empty($person['image']))
                <img src="{{ $person['image'] }}" alt="" loading="lazy" class="size-9 shrink-0 rounded-full bg-surface-sunken object-cover">
            @else
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-text" aria-hidden="true">{{ $initials($name) ?: '?' }}</span>
            @endif
            <span class="min-w-0">
                <span class="block truncate font-medium text-text">{{ $name }}</span>
                @if (! empty($person['subtitle'])) <span class="block truncate text-xs text-text-muted">{{ $person['subtitle'] }}</span> @endif
            </span>
        </div>
        @break
    @case('title-subtitle')
        @php $item = is_array($value) ? $value : ['title' => (string) $value]; @endphp
        <div class="min-w-0 max-w-md">
            @if (! empty($item['url'])) <a href="{{ $item['url'] }}" class="block truncate font-medium text-text hover:underline focus-visible:outline-2 focus-visible:outline-focus">{{ $item['title'] ?? '' }}</a>
            @else <span class="block truncate font-medium text-text">{{ $item['title'] ?? '' }}</span> @endif
            @if (! empty($item['subtitle'])) <span class="block truncate text-xs text-text-muted">{{ $item['subtitle'] }}</span> @endif
        </div>
        @break
    @case('thumbnail')
        @if ($value) <img src="{{ $value }}" alt="" loading="lazy" class="size-10 rounded-control bg-surface-sunken object-cover">
        @else <span class="inline-flex size-10 items-center justify-center rounded-control bg-surface-sunken text-text-muted" aria-hidden="true"><x-codeart::icon name="image" class="size-5" /></span> @endif
        @break
    @case('language-chips')
        @php $chips = is_array($value) ? (array_is_list($value) ? array_fill_keys($value, true) : $value) : []; @endphp
        <span class="flex flex-wrap gap-1">
            @foreach ($chips as $code => $present)
                <x-codeart::badge :tone="$present ? 'primary' : 'neutral'" :dot="false" :class="'px-2 uppercase'.($present ? '' : ' opacity-60')" data-lang="{{ $code }}">{{ $code }}@unless ($present)<span class="sr-only"> ({{ __('codeart::table.missing_translation') }})</span>@endunless</x-codeart::badge>
            @endforeach
        </span>
        @break
    @default
        @if ($value === null || $value === '') <span class="text-text-muted">—</span>
        @else <span class="block max-w-md truncate" title="{{ is_scalar($value) ? $value : '' }}">{{ is_scalar($value) || $value instanceof \Stringable ? $value : '' }}</span> @endif
@endswitch
