@props(['id', 'label', 'hint' => null, 'error' => null, 'required' => false, 'max' => null, 'count' => 0])
{{-- Shared frame of every form control: label (with required marker), the control (slot), hint, character counter and the
     error under the field. The control itself carries id, aria-invalid and aria-describedby (see describedBy() users).
     The counter shows the server value without JS and follows typing with Alpine. --}}
<div {{ $attributes->class('space-y-2') }} data-field
    @if ($max) x-data="{ count: {{ (int) $count }} }" x-on:input="count = [...$event.target.value].length" @endif>
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-text">{{ $label }}@if ($required)<span class="ms-0.5 text-danger-text" aria-hidden="true">*</span>@endif</label>
    {{ $slot }}
    @if ($hint) <p id="{{ $id }}-hint" class="text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($max)
        <p id="{{ $id }}-counter" data-counter="{{ $max }}" class="text-end text-xs text-text-muted" x-bind:class="count > {{ (int) $max }} ? 'text-danger-text' : ''">
            <span x-text="count">{{ (int) $count }}</span> / {{ (int) $max }}
        </p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm font-medium text-danger-text"><x-codeart::icon name="alert" class="mt-0.5 size-4" /><span>{{ $error }}</span></p>
    @endif
</div>
