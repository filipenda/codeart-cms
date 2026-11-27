<?php

namespace Codeart\Core\Confirmation;

use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The confirmation step for a destructive form posted by `<x-codeart::confirm>` (row actions, "delete" buttons).
 * The component posts here, never straight to the target: the response is the confirmation page (no JS) or JSON with a
 * token (the dialog, JS). The page's Confirm button posts to the target with the token; the target route enforces it with
 * the `codeart.confirmed` middleware (RequireConfirmation). The target and texts are signed by the component, so this
 * page cannot be pointed at another address.
 */
final class ConfirmController
{
    public function __construct(private readonly ConfirmationTokens $tokens) {}

    public function show(Request $request): View|JsonResponse
    {
        $target = (string) $request->input('target');
        $verb = strtoupper((string) $request->input('verb', 'POST'));
        $title = (string) $request->input('title');
        $text = (string) $request->input('text');
        $confirm = (string) $request->input('confirm');
        abort_unless($this->tokens->validSignature((string) $request->input('sig'), $target, $verb, $title, $text, $confirm), 403);

        $token = $this->tokens->issue($request->user(Admin::guardName()), RequireConfirmation::scope($verb, (string) parse_url($target, PHP_URL_PATH)));

        if ($request->expectsJson()) {
            return response()->json(['token' => $token]);
        }

        $back = (string) $request->input('back', '');
        session()->put('codeart.confirm_back', $back);

        return view('codeart::admin.confirm', [
            'title' => $title,
            'text' => $text !== '' ? $text : __('codeart::ui.c.confirm_text'),
            'count' => null,
            'filter' => null,
            'target' => $target,
            'method' => $verb,
            'fields' => [],
            'token' => $token,
            'back' => $back !== '' && str_starts_with($back, '/') && ! str_starts_with($back, '//') ? url($back) : route('admin.dashboard'),
            'expired' => false,
            'confirmLabel' => $confirm !== '' ? $confirm : null,
        ]);
    }

    /** Opened after the confirmed target redirected "back" to the confirmation page's own address: return to the page it came from. */
    public function back(Request $request): RedirectResponse
    {
        $back = (string) session()->pull('codeart.confirm_back', '');

        return redirect()->to($back !== '' && str_starts_with($back, '/') && ! str_starts_with($back, '//') ? url($back) : route('admin.dashboard'));
    }
}
