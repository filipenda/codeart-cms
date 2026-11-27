<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Auth\Actions\RegisterUser;
use Codeart\Core\Auth\Actions\ResetUserPassword;
use Codeart\Core\Auth\Actions\UpdateUserPassword;
use Codeart\Core\Auth\Http\RegisterResponse;
use Codeart\Core\Auth\Http\SameResponseForPasswordReset;
use Codeart\Core\Auth\Http\Screens;
use Codeart\Core\Auth\Listeners\AuthEventSubscriber;
use Codeart\Core\Auth\Middleware\ConfigureAuthRequest;
use Codeart\Core\Auth\Middleware\EnsureAdminAccess;
use Codeart\Core\Auth\Middleware\SetAuthLocale;
use Codeart\Core\Auth\Middleware\ValidInvitationLink;
use Codeart\Core\Confirmation\RequireConfirmation;
use Codeart\Core\Mail\MailConfig;
use Codeart\Core\Settings\Settings;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Staff authentication: the `admin` guard on top of Laravel Fortify (headless), runtime session and mail
 * settings, the `admin.access` middleware, the user policy and the auth event listeners. See docs/auth.md.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // We register Fortify's routes ourselves (in boot, when settings and the database are ready) so the
        // admin path can come from a setting.
        Fortify::ignoreRoutes();
        $this->configure();
        $this->app->register(FortifyServiceProvider::class);

        $this->app->singleton(LoginRateLimiter::class, ConfiguredLoginRateLimiter::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, SameResponseForPasswordReset::class);
        $this->app->bind(FailedPasswordResetResponse::class, SameResponseForPasswordReset::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, SameResponseForPasswordReset::class);
        $this->app->bind(RegisterResponseContract::class, RegisterResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(RegisterUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::authenticateUsing($this->authenticate(...));

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('admin.access', EnsureAdminAccess::class);
        $router->aliasMiddleware('admin.invitation', ValidInvitationLink::class);
        $router->aliasMiddleware('codeart.confirmed', RequireConfirmation::class);
        $this->registerScreens();
        try {
            Gate::policy(Admin::userModel(), UserPolicy::class);
        } catch (\RuntimeException) {
            // the app has no Eloquent user provider; nothing to protect
        }
        $this->app->make(Dispatcher::class)->subscribe(AuthEventSubscriber::class);

        // Guests who hit a protected page go to the login screen (route `login`, present while the screens are on); otherwise a plain 401.
        Authenticate::redirectUsing(fn (Request $request): ?string => Route::has('login') && ! $request->expectsJson() ? route('login') : null);

        $this->app->make(MailConfig::class)->apply();
        $this->applySessionLifetime();

        $this->registerRoutes();
    }

    /** Fortify's GET screens render core's Blade views (an app overrides them by publishing the views). */
    private function registerScreens(): void
    {
        if (! config('fortify.views')) {
            return;
        }
        $screens = fn (string $method): \Closure => fn (Request $request) => $this->app->make(Screens::class)->{$method}($request);

        Fortify::loginView($screens('login'));
        Fortify::registerView($screens('register'));
        Fortify::twoFactorChallengeView($screens('twoFactorChallenge'));
        Fortify::requestPasswordResetLinkView($screens('forgotPassword'));
        Fortify::resetPasswordView($screens('resetPassword'));
        Fortify::verifyEmailView($screens('verifyEmail'));
        Fortify::confirmPasswordView($screens('confirmPassword'));
    }

    /** Fortify's routes and core's own, under the admin path. Skipped when the app caches its routes (the cache has them). */
    private function registerRoutes(): void
    {
        $path = Admin::path();
        $config = $this->app->make('config');
        $config->set('fortify.prefix', $path);
        $config->set('fortify.home', '/'.$path);

        if ($this->app->routesAreCached()) {
            return;
        }

        $fortify = dirname((string) (new \ReflectionClass(Fortify::class))->getFileName(), 2).'/routes/routes.php';
        Route::group(['namespace' => 'Laravel\\Fortify\\Http\\Controllers', 'prefix' => $path], fn () => require $fortify);
        Route::middleware(['web', SetAuthLocale::class])->prefix($path)->group(__DIR__.'/../../routes/auth.php');
        Route::middleware(['web', SetAuthLocale::class])->prefix($path)->group(__DIR__.'/../../routes/admin.php');
    }

    /** Guard, Fortify config and the settings-driven parts. Values come from settings with config fallbacks. */
    private function configure(): void
    {
        $config = $this->app->make('config');
        $guard = (string) $config->get('codeart.auth.guard', 'admin');

        // The staff guard. It is the default guard, which is also what makes the database session handler record user ids.
        if ($config->get("auth.guards.{$guard}") === null) {
            $config->set("auth.guards.{$guard}", ['driver' => 'session', 'provider' => 'users']);
        }
        if ($config->get('auth.defaults.guard') === 'web') {
            $config->set('auth.defaults.guard', $guard);
        }
        // Staff sessions live in the database so they can be listed and revoked.
        if (in_array($config->get('session.driver'), ['file', 'cookie', null], true)) {
            $config->set('session.driver', 'database');
        }
        // Role and permission changes are logged from spatie's events.
        $config->set('permission.events_enabled', true);

        $config->set('fortify.guard', $guard);
        $config->set('fortify.passwords', 'users');
        $config->set('fortify.username', 'email');
        $config->set('fortify.email', 'email');
        $config->set('fortify.views', (bool) $config->get('codeart.auth.views', false));
        $config->set('fortify.limiters.login', null); // ConfiguredLoginRateLimiter does it, and fires Lockout
        $config->set('fortify.middleware', ['web', SetAuthLocale::class, ConfigureAuthRequest::class]);
        // Registration is always routed; ConfigureAuthRequest answers 404 until auth.registration.enabled is on.
        $config->set('fortify.features', [
            Features::registration(),
            Features::resetPasswords(),
            Features::emailVerification(),
            Features::updatePasswords(),
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
        ]);
    }

    private function applySessionLifetime(): void
    {
        $minutes = (int) $this->app->make(Settings::class)->get('security.session.lifetime_minutes');
        $this->app->make('config')->set('session.lifetime', $minutes);
    }

    /** E-mail + password. Same error and about the same work for a wrong password, unknown e-mail and inactive account. */
    private function authenticate(Request $request): mixed
    {
        $email = $request->input(Fortify::username());
        $password = (string) $request->input('password');
        $user = is_string($email) ? Admin::findByEmail($email) : null;

        // Unknown e-mail: still pay for one hash check, so response time does not tell.
        $hash = $user !== null ? (string) $user->getAuthPassword() : $this->dummyHash();
        $matches = Hash::check($password, $hash);

        if ($user === null || ! $matches || ! $user->is_active) {
            return null;
        }
        if (Hash::needsRehash($hash)) {
            $user->forceFill(['password' => Hash::make($password)])->saveQuietly();
        }

        return $user;
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('codeart-dummy-password');
    }
}
