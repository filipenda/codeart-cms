# Privacy note for clients (IP addresses)

**What is stored.** When staff sign in or act in the admin, the CMS records the IP address in the activity log, in the list of active sessions and as "last sign-in" on the profile. Nothing else about location is stored.

**For how long.** Activity log entries are deleted after `security.activity_log.retention_days` (default 90 days). Session rows disappear when the session expires (default 120 minutes idle) or the user signs out. "Last sign-in IP" is overwritten at every sign-in. Login throttling keeps the raw IP only in the cache, for the lockout window (default 15 minutes).

**How to change it.** A super-admin sets `security.ip_storage`: `full` (default), `anonymized` (last IPv4 octet / last 80 bits of IPv6 zeroed) or `off` (nothing stored, "IP adresa" rows hidden). From the command line: `php artisan settings:set security.ip_storage anonymized`. Addresses already stored are brought in line by the daily `activity-log:prune` run (or run it once by hand).

Technical details: `docs/auth.md`, "IP storage (privacy)".
