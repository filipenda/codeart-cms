@props(['result'])
@php
    // first, previous, a window of numbers around the current page, next, last. Links keep the whole state.
    $current = $result->state->page;
    $last = $result->lastPage();
    $from = max(1, min($current - 2, $last - 4));
    $to = min($last, max($current + 2, 5));
    $item = 'inline-flex h-10 min-w-10 items-center justify-center rounded-control border px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';
    $link = $item.' border-border bg-surface text-text hover:bg-surface-alt';
    $off = $item.' border-border bg-surface text-text-muted opacity-50';
    $nav = [['first', 1, 'chevrons-left'], ['previous', $current - 1, 'chevron-left']];
@endphp
@if ($last > 1)
    <nav aria-label="{{ __('codeart::table.pagination') }}" class="flex flex-wrap items-center gap-1.5" data-pagination>
        @foreach ($nav as [$key, $page, $icon])
            @if ($current > 1)
                <a href="{{ $result->url(['page' => $page]) }}" rel="{{ $key === 'previous' ? 'prev' : 'first' }}" aria-label="{{ __('codeart::table.'.$key) }}" class="{{ $link }}"><x-codeart::icon :name="$icon" class="size-4" /></a>
            @else
                <span aria-hidden="true" class="{{ $off }}"><x-codeart::icon :name="$icon" class="size-4" /></span>
            @endif
        @endforeach
        @foreach (range($from, $to) as $page)
            @if ($page === $current)
                <span aria-current="page" aria-label="{{ __('codeart::table.page', ['page' => $page]) }}" class="{{ $item }} border-primary bg-primary font-semibold text-on-primary">{{ $page }}</span>
            @else
                <a href="{{ $result->url(['page' => $page]) }}" aria-label="{{ __('codeart::table.page', ['page' => $page]) }}" class="{{ $link }}">{{ $page }}</a>
            @endif
        @endforeach
        @foreach ([['next', $current + 1, 'chevron-right'], ['last', $last, 'chevrons-right']] as [$key, $page, $icon])
            @if ($current < $last)
                <a href="{{ $result->url(['page' => $page]) }}" rel="{{ $key === 'next' ? 'next' : 'last' }}" aria-label="{{ __('codeart::table.'.$key) }}" class="{{ $link }}"><x-codeart::icon :name="$icon" class="size-4" /></a>
            @else
                <span aria-hidden="true" class="{{ $off }}"><x-codeart::icon :name="$icon" class="size-4" /></span>
            @endif
        @endforeach
    </nav>
@endif
