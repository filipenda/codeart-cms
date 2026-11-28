@props(['name', 'label', 'type' => 'text', 'icon' => null, 'iconEnd' => null, 'id' => null, 'hint' => null, 'bag' => null, 'value' => null])
@php
    // Label, control, hint, counter and error, wired together: for/id and aria-describedby. $bag lets a page that holds
    // two forms show errors only in the one that was submitted (default: the shared $errors of the request).
    // `required` and `maxlength` (given as plain attributes) also drive the required marker and the counter.
    $id ??= 'field-'.$name;
    $error = ($bag ?? $errors)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $control = ['id' => $id, 'name' => $name, 'aria-invalid' => $error ? 'true' : null, 'aria-describedby' => $describedBy !== '' ? $describedBy : null];
    $value ??= in_array($type, ['password', 'otp'], true) ? null : old($name);
    $max = $attributes->get('maxlength');
@endphp
<x-codeart::field-shell :id="$id" :label="$label" :hint="$hint" :error="$error" :required="$attributes->has('required')" :max="$max" :count="mb_strlen((string) $value)">
    @if ($type === 'password')
        <x-codeart::password-input :icon="$icon ?? 'lock'" {{ $attributes->merge($control) }} />
    @elseif ($type === 'otp')
        <x-codeart::otp-input {{ $attributes->merge($control) }} />
    @else
        <x-codeart::input :type="$type" :icon="$icon" :icon-end="$iconEnd" :value="$value" {{ $attributes->merge($control) }} />
    @endif
</x-codeart::field-shell>
