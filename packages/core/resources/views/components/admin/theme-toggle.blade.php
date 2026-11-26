{{-- Per-browser theme choice (localStorage); until chosen, the theme.mode setting applies. Needs JavaScript, so it stays hidden without. --}}
<div role="group" aria-label="{{ __('codeart::admin.theme.label') }}" hidden x-bind:hidden="false" class="flex items-center rounded-control border border-border bg-surface p-0.5">
    @foreach (['light' => 'sun', 'dark' => 'moon', 'auto' => 'monitor'] as $mode => $icon)
        <button type="button" x-on:click="setTheme('{{ $mode }}')" x-bind:aria-pressed="theme === '{{ $mode }}' ? 'true' : 'false'" aria-pressed="false"
            title="{{ __('codeart::admin.theme.'.$mode) }}" aria-label="{{ __('codeart::admin.theme.'.$mode) }}"
            class="inline-flex size-8 items-center justify-center rounded-sm text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-bind:class="theme === '{{ $mode }}' ? 'bg-primary text-on-primary hover:text-on-primary' : ''">
            <x-codeart::icon :name="$icon" class="size-4" />
        </button>
    @endforeach
</div>
