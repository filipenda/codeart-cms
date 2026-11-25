@props(['icon' => 'lock'])
{{-- Password field with a show/hide toggle. Without JS it stays a plain password field (the toggle is hidden). --}}
<div class="relative" x-data="{ show: false }">
    <span class="pointer-events-none absolute inset-y-0 start-4 flex items-center text-text-muted"><x-codeart::icon :name="$icon" /></span>
    <input type="password" x-bind:type="show ? 'text' : 'password'" {{ $attributes->class([
        'block h-13 w-full rounded-control border border-border bg-surface ps-12 pe-14 text-base text-text placeholder:text-text-muted',
        'focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
        'aria-invalid:border-danger',
    ]) }}>
    <button type="button" hidden x-bind:hidden="false" x-on:click="show = !show"
        class="absolute inset-y-0 end-0 flex w-13 items-center justify-center rounded-e-control text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus"
        x-bind:aria-pressed="show ? 'true' : 'false'"
        x-bind:aria-label="show ? @js(__('codeart::ui.hide_password')) : @js(__('codeart::ui.show_password'))"
        aria-label="{{ __('codeart::ui.show_password') }}" aria-pressed="false">
        <span x-bind:hidden="show"><x-codeart::icon name="eye" /></span>
        <span hidden x-bind:hidden="!show"><x-codeart::icon name="eye-off" /></span>
    </button>
</div>
