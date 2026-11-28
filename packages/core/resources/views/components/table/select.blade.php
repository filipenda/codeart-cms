@props(['name', 'label', 'options' => [], 'value' => '', 'placeholder' => null])
{{-- Compact native select for the table toolbar (44px high), labelled for screen readers. --}}
<div class="relative">
    <select name="{{ $name }}" aria-label="{{ $label }}" {{ $attributes->class(['h-11 w-full appearance-none rounded-control border border-border bg-surface ps-3 pe-9 text-sm text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus']) }}>
        @if ($placeholder !== null) <option value="">{{ $placeholder }}</option> @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $optionValue === (string) $value)>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-text-muted"><x-codeart::icon name="chevron-down" class="size-4" /></span>
</div>
