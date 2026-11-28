@props(['id', 'title' => null, 'size' => 'md'])
@php
    $width = match ($size) { 'sm' => 'max-w-sm', 'lg' => 'max-w-2xl', default => 'max-w-lg' };
@endphp
{{-- Native <dialog> opened with showModal(): focus is trapped, Esc closes, the page behind is inert and the browser
     returns focus to the opener. Open it with any element carrying data-modal-open="{{ $id }}"; [data-modal-close] closes;
     a click on the backdrop closes. Slots: title (also a plain prop), default = body, footer. Needs JS to open. --}}
<dialog id="{{ $id }}" data-modal aria-labelledby="{{ $id }}-title" {{ $attributes->class(['m-auto w-[calc(100%-2rem)] min-w-0 whitespace-normal break-words overflow-x-hidden rounded-card border border-border bg-surface p-0 text-text shadow-card backdrop:bg-scrim', $width]) }}>
    <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
        <h2 id="{{ $id }}-title" class="min-w-0 flex-1 whitespace-normal break-words text-lg font-semibold">{{ $title }}</h2>
        <button type="button" data-modal-close aria-label="{{ __('codeart::ui.c.close') }}" class="-m-1 rounded-md p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus"><x-codeart::icon name="x" class="size-5" /></button>
    </div>
    <div class="whitespace-normal break-words px-6 py-5 text-sm" data-modal-body>{{ $slot }}</div>
    @isset($footer)
        <div class="flex flex-wrap justify-end gap-3 border-t border-border px-6 py-4">{{ $footer }}</div>
    @endisset
</dialog>
