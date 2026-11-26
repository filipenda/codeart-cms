<?php

namespace Codeart\Core\Admin\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Developer page listing every Blade component in all states (docs/components.md). Local environment only
 * (the route answers 404 anywhere else, see routes/admin.php) and only for `manage-modules` (super-admin).
 */
class UiShowcaseController
{
    public function show(): View
    {
        Gate::authorize('manage-modules');

        return view('codeart::admin.ui-showcase');
    }

    /** The demo form: fails on purpose so the error display can be reviewed, or flashes a toast. */
    public function submit(Request $request): RedirectResponse
    {
        Gate::authorize('manage-modules');

        $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:80'],
            'plan' => ['required', 'in:free,pro,team'],
            'terms' => ['accepted'],
        ]);

        return redirect()->route('codeart.ui-showcase')->with('toast', ['type' => 'success', 'message' => 'Form is valid: this is a flash toast.']);
    }
}
