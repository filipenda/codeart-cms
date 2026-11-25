# Authentication (staff)

Headless backend for signing in to the admin. There are **no screens** in core: the next task builds them on
top of the routes, names and JSON shapes below. Read this file alone and you can start.

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

Core makes `admin` the default guard when the app still has `web` (the database session handler needs that to record user ids). `config('codeart.auth.guard')` names it; `config('codeart.auth.views')` is `false` until the screens task sets it to `true` (see below).

## Roles and permissions

All under guard name `admin`. `CreateDefaultRoles` grants a permission to roles only when the permission is first created, so a later revoke sticks.

| Permission | admin | editor | member | super-admin |
|---|---|---|---|---|
| `admin.access` | yes | yes | no | Gate bypass |
| `users.view`, `users.invite`, `users.manage`, `users.approve` | yes | no | no | Gate bypass |
| `settings.appearance` | yes | no | no | Gate bypass |

`member` is the role of registered visitors: it has no permission, so it never reaches the admin (403).

Rank (`config('codeart.auth.role_rank')`): super-admin 100, admin 50, editor 10, member 0, unknown roles 98. Nobody may assign, remove or touch a role above their own; **only a super-admin touches a super-admin** (`RoleRank`, enforced in every action and in `UserPolicy`). A null actor (command line) counts as super-admin.

