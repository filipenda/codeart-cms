# Authentication (staff)

Backend and screens for signing in to the admin. The routes, names and JSON shapes below are the backend; the screens
(Blade + Alpine, no Livewire) are described under "Screens" and are on by default (`codeart.auth.views`). Every form posts
straight to Fortify and works without JavaScript.

- Guard `admin` (provider `users`, the app's `User` model). Staff only; future shop customers get their own guard and table.
- Laravel Fortify 1.44 does the heavy lifting (login, reset, verification, 2FA, registration); core adds the policies, settings, invitations, logging and mail around it.
- Everything is under the admin path (setting `auth.admin_path`, default `admin`). All examples below use `/admin`.
- Routes are not cached (`route:cache`): the admin path is read from the database when the app boots. Changing the setting takes effect on the next request.

## Setup in an app

1. `composer require codeart/core`, then `php artisan migrate` (adds the user columns, `sessions`, `user_invitations`, `activity_log`).
2. The app's `User` model uses the trait and implements two contracts (the starter does this):

   ```php
   use Codeart\Core\Auth\Concerns\AdminUser;
   class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
   {
       use AdminUser;               // brings HasRoles, Notifiable, Fortify's TwoFactorAuthenticatable
   }
   ```

3. `php artisan codeart:roles` creates roles `super-admin`, `admin`, `editor`, `member` and the permissions (below).
4. **First super-admin:** `php artisan user:create "Name" you@example.com super-admin`. The password is asked for with a hidden prompt (never on the command line). The user is created verified, active and approved. With 2FA required (default) they set it up at first sign-in.
5. Production needs HTTPS and `SESSION_SECURE_COOKIE=true` (plus `SESSION_SAME_SITE=lax`, the default, and `SESSION_HTTP_ONLY=true`, the default) in `.env`, `APP_URL` with `https://`. Sessions are stored in the database (`SESSION_DRIVER=database`; core switches `file`/`cookie` to `database`). Mail must work (see Mail) or invitations, resets and verification cannot be delivered.

Core makes `admin` the default guard when the app still has `web` (the database session handler needs that to record user ids). `config('codeart.auth.guard')` names it; `config('codeart.auth.views')` is `true` (set it to `false` for a headless install: Fortify then registers no GET screens).

## Roles and permissions

All under guard name `admin`. `CreateDefaultRoles` grants a permission to roles only when the permission is first created, so a later revoke sticks.

| Permission | admin | editor | member | super-admin |
|---|---|---|---|---|
| `admin.access` | yes | yes | no | Gate bypass |
| `users.view`, `users.create`, `users.update`, `users.approve` | yes | no | no | Gate bypass |
| `settings.appearance` | yes | no | no | Gate bypass |

`member` is the role of registered visitors: it has no permission, so it never reaches the admin (403).

Rank (`config('codeart.auth.role_rank')`): super-admin 100, admin 50, editor 10, member 0, unknown roles 98. Nobody may assign, remove or touch a role above their own; **only a super-admin touches a super-admin** (`RoleRank`, enforced in every action and in `UserPolicy`). A null actor (command line) counts as super-admin.

`UserPolicy` abilities (registered for the app's User model): `viewAny`, `view` (`users.view`), `invite` (`users.create`), `create`, `update`, `deactivate`, `delete` (`users.update` + rank; never yourself for deactivate/delete), `approve` (`users.approve` + rank).

## Middleware

- `admin.access` (alias): signed in on the guard, `is_active`, approved (`auth.registration.require_approval`), e-mail verified (`auth.registration.require_email_verification`), permission `admin.access` (super-admin passes the Gate), and 2FA set up when required for the role. **Every admin route, including module routes, must use it** (`['web', 'auth:admin', 'admin.access']`).
- Denial: guests 401; others 403 with JSON `{message, reason}` (`inactive`, `unapproved`, `unverified`, `forbidden`, `two_factor_required`) for JSON requests, `abort(403)` otherwise. For browsers, `unverified` redirects to route `verification.notice` and `two_factor_required` to `admin.two-factor.setup` **when those screen routes exist**.
- While 2FA setup is pending only these are reachable: every `two-factor.*` route, `logout`, `admin.session` and the password-confirm routes (`codeart.auth.two_factor_exempt_routes`).

## Route names

Fortify's routes keep Fortify's names, all under the admin prefix. Core's own start with `admin.`.

| Method and path | Name | Notes |
|---|---|---|
| POST `login` | `login.store` | `email`, `password`, `remember`. 200 `{"two_factor": true}` when a code is needed |
| POST `logout` | `logout` | 204, session invalidated |
| POST `forgot-password` | `password.email` | always 200 with the same message |
| POST `reset-password` | `password.update` | `token`, `email`, `password`, `password_confirmation` |
| POST `register` | `register.store` | 404 unless registration is on, see Registration |
| GET `email/verify/{id}/{hash}` | `verification.verify` | signed link from the mail |
| POST `email/verification-notification` | `verification.send` | resend |
| PUT `user/password` | `user-password.update` | `current_password`, `password`, `password_confirmation` |
| GET `user/confirmed-password-status`, POST `user/confirm-password` | `password.confirmation`, `password.confirm.store` | password confirmation for sensitive actions (423 until confirmed) |
| POST `two-factor-challenge` | `two-factor.login.store` | `code` or `recovery_code` |
| POST `user/two-factor-authentication` | `two-factor.enable` | needs password confirmation; 403 when mode is `off` |
| POST `user/confirmed-two-factor-authentication` | `two-factor.confirm` | `code` from the app |
| DELETE `user/two-factor-authentication` | `two-factor.disable` | |
| GET `user/two-factor-qr-code`, `user/two-factor-secret-key` | `two-factor.qr-code`, `two-factor.secret-key` | `{svg, url}`, `{secretKey}` |
| GET / POST `user/two-factor-recovery-codes` | `two-factor.recovery-codes`, `two-factor.regenerate-recovery-codes` | list / regenerate |
| GET `session` | `admin.session` | signed-in state for the screens (user, roles, `can_access_admin`, `denial`, `two_factor.{available,enabled,must_set_up}`) |
| GET `/` (the admin path itself) | `admin.dashboard` | the dashboard, behind `auth:admin` + `admin.access`; the page after sign-in |
| GET `access` | `admin.access.check` | behind `admin.access`: 204 or 403 |
| GET `sessions`, DELETE `sessions/{id}`, DELETE `sessions` | `admin.sessions.index`, `.destroy`, `.destroy-others` | own sessions; the last needs `password` |
| GET / POST `invitation/{token}` | `admin.invitation.show`, `admin.invitation.accept` | signed; see Invitations |

Throttling: login by e-mail + IP from the settings (lockout answers 429 and fires `Lockout`); 2FA challenge 5/minute (Fortify); registration 5/hour per IP; invitation links 20/minute.

## Settings (all in `docs/modules.md` format, read with `Settings::get`)

| Key | Default | Who |
|---|---|---|
| `auth.admin_path` | `admin` | super-admin |
| `auth.invitation.expiry_days` | 7 | super-admin |
| `auth.registration.enabled` | **false** | super-admin |
| `auth.registration.require_email_verification` | true | super-admin |
| `auth.registration.require_approval` | true | super-admin |
| `auth.registration.default_role` | `member` (never `super-admin` or `admin`) | super-admin |
| `auth.registration.allowed_email_domains` | `[]` (any) | super-admin |
| `auth.registration.min_seconds` | 3 | super-admin |
| `security.2fa.mode` | **required** (`off`, `optional`, `required`) | super-admin |
| `security.2fa.required_roles` | `[super-admin, admin]` | super-admin |
| `security.password.min_length` / `require_mixed_case` / `require_number` / `require_symbol` | 10 / on / on / off | super-admin |
| `security.password.check_breached` | on (fails open when the service is unreachable) | super-admin |
| `security.login.max_attempts` / `decay_minutes` / `remember_days` | 5 / 15 / 30 | super-admin |
| `security.session.lifetime_minutes` | 120 (applied at runtime) | super-admin |
| `site.timezone` | `Europe/Belgrade` (display zone when a user chose none) | super-admin |
| `security.activity_log.retention_days` | 90 | super-admin |
| `mail.from_name`, `from_address`, `driver` (`smtp`/`log`), `host`, `port`, `username`, `password` (secret), `encryption` (`tls`/`ssl`/`none`) | from `.env` | super-admin |

Mail settings override `.env` only for values that were saved. 2FA mode: `off` hides/blocks setup (users who already have 2FA are still challenged); `optional` allows it; `required` forces it for `required_roles`.

## Flows

**Sign-in.** POST `login.store`. Unknown e-mail, wrong password and inactive account give the identical 422 (and about the same work). Lockout per e-mail + IP. The session id is regenerated; logout invalidates the session. With 2FA enabled the response is `{"two_factor": true}` and the user is not signed in until `two-factor.login.store` succeeds (session carries the pending login, so the screens must keep cookies).

**Password reset.** `password.email` answers 200 with the same text for known, unknown and throttled addresses; the mail is sent after the response. `password.update` answers the same 422 for a wrong e-mail or a wrong token. A reset ends all the user's sessions.

**E-mail verification.** The mail links to the signed `verification.verify` route; the user must be signed in. Required only while `auth.registration.require_email_verification` is on.

**Invitations (the main way to add staff).** `InviteUser` (action) or `php artisan user:invite name email role`. Needs `users.create`, and the role must not be above the inviter's (admin: up to admin, never super-admin). One open invitation per address; the token is random, stored only as a SHA-256 hash, valid `auth.invitation.expiry_days`, single use. The mail contains a signed URL to `admin.invitation.show`. The invitation screen renders the "set your password" form at that URL and POSTs the same URL with `password`, `password_confirmation` and optional `locale` (`admin.invitation.accept`): accepting creates the user (verified, active, approved, roles from the invitation, `invited_at`). Unknown, used, expired or no-longer-allowed invitations all answer 410; a tampered link 403.

**Registration** (off by default). When enabled, `register.store` creates a user with the default role. With approval required the user is `is_active=false`, `approved_at=null` and cannot sign in until approved; they are signed in right after registering, so they can verify the e-mail in that session. Mails: verification, "registration received", and a "new registration pending" to every active user with `users.approve` plus super-admins. Approve with `ApproveUser` (needs `users.approve`) or `php artisan user:approve email`; the user gets "registration approved". Known trade-off: the e-mail uniqueness check reveals that an address is registered (login and reset do not).

Bot protection (`Codeart\Core\Auth\BotProtection`, applied to `register.store`): the form must send the hidden fields `form_token` (value: `app(BotProtection::class)->issueToken()`, an encrypted timestamp) and the honeypot `company_website` (empty; hide it with CSS, `tabindex="-1"`, `autocomplete="off"`). Failure gives the same generic 422 as any failed registration. Field names: `tokenField()`, `honeypotField()`.

**Sessions.** `UserSessions` (list / revoke one / revoke others / revoke all). Revoking others also rotates the remember token.

**2FA.** Enable (password confirmation first) → show QR/secret → confirm with a code → show recovery codes. Recovery codes are replaced when used. Lost phone: `php artisan user:reset-2fa email` (logged).

## Screens

Views live in `codeart::auth.*` (`packages/core/resources/views/auth`) on top of the components in `docs/components.md`.
Fortify's view hooks are registered in `AuthServiceProvider::registerScreens()`; `Codeart\Core\Auth\Http\Screens` builds the data.

| Screen | Route name / path | Notes |
|---|---|---|
| Login + registration tabs | `login` (GET `/admin/login`), `register` (GET `/admin/register`) | Tabs are links (no JS); the registration tab, its link, the form, honeypot and signed timestamp exist ONLY when `auth.registration.enabled` is on (`/register` is 404 otherwise). After a failed registration the page stays on its tab and keeps the input |
| 2FA challenge | `two-factor.login` | one numeric code input; `?recovery=1` shows the recovery-code field (also toggled by Alpine) |
| 2FA setup | `admin.two-factor.setup` (`/admin/two-factor-setup`) | states: off, start (asks for the password first), confirm (inline SVG QR, manual key, code form), done. Recovery codes appear ONLY right after the first correct code or after regenerating (flash status); `admin.access` redirects here when 2FA is required |
| Forgot / reset password | `password.request`, `password.reset` | status messages from `codeart::ui.status.*` or the neutral sentence |
| Accept invitation | `admin.invitation.show` | valid = password form posting to the same signed URL (`locale` hidden = current language); expired, used, invalid = a clear state (HTTP 410; a changed or timed-out signature 403 with state `invalid` / `expired`). JSON clients still get JSON |
| Verify e-mail | `verification.notice` | resend button; `admin.access` redirects unverified users here |
| Confirm password | `password.confirm` | |
| Registration received | `admin.registration.received` | where a browser lands after registering; shows "verify your e-mail" and/or "waiting for approval" |
| No access | rendered by `admin.access` | themed 403 for browsers; JSON clients keep the JSON `{message, reason}` |

Not built yet (later tasks): own-sessions list, change password, pending-registrations screen, the admin home (`/admin`, Fortify's post-login target), social sign-in buttons (the layout has the empty `social` slot).

**Language of the screens.** `SetAuthLocale` (on every Fortify route and core's auth routes) picks: the visitor's choice (session `codeart.locale`, then the cookie `codeart_locale`), then the signed-in user's `locale`, then `locales.default`; only ENABLED locales count. The switcher posts to `codeart.locale` (`POST /admin/locale`, throttled), which validates against the enabled locales, stores the choice in the session and a cookie and updates the signed-in user's `locale`.

**Override a screen.** `php artisan vendor:publish --tag=codeart-views` copies the views and components to `resources/views/vendor/codeart/`; a copy there wins over core's. `--tag=codeart-assets` publishes `codeart.css` and `codeart.js` to `public/vendor/codeart`; the starter's `composer install`/`update` (and so `composer setup`) run it automatically. FTP-only deployments: run `php artisan vendor:publish --tag=codeart-assets --force` locally and upload `public/vendor/codeart` (needed after every core update). `php artisan codeart:brand:load [path]` loads `logo-light-bg.png`, `logo-dark-bg.png` and `favicon.png` (default `docs/design/brand`) into the theme settings through the normal image upload service (needs `php artisan storage:link` to be served).

Use `GET admin.session` to learn who is signed in and why access is denied. Unauthenticated browser requests are redirected to route `login`.

## Mail

Sent in the user's `locale` (`sr`, `sr-Cyrl`, `en`; falls back to the app locale): invitation, reset password, verify e-mail, registration received, registration approved, new registration pending, plus the `mail:test` message. One template (`codeart::mail.message`); every color comes from `ThemeSettings::emailPalette()` (derived from the theme settings and the base colors in config), so there are no color literals in views. Reset, verification and registration mails are sent after the response (no queue worker is needed); invitations are sent at once so an error is visible. `php artisan mail:test you@example.com` checks the settings.

## Activity log

`ActivityLog::record($event, $description, $subject, $causer, $properties, $log)` writes to `activity_log` (spatie/laravel-activitylog; adds `ip_address` and `user_agent`). Property keys that look like secrets (`pass`, `token`, `secret`, `recovery`, `code`, `key`, `hash`, ...) are dropped automatically. Events: `auth.login`, `auth.login_failed`, `auth.logout`, `auth.lockout`, `password.reset_requested`, `password.reset_completed`, `password.changed`, `two_factor.setup_started|enabled|disabled|failed|recovery_code_used|recovery_codes_regenerated|reset`, `profile.updated|avatar_changed|avatar_removed`, `session.revoked`, `email.verified`, `registration.created`, `invitation.sent|accepted`, `user.created|approved|deactivated`, `role.attached|detached`, `permission.attached|detached`, `session.others_revoked`. `activity-log:prune` deletes entries older than `security.activity_log.retention_days`; core schedules it daily (the client's cron runs `schedule:run`).

## Language files

`packages/core/resources/lang/{sr,en}` are written by hand; `sr-Cyrl` is generated: `php artisan lang:transliterate` (placeholders such as `:name` stay untouched; `--check` only reports). Commit the generated files. A test fails when a key is missing in any language or `sr-Cyrl` is stale. Namespace `codeart::` (`auth`, `mail`, `ui` for the screens, `framework`). `framework.php` holds fallbacks for Laravel's own `auth.*`, `passwords.*` and `validation.*` keys, used only when the app has no translation for the key.

## Commands

`codeart:brand:load {path?}`, `user:create {name} {email} {role}`, `user:invite`, `user:approve`, `user:deactivate`, `user:reset-2fa`, `activity-log:prune`, `mail:test {address}`, `lang:transliterate`, `codeart:roles`.

## Users management (core, always on)

Screens under the admin path: `admin.users.index` (list, menu "Korisnici"), `admin.users.edit` / `update` / `role`, row and bulk actions (`block`, `unblock`, `destroy` = soft delete, `restore`, `force-destroy`, `reset-2fa`, `approve`, `reject`). Dashboard widget "Čeka odobrenje" (only with `users.approve`) and quick action "Pozovi korisnika".

- **Permissions:** `users.view`, `users.create` (invite), `users.update`, `users.block`, `users.approve`, `users.delete`, `users.force-delete`, `users.reset-2fa` (the old `users.invite` / `users.manage` became `users.create` / `users.update`). `admin` gets all but `users.force-delete`; `editor` and `member` none; super-admin passes the Gate, except the self / last-super-admin rules.
- **Policy rules** (`UserPolicy`, tested): an admin manages only users STRICTLY below their rank (editor, member); admins and super-admins only by a super-admin. Roles can be assigned only strictly below the actor's own (`RoleRank::canAssign`); nobody changes their own role, blocks or deletes themselves; the last active super-admin cannot be blocked, deleted or demoted. Super-admin abilities `changeRole`, `block`, `delete`, `restore`, `forceDelete`, `resetTwoFactor` still go through the policy (`UserPolicy::GUARDED`, Gate::before skips them).
- **Status** (`UserStatus`): deleted > pending (no `approved_at`) > blocked (`is_active` = 0) > unverified (e-mail) > active. Blocked reuses `is_active`.
- **Soft delete:** `users.deleted_at` (the app's User uses `AdminUser`, which brings `SoftDeletes`). Deleted users vanish from the list (filter "Obrisani" shows them), cannot sign in, can be restored; permanent delete is super-admin only. Log entries keep `name` and `email` in their properties.
- **Dangerous actions** (role change, block, delete, force delete, reset 2FA) carry middleware `password.confirm` then `codeart.confirmed`: a fresh password confirmation AND the server-issued confirmation token.
- **Edit:** only `name` and `email` (generic update) and `role` (own route) are read from the request. A new e-mail clears `email_verified_at` and sends the verification mail when verification is required. New people are added only through invitations.
- **Registrations:** `approve` / `reject` (reason optional, max 500) both mail the person; reject soft-deletes the pending account.
- **Bulk:** approve, block, unblock, delete; each row passes the policy, forbidden rows are skipped and counted ("preskočeno"). Block and delete need the confirmation.
- **Log events** (`users` log): `user.updated`, `user.email_changed`, `user.role_changed`, `user.deactivated`, `user.unblocked`, `user.deleted`, `user.restored`, `user.force_deleted`, `user.approved`, `user.rejected`, `two_factor.reset`.


### Invitations screen
`admin.users.invite` (form: name, e-mail, role from the assignable roles, optional message up to 500 characters; `users.create`) posts to `admin.users.invite.store` (throttle 10/min) and uses the existing `InviteUser` flow (hashed token, signed URL, expiry; the token appears only in the mail). `admin.users.invitations` lists open invitations (DataTable `user-invitations`, translated state/role search): `resend` (new token, the old link stops) and `revoke` (confirmation + password). A role above the actor's rank can neither be invited nor resent nor revoked. Events: `invitation.sent`, `invitation.revoked`.

## "Moj profil" (`admin.profile`)
Every user who may enter the admin (menu entry and a link in the user menu). Always the signed-in user's own row, no id in any URL. Layout and tabs: `docs/admin-ui.md`.
- **Details:** `PUT admin.profile.update` reads only `first_name`, `last_name` (`name` is composed from them), `phone`, `timezone` (an IANA identifier or empty = site zone), `locale`, `theme_preference`; anything else (e-mail, role, status) is ignored. E-mail changes only by an admin. Logged as `profile.updated`.
- **Password:** `PUT admin.profile.password` asks for the current password and applies the configured password rules (`PasswordRules`; the checklist on the page is `PasswordRules::requirements()`), signs out other devices and logs `password.changed`.
- **2FA tab:** enabling and confirming use Fortify's routes; `POST admin.profile.two-factor.recovery` (new recovery codes) and `DELETE admin.profile.two-factor.disable` need a fresh password and the server confirmation token. Turning 2FA off is refused with 403 while the settings require it for the user's role (`TwoFactorPolicy::requiredFor`), on the profile route and on Fortify's own route. Recovery codes are shown only right after confirming/regenerating or after a fresh password confirmation (`?codes=1`).
- **Sessions tab (`admin.profile.sessions`): requires `SESSION_DRIVER=database`** (the starter and the dev environment use it; the `sessions` table comes from core's migration). With any other driver the tab is not rendered and the route answers 404. Rows come from the `sessions` table for the signed-in user only; the page carries opaque handles (`SessionHandle`), never real ids; no location is shown. `DELETE admin.profile.sessions/{handle}` and `DELETE admin.profile.sessions` (all others) need a fresh password and the confirmation token; the current session can never be ended there. Log events `session.revoked`, `session.others_revoked`.
- **Preferences:** `users.locale` (also saved by the language switcher) and `users.theme_preference` (NULL = not chosen); the user-menu theme switcher posts to `admin.profile.theme` and the saved choice starts every page (`data-theme`, `data-theme-user`), falling back to the browser's memory and then the site default.

## Time zone
Dates are stored in UTC and only DISPLAYED in a zone: the user's `users.timezone` (profile), else the setting `site.timezone` (default `Europe/Belgrade`, super-admin). `Codeart\Core\Support\DisplayTime` (singleton; `DisplayTime::current()` in views) converts and formats: `convert($value)`, `format($value, 'd.m.Y. H:i')`, `relative($value)`, `options()` (zones grouped by region, label `(UTC+01:00) Beograd`), `isValid($zone)`. `admin.access` applies the signed-in user's zone to every admin request. Used by data-table date cells, the activity feed, users list/edit, the profile card and the session list. Mails and CSV exports keep their old behaviour. Invalid zones are rejected by validation (profile, setting) and ignored at runtime (site zone).
