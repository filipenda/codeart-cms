@props(['action', 'method' => 'POST', 'title' => null, 'text' => null, 'confirm' => null, 'cancel' => null, 'button' => 'Delete', 'variant' => 'danger', 'size' => 'md', 'icon' => null])
@php
    $id = 'confirm-'.substr(md5($action.$button.($title ?? '')), 0, 8);
    $verb = strtoupper($method);
    $tokens = app(\Codeart\Core\Confirmation\ConfirmationTokens::class);
    $titleText = (string) ($title ?? __('codeart::ui.c.confirm_title'));
    $bodyText = (string) ($text ?? __('codeart::ui.c.confirm_text'));
    $confirmText = (string) ($confirm ?? __('codeart::ui.c.confirm'));
    $sig = $tokens->sign($action, $verb, $titleText, $bodyText, $confirmText);
    $back = request()->getRequestUri();
@endphp
{{-- Destructive action with a SERVER-side confirmation (TB11). The trigger form posts to the confirmation step
     (`codeart.confirm`), never to the target: without JS the answer is a confirmation page. With JS the trigger fetches a
     token from the same step, opens the dialog, and the dialog's own form posts the real request with that token.
     The target route must carry the `codeart.confirmed` middleware, which refuses a request without the token. --}}
<div {{ $attributes }}>
    <form method="POST" action="{{ route('codeart.confirm') }}" data-confirm-step>
        @csrf
        <input type="hidden" name="target" value="{{ $action }}">
        <input type="hidden" name="verb" value="{{ $verb }}">
        <input type="hidden" name="title" value="{{ $titleText }}">
        <input type="hidden" name="text" value="{{ $bodyText }}">
        <input type="hidden" name="confirm" value="{{ $confirmText }}">
        <input type="hidden" name="back" value="{{ $back }}">
        <input type="hidden" name="sig" value="{{ $sig }}">
        <x-codeart::button :variant="$variant" :size="$size" :full="false" :icon-left="$icon" data-confirm-trigger aria-haspopup="dialog" aria-controls="{{ $id }}">{{ $slot->isNotEmpty() ? $slot : $button }}</x-codeart::button>
    </form>
    <form method="{{ $verb === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}" data-confirm-form>
        @csrf
        @if (! in_array($verb, ['GET', 'POST'], true)) @method($verb) @endif
        <input type="hidden" name="confirmation" value="" data-confirm-token>
        <x-codeart::modal :id="$id" size="sm" role="alertdialog" aria-describedby="{{ $id }}-text" :title="$titleText">
            <p id="{{ $id }}-text">{{ $bodyText }}</p>
            <x-slot:footer>
                <x-codeart::button type="button" variant="secondary" size="md" :full="false" data-modal-close>{{ $cancel ?? __('codeart::ui.c.cancel') }}</x-codeart::button>
                <x-codeart::button type="submit" variant="danger" size="md" :full="false">{{ $confirmText }}</x-codeart::button>
            </x-slot:footer>
        </x-codeart::modal>
    </form>
</div>