`UserPolicy` abilities (registered for the app's User model): `viewAny`, `view` (`users.view`), `invite` (`users.invite`), `create`, `update`, `deactivate`, `delete` (`users.manage` + rank; never yourself for deactivate/delete), `approve` (`users.approve` + rank).

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
| `security.activity_log.retention_days` | 90 | super-admin |
| `mail.from_name`, `from_address`, `driver` (`smtp`/`log`), `host`, `port`, `username`, `password` (secret), `encryption` (`tls`/`ssl`/`none`) | from `.env` | super-admin |

Mail settings override `.env` only for values that were saved. 2FA mode: `off` hides/blocks setup (users who already have 2FA are still challenged); `optional` allows it; `required` forces it for `required_roles`.

## Flows

**Sign-in.** POST `login.store`. Unknown e-mail, wrong password and inactive account give the identical 422 (and about the same work). Lockout per e-mail + IP. The session id is regenerated; logout invalidates the session. With 2FA enabled the response is `{"two_factor": true}` and the user is not signed in until `two-factor.login.store` succeeds (session carries the pending login, so the screens must keep cookies).

**Password reset.** `password.email` answers 200 with the same text for known, unknown and throttled addresses; the mail is sent after the response. `password.update` answers the same 422 for a wrong e-mail or a wrong token. A reset ends all the user's sessions.

**E-mail verification.** The mail links to the signed `verification.verify` route; the user must be signed in. Required only while `auth.registration.require_email_verification` is on.

**Invitations (the main way to add staff).** `InviteUser` (action) or `php artisan user:invite name email role`. Needs `users.invite`, and the role must not be above the inviter's (admin: up to admin, never super-admin). One open invitation per address; the token is random, stored only as a SHA-256 hash, valid `auth.invitation.expiry_days`, single use. The mail contains a signed URL to `admin.invitation.show`. **The screens task** renders the "set your password" form at that URL and POSTs the same URL with `password`, `password_confirmation` and optional `locale` (`admin.invitation.accept`): it creates the user (verified, active, approved, roles from the invitation, `invited_at`). Unknown, used, expired or no-longer-allowed invitations all answer 410; a tampered link 403.

**Registration** (off by default). When enabled, `register.store` creates a user with the default role. With approval required the user is `is_active=false`, `approved_at=null` and cannot sign in until approved; they are signed in right after registering, so they can verify the e-mail in that session. Mails: verification, "registration received", and a "new registration pending" to every active user with `users.approve` plus super-admins. Approve with `ApproveUser` (needs `users.approve`) or `php artisan user:approve email`; the user gets "registration approved". Known trade-off: the e-mail uniqueness check reveals that an address is registered (login and reset do not).

Bot protection (`Codeart\Core\Auth\BotProtection`, applied to `register.store`): the form must send the hidden fields `form_token` (value: `app(BotProtection::class)->issueToken()`, an encrypted timestamp) and the honeypot `company_website` (empty; hide it with CSS, `tabindex="-1"`, `autocomplete="off"`). Failure gives the same generic 422 as any failed registration. Field names: `tokenField()`, `honeypotField()`.

**Sessions.** `UserSessions` (list / revoke one / revoke others / revoke all). Revoking others also rotates the remember token.

**2FA.** Enable (password confirmation first) → show QR/secret → confirm with a code → show recovery codes. Recovery codes are replaced when used. Lost phone: `php artisan user:reset-2fa email` (logged).

## What the screens task must build

Login (with remember me and the 2FA challenge step), forgot password, reset password (reads `token` and `email` from the URL; the mail links to the named route `password.reset`, or `/admin/reset-password/{token}?email=` if that route is missing), verify-email notice (`verification.notice`) with a resend button, password confirmation (`password.confirm`), 2FA setup (`admin.two-factor.setup`: QR, secret, confirm, recovery codes), invitation acceptance (`admin.invitation.show`), registration form, own-sessions list, change password, and a pending-registrations screen (`admin.users.pending`, linked from the approvers' mail; falls back to `/admin/users/pending`). To get Fortify's GET screen routes set `config('codeart.auth.views')` to `true` and register the views with `Fortify::loginView(...)` etc. Use `GET admin.session` to learn who is signed in and why access is denied. Unauthenticated browser requests are redirected to route `login` once it exists (else 401).

## Mail

Sent in the user's `locale` (`sr`, `sr-Cyrl`, `en`; falls back to the app locale): invitation, reset password, verify e-mail, registration received, registration approved, new registration pending, plus the `mail:test` message. One template (`codeart::mail.message`); every color comes from `ThemeSettings::emailPalette()` (derived from the theme settings and the base colors in config), so there are no color literals in views. Reset, verification and registration mails are sent after the response (no queue worker is needed); invitations are sent at once so an error is visible. `php artisan mail:test you@example.com` checks the settings.

## Activity log

`ActivityLog::record($event, $description, $subject, $causer, $properties, $log)` writes to `activity_log` (spatie/laravel-activitylog; adds `ip_address` and `user_agent`). Property keys that look like secrets (`pass`, `token`, `secret`, `recovery`, `code`, `key`, `hash`, ...) are dropped automatically. Events: `auth.login`, `auth.login_failed`, `auth.logout`, `auth.lockout`, `password.reset_requested`, `password.reset_completed`, `password.changed`, `two_factor.setup_started|enabled|disabled|failed|recovery_code_used|recovery_codes_regenerated|reset`, `email.verified`, `registration.created`, `invitation.sent|accepted`, `user.created|approved|deactivated`, `role.attached|detached`, `permission.attached|detached`, `session.others_revoked`. `activity-log:prune` deletes entries older than `security.activity_log.retention_days`; core schedules it daily (the client's cron runs `schedule:run`).

## Language files

`packages/core/resources/lang/{sr,en}` are written by hand; `sr-Cyrl` is generated: `php artisan lang:transliterate` (placeholders such as `:name` stay untouched; `--check` only reports). Commit the generated files. A test fails when a key is missing in any language or `sr-Cyrl` is stale. Namespace `codeart::` (`auth`, `mail`, `framework`). `framework.php` holds fallbacks for Laravel's own `auth.*`, `passwords.*` and `validation.*` keys, used only when the app has no translation for the key.

## Commands

`user:create {name} {email} {role}`, `user:invite`, `user:approve`, `user:deactivate`, `user:reset-2fa`, `activity-log:prune`, `mail:test {address}`, `lang:transliterate`, `codeart:roles`.
