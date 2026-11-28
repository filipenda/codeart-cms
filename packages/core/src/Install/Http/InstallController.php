<?php

namespace Codeart\Core\Install\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Install\InstallerService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The wizard. Each screen needs the earlier ones to be done; the state lives in the (encrypted) session until the run. */
class InstallController
{
    public function __construct(private readonly InstallerService $installer) {}

    public function key(Request $request): View|RedirectResponse
    {
        $id = (string) $request->ip();

        return view('codeart::install.key', [
            'missing' => ! $this->installer->key->exists(),
            'locked' => $this->installer->key->tooManyAttempts($id),
            'wait' => $this->installer->key->tooManyAttempts($id) ? (int) ceil($this->installer->key->retryAfter($id) / 60) : 0,
            'path' => $this->installer->paths->base,
        ]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $id = (string) $request->ip();
        $request->validate(['key' => ['required', 'string', 'max:200']], [], ['key' => __('codeart::install.attributes.key')]);

        if ($this->installer->key->tooManyAttempts($id)) {
            return back()->withErrors(['key' => __('codeart::install.key.locked', ['minutes' => (int) ceil($this->installer->key->retryAfter($id) / 60)])]);
        }
        if (! $this->installer->key->attempt((string) $request->input('key'), $id)) {
            return back()->withErrors(['key' => __('codeart::install.key.wrong')]);
        }
        $request->session()->regenerate();
        $request->session()->put('installer.key_ok', true);

        return redirect()->route('install.requirements');
    }

    public function requirements(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'key_ok')) {
            return $redirect;
        }
        $list = $this->installer->requirements();

        return view('codeart::install.requirements', ['list' => $list, 'passes' => $this->installer->requirementsPass()]);
    }

    public function database(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'key_ok')) {
            return $redirect;
        }
        if (! $this->installer->requirementsPass()) {
            return redirect()->route('install.requirements');
        }

        return view('codeart::install.database', ['db' => $request->session()->get('installer.db_form', ['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '']), 'existing' => $request->session()->get('installer.tables', 0)]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        if ($redirect = $this->need($request, 'key_ok')) {
            return $redirect;
        }
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
        ], [], __('codeart::install.attributes'));
        $data['password'] = (string) ($data['password'] ?? '');
        $request->session()->put('installer.db_form', ['host' => $data['host'], 'port' => $data['port'], 'database' => $data['database'], 'username' => $data['username']]);

        $result = $this->installer->testDatabase($data);
        if (! $result->ok) {
            return back()->withInput($request->except('password'))->withErrors(['connection' => __('codeart::install.db_error.'.$result->error)]);
        }
        if ($result->tables > 0 && ! $request->boolean('confirm_existing')) {
            $request->session()->put('installer.tables', $result->tables);

            return back()->withInput($request->except('password'))->withErrors(['confirm_existing' => __('codeart::install.db.existing_confirm')]);
        }
        $request->session()->forget('installer.tables');

        $this->installer->writeEnvironment($data, $request->getSchemeAndHttpHost(), (string) config('app.name'), app()->getLocale() === 'en' ? 'en' : 'sr');
        $request->session()->put('installer.db_ok', true);

        return redirect()->route('install.site');
    }

    public function site(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'db_ok')) {
            return $redirect;
        }

        return view('codeart::install.site', [
            'site' => $request->session()->get('installer.site') ?? $this->installer->siteDefaults($request->getSchemeAndHttpHost()),
            'locales' => (array) config('codeart.locales.available', []),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        if ($redirect = $this->need($request, 'db_ok')) {
            return $redirect;
        }
        $site = $this->installer->validateSite($request->only(['site_name', 'url', 'timezone', 'locales', 'default_locale', 'admin_path']));
        $request->session()->put('installer.site', $site);

        return redirect()->route('install.admin');
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'site')) {
            return $redirect;
        }
        $generated = null;
        if ($request->boolean('generate')) {
            $generated = $this->installer->generatePassword();
        }

        return view('codeart::install.admin', [
            'admin' => $request->session()->get('installer.admin_form', []),
            'generated' => $generated,
            'rules' => app(PasswordRules::class)->requirements(),
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        if ($redirect = $this->need($request, 'site')) {
            return $redirect;
        }
        $request->session()->put('installer.admin_form', $request->only(['first_name', 'last_name', 'email']));
        $input = $request->only(['first_name', 'last_name', 'email', 'password']);
        $input['password'] = (string) ($input['password'] ?? '');
        if (! $request->boolean('generated') && $input['password'] !== (string) $request->input('password_confirmation')) {
            return back()->withErrors(['password' => __('codeart::install.errors.password_confirmation')])->withInput($request->only(['first_name', 'last_name', 'email']));
        }
        $request->session()->put('installer.admin', $this->installer->validateAdmin($input));

        return redirect()->route('install.modules');
    }

    public function modules(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'admin')) {
            return $redirect;
        }

        return view('codeart::install.modules', ['modules' => $this->installer->availableModules(), 'chosen' => $request->session()->get('installer.modules', [])]);
    }

    public function storeModules(Request $request): RedirectResponse
    {
        if ($redirect = $this->need($request, 'admin')) {
            return $redirect;
        }
        $available = array_keys($this->installer->availableModules());
        $chosen = array_values(array_intersect($available, (array) $request->input('modules', [])));
        $request->session()->put('installer.modules', $chosen);
        $request->session()->put('installer.modules_done', true);

        return redirect()->route('install.run');
    }

    public function run(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'modules_done')) {
            return $redirect;
        }

        return view('codeart::install.run', ['results' => null, 'retry' => false]);
    }

    public function execute(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->need($request, 'modules_done')) {
            return $redirect;
        }
        $results = $this->installer->run([
            'site' => $request->session()->get('installer.site'),
            'admin' => $request->session()->get('installer.admin'),
            'modules' => $request->session()->get('installer.modules', []),
        ]);
        $failed = collect($results)->contains(fn ($r) => ! $r->ok);
        if ($failed) {
            return view('codeart::install.run', ['results' => $results, 'retry' => true]);
        }

        $url = (string) $request->session()->get('installer.site.url');
        $request->session()->forget('installer'); // the password goes with it

        return view('codeart::install.done', [
            'results' => $results,
            'adminUrl' => $url.'/'.Admin::path(),
            'cron' => $this->installer->cronLine(),
        ]);
    }

    /** Earlier steps first: send the person to the first screen that is still open. */
    private function need(Request $request, string $flag): ?RedirectResponse
    {
        $order = ['key_ok' => 'install.key', 'db_ok' => 'install.database', 'site' => 'install.site', 'admin' => 'install.admin', 'modules_done' => 'install.modules'];
        foreach ($order as $key => $route) {
            if (! $request->session()->has('installer.'.$key)) {
                return $key === $flag && $flag === 'key_ok' ? redirect()->route('install.key') : redirect()->route($route);
            }
            if ($key === $flag) {
                return null;
            }
        }

        return null;
    }
}
