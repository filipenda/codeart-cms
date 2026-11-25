@props(['text' => null])
@if ($text)
    <div class="flex items-center gap-4 text-sm text-text-muted" role="separator" aria-label="{{ $text }}">
        <span class="h-px flex-1 bg-border"></span><span>{{ $text }}</span><span class="h-px flex-1 bg-border"></span>
    </div>
@else
    <hr class="border-0 border-t border-border">
@endif
