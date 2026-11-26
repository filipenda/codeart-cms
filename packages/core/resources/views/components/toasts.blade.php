@php
    // Session flashes: `toast` (string, or ['type' => ..., 'message' => ...], or a list of those), `status` (success), `error` (danger).
    $normalize = function ($item, string $type): ?array {
        if (is_array($item)) {
            return isset($item['message']) ? ['type' => (string) ($item['type'] ?? $type), 'message' => (string) $item['message']] : null;
        }

        return is_string($item) && $item !== '' ? ['type' => $type, 'message' => $item] : null;
    };
    $flashed = [];
    $toast = session('toast');
    foreach (is_array($toast) && array_is_list($toast) ? $toast : [$toast] as $item) {
        if ($n = $normalize($item, 'info')) { $flashed[] = $n; }
    }
    foreach ([['status', 'success'], ['error', 'danger']] as [$key, $type]) {
        if ($n = $normalize(session($key), $type)) { $flashed[] = $n; }
    }
@endphp
{{-- Toast stack: aria-live region. Flashed messages are in the first paint; JS adds more from the window event
     `codeart:toast` ({ message, type: info|success|warning|danger, timeout }) and removes them after a few seconds.
     Without JS the flashes show as plain alerts above the content. --}}
<div x-data="codeartToasts(@js($flashed))" x-on:codeart:toast.window="add($event.detail ?? {})" data-toasts
    class="pointer-events-none fixed inset-x-4 bottom-4 z-60 flex flex-col items-end gap-3 sm:inset-x-auto sm:end-4 sm:w-96" role="region" aria-label="{{ __('codeart::ui.c.notifications') }}" aria-live="polite">
    <template x-for="item in items" x-bind:key="item.id">
        <div class="pointer-events-auto flex w-full items-start gap-3 rounded-control border bg-surface px-4 py-3 text-sm text-text shadow-card"
            x-bind:class="{ 'border-success': item.type === 'success', 'border-danger': item.type === 'danger', 'border-warning': item.type === 'warning', 'border-info': item.type === 'info' }"
            x-bind:role="item.type === 'danger' ? 'alert' : 'status'">
            <span class="mt-0.5 shrink-0" x-bind:class="{ 'text-success-text': item.type === 'success', 'text-danger-text': item.type === 'danger', 'text-warning-text': item.type === 'warning', 'text-info-text': item.type === 'info' }" aria-hidden="true">
                <x-codeart::icon name="info" class="size-5" x-show="item.type === 'info'" />
                <x-codeart::icon name="circle-check" class="size-5" x-show="item.type === 'success'" x-cloak />
                <x-codeart::icon name="triangle-alert" class="size-5" x-show="item.type === 'warning' || item.type === 'danger'" x-cloak />
            </span>
            <p class="min-w-0 flex-1" x-text="item.message"></p>
            <button type="button" x-on:click="remove(item.id)" aria-label="{{ __('codeart::ui.c.dismiss') }}" class="-m-1 shrink-0 rounded-md p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus"><x-codeart::icon name="x" class="size-4" /></button>
        </div>
    </template>
</div>
@if ($flashed !== [])
    <noscript>
        <div class="mb-6 space-y-2">
            @foreach ($flashed as $item)
                <x-codeart::alert :type="$item['type']">{{ $item['message'] }}</x-codeart::alert>
            @endforeach
        </div>
    </noscript>
@endif
