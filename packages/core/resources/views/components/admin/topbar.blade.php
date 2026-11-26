@props(['user'])
<header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-border bg-topbar px-4 sm:px-6">
    <button type="button" hidden x-bind:hidden="false" x-on:click="toggleSidebar()" class="inline-flex size-10 shrink-0 items-center justify-center rounded-control text-text-muted hover:bg-surface-alt hover:text-text focus-visible:outline-2 focus-visible:outline-focus"
        aria-label="{{ __('codeart::admin.menu.toggle') }}" aria-controls="sidebar" x-bind:aria-expanded="(isMobile ? drawer : !collapsed) ? 'true' : 'false'">
        <x-codeart::icon name="menu" class="size-5" />
    </button>

    {{-- Opens the command palette (no JavaScript, no palette: the button stays hidden). --}}
    <button type="button" hidden x-bind:hidden="false" x-on:click="openPalette()" aria-haspopup="dialog"
        class="flex h-10 min-w-0 max-w-md flex-1 items-center gap-2 rounded-control border border-border bg-surface px-3 text-start text-sm text-text-muted hover:border-text-muted focus-visible:outline-2 focus-visible:outline-focus">
        <x-codeart::icon name="search" class="size-4" />
        <span class="flex-1 truncate">{{ __('codeart::admin.search.placeholder') }}</span>
        <kbd class="hidden rounded-sm border border-border bg-surface-alt px-1.5 py-0.5 font-sans text-xs text-text-muted sm:inline">Ctrl K</kbd>
    </button>

    <div class="ms-auto flex items-center gap-2">
        <x-codeart::admin.user-menu :user="$user" />
    </div>
</header>
