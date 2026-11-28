@props(['name', 'label', 'id' => null, 'hint' => null, 'bag' => null, 'value' => null, 'options' => [], 'placeholder' => null, 'icon' => null])
@php
    // Styled native select. Options as `options` (value => label) or as <option> elements in the slot.
    // The selected value is `value`, else old(name).
    $id ??= 'field-'.$name;
    $error = ($bag ?? $errors)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $selected = (string) ($value ?? old($name));
@endphp
<x-codeart::field-shell :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$attributes->has('required')">
    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 start-4 flex items-center text-text-muted" data-select-icon><x-codeart::icon :name="$icon" /></span>
        @endif
        <select id="{{ $id }}" name="{{ $name }}"
            @if ($error) aria-invalid="true" @endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class([
                'block h-13 w-full appearance-none rounded-control border border-border bg-surface pe-12 text-base text-text',
                $icon ? 'ps-12' : 'ps-4',
                'focus:border-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                'aria-invalid:border-danger disabled:opacity-60',
            ]) }}>
            @if ($placeholder !== null) <option value="">{{ $placeholder }}</option> @endif
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $optionValue === $selected)>{{ $optionLabel }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <span class="pointer-events-none absolute inset-y-0 end-4 flex items-center text-text-muted"><x-codeart::icon name="chevron-down" /></span>
    </div>
</x-codeart::field-shell>
