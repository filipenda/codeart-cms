@props(['length' => 6, 'id', 'name' => 'code'])
{{--
    One-time code. The submitted value is ALWAYS the single text input (numeric keypad, autofill from SMS / authenticator).
    Alpine turns it into separate boxes with auto-advance, paste and backspace, and keeps the single value in sync.
--}}
<div x-data="codeartOtp({{ (int) $length }})" class="w-full">
    <input type="text" id="{{ $id }}" name="{{ $name }}" x-ref="main" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="{{ (int) $length }}"
        x-bind:tabindex="enabled ? -1 : null" x-bind:class="enabled ? 'sr-only' : ''"
        {{ $attributes->class([
            'block h-13 w-full rounded-control border border-border bg-surface px-4 text-center text-xl tracking-[0.5em] text-text',
            'focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-invalid:border-danger',
        ]) }}>
    <div hidden x-bind:hidden="!enabled" class="flex justify-between gap-2" role="group" aria-labelledby="{{ $id }}-label">
        <template x-for="(digit, i) in digits" :key="i">
            <input type="text" data-otp-box inputmode="numeric" maxlength="1" pattern="[0-9]*"
                x-bind:autocomplete="i === 0 ? 'one-time-code' : 'off'"
                x-bind:aria-label="@js(__('codeart::ui.digit_of')).replace(':n', i + 1).replace(':total', length)"
                x-bind:value="digits[i]"
                x-on:input="onInput(i, $event)" x-on:keydown="onKeydown(i, $event)" x-on:paste="onPaste(i, $event)" x-on:focus="$event.target.select()"
                class="block h-13 w-full min-w-0 rounded-control border border-border bg-surface text-center text-xl font-medium text-text focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
        </template>
    </div>
</div>
