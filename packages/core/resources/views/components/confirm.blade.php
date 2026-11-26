@props(['action', 'method' => 'POST', 'title' => null, 'text' => null, 'confirm' => null, 'cancel' => null, 'button' => 'Delete', 'variant' => 'danger', 'size' => 'md', 'icon' => null])
@php
    $id = 'confirm-'.substr(md5($action.$button.($title ?? '')), 0, 8);
    $verb = strtoupper($method);
@endphp
{{-- Destructive form with a confirmation. The default slot is the label of the trigger button. With JS the trigger opens
     a dialog (title, text, danger confirm button); without JS it submits the form as a plain form would. --}}
<form method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" {{ $attributes }}>
    @csrf
    @if (! in_array($verb, ['GET', 'POST'], true)) @method($verb) @endif
    <x-codeart::button :variant="$variant" :size="$size" :full="false" :icon-left="$icon" data-confirm-trigger aria-haspopup="dialog" aria-controls="{{ $id }}">{{ $slot->isNotEmpty() ? $slot : $button }}</x-codeart::button>
    <x-codeart::modal :id="$id" size="sm" role="alertdialog" aria-describedby="{{ $id }}-text" :title="$title ?? __('codeart::ui.c.confirm_title')">
        <p id="{{ $id }}-text">{{ $text ?? __('codeart::ui.c.confirm_text') }}</p>
        <x-slot:footer>
            <x-codeart::button type="button" variant="secondary" size="md" :full="false" data-modal-close>{{ $cancel ?? __('codeart::ui.c.cancel') }}</x-codeart::button>
            <x-codeart::button type="submit" variant="danger" size="md" :full="false">{{ $confirm ?? __('codeart::ui.c.confirm') }}</x-codeart::button>
        </x-slot:footer>
    </x-codeart::modal>
</form>
