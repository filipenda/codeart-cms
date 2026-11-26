@props(['tree', 'version', 'mark' => null])
{{-- Left navigation. Desktop: fixed, can collapse to icons. Mobile: off-canvas drawer (inert while closed). --}}
<aside id="sidebar" x-ref="sidebar"
    class="fixed inset-y-0 start-0 z-40 flex w-72 -translate-x-full flex-col border-e border-sidebar-border bg-sidebar text-sidebar-text transition-transform duration-200 motion-reduce:transition-none lg:z-20 lg:w-64 lg:translate-x-0 lg:in-data-[sidebar=collapsed]:w-18 rtl:translate-x-full lg:rtl:translate-x-0"
    x-bind:class="{ 'translate-x-0 rtl:translate-x-0': drawer }"
    x-bind:inert="isMobile && !drawer" x-bind:aria-hidden="isMobile && !drawer ? 'true' : null"
    x-bind:role="isMobile && drawer ? 'dialog' : null" x-bind:aria-modal="isMobile && drawer ? 'true' : null"
    aria-label="{{ __('codeart::admin.menu.main') }}">
    <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border px-4 lg:in-data-[sidebar=collapsed]:justify-center lg:in-data-[sidebar=collapsed]:px-0">
        <a href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : url('/') }}" class="flex min-w-0 items-center rounded-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:in-data-[sidebar=collapsed]:hidden">
            <x-codeart::logo class="h-8 w-auto max-w-44 object-contain" />
        </a>
        {{-- Collapsed (icons only): the brand mark = the favicon from the theme settings, else the first letter of the site name. --}}
        <a href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : url('/') }}" data-brand-mark aria-label="{{ config('app.name') }}" class="hidden size-10 items-center justify-center rounded-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus lg:in-data-[sidebar=collapsed]:flex">
            @if ($mark)
                <img src="{{ $mark }}" alt="" class="size-8 object-contain">
            @else
                <span class="inline-flex size-9 items-center justify-center rounded-control bg-primary text-base font-bold text-on-primary" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) config('app.name', 'C'), 0, 1)) ?: 'C' }}</span>
            @endif
        </a>
        <button type="button" hidden x-bind:hidden="!isMobile" x-on:click="closeDrawer()" class="inline-flex size-10 items-center justify-center rounded-control text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-text focus-visible:outline-2 focus-visible:outline-focus lg:hidden" aria-label="{{ __('codeart::admin.menu.close') }}">
            <x-codeart::icon name="x" class="size-5" />
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4 lg:in-data-[sidebar=collapsed]:overflow-visible" aria-label="{{ __('codeart::admin.menu.main') }}">
        <ul class="space-y-1">
            @foreach ($tree as $node)
                <x-codeart::admin.menu-node :node="$node" />
            @endforeach
        </ul>
    </nav>

    <p class="shrink-0 border-t border-sidebar-border px-4 py-3 text-xs text-sidebar-muted lg:in-data-[sidebar=collapsed]:hidden">
        {{ __('codeart::admin.version', ['version' => $version]) }}
    </p>
</aside>
