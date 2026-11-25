@props(['name', 'label', 'type' => 'text', 'icon' => null, 'id' => null, 'hint' => null, 'bag' => null, 'value' => null])
@php
    // Label, control, hint and error, wired together: for/id and aria-describedby. $bag lets a page that holds
    // two forms show errors only in the one that was submitted (default: the shared $errors of the request).
    $id ??= 'field-'.$name;
    $error = ($bag ?? $errors)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $control = ['id' => $id, 'name' => $name, 'aria-invalid' => $error ? 'true' : null, 'aria-describedby' => $describedBy !== '' ? $describedBy : null];
    $value ??= in_array($type, ['password', 'otp'], true) ? null : old($name);
@endphp
<div class="space-y-2">
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-text">{{ $label }}</label>
    @if ($type === 'password')
        <x-codeart::password-input :icon="$icon ?? 'lock'" {{ $attributes->merge($control) }} />
    @elseif ($type === 'otp')
        <x-codeart::otp-input {{ $attributes->merge($control) }} />
    @else
        <x-codeart::input :type="$type" :icon="$icon" :value="$value" {{ $attributes->merge($control) }} />
    @endif
    @if ($hint) <p id="{{ $id }}-hint" class="text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($error)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm font-medium text-danger-text"><x-codeart::icon name="alert" class="mt-0.5 size-4" /><span>{{ $error }}</span></p>
    @endif
</div>
