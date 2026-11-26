@props(['node'])
@php
    $link = 'group relative flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:in-data-[sidebar=collapsed]:justify-center lg:in-data-[sidebar=collapsed]:px-0 ';
    $idle = 'text-sidebar-text hover:bg-sidebar-hover';
    $on = 'bg-primary text-on-primary hover:bg-primary-hover';
@endphp
@if ($node->isGroup())
    <li x-data="{ open: {{ $node->open() ? 'true' : 'false' }} }">
        <button type="button" class="{{ $link }}w-full text-start {{ $node->open() ? 'bg-sidebar-hover' : $idle }}"
            x-on:click="if (collapsedDesktop) { expandSidebar(); open = true } else { open = !open }"
            x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="{{ $node->open() ? 'true' : 'false' }}" aria-controls="group-{{ $node->item->key }}"
            aria-label="{{ $node->label }}">
            <x-codeart::icon :name="$node->item->icon" class="size-5" />
            <span role="tooltip" data-tooltip class="pointer-events-none absolute start-full z-50 ms-3 hidden whitespace-nowrap rounded-md bg-text px-2 py-1 text-xs font-medium text-surface shadow-card lg:in-data-[sidebar=collapsed]:group-hover:block lg:in-data-[sidebar=collapsed]:group-focus-visible:block" aria-hidden="true">{{ $node->label }}</span>
            <span class="flex-1 truncate lg:in-data-[sidebar=collapsed]:hidden">{{ $node->label }}</span>
            <x-codeart::icon name="chevron-down" class="size-4 transition-transform lg:in-data-[sidebar=collapsed]:hidden" x-bind:class="open ? 'rotate-180' : ''" />
        </button>
        <ul id="group-{{ $node->item->key }}" x-show="open" @unless ($node->open()) x-cloak @endunless class="mt-1 space-y-1 lg:in-data-[sidebar=collapsed]:hidden">
            @foreach ($node->children as $child)
                <li>
                    <a href="{{ $child->url }}" class="{{ $link }}ps-11 {{ $child->active ? $on : $idle }}" @if ($child->active) aria-current="page" @endif x-on:click="if (isMobile) drawer = false">
                        <span class="absolute start-6 size-1.5 rounded-full bg-current opacity-60" aria-hidden="true"></span>
                        <span class="truncate">{{ $child->label }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </li>
@else
    <li>
        <a href="{{ $node->url }}" class="{{ $link }}{{ $node->active ? $on : $idle }}" @if ($node->active) aria-current="page" @endif
            aria-label="{{ $node->label }}" x-on:click="if (isMobile) drawer = false">
            <x-codeart::icon :name="$node->item->icon" class="size-5" />
            <span role="tooltip" data-tooltip class="pointer-events-none absolute start-full z-50 ms-3 hidden whitespace-nowrap rounded-md bg-text px-2 py-1 text-xs font-medium text-surface shadow-card lg:in-data-[sidebar=collapsed]:group-hover:block lg:in-data-[sidebar=collapsed]:group-focus-visible:block" aria-hidden="true">{{ $node->label }}</span>
            <span class="truncate lg:in-data-[sidebar=collapsed]:hidden">{{ $node->label }}</span>
        </a>
    </li>
@endif
