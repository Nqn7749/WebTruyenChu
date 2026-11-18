# Production readiness checklist

This checklist complements the application code. Values that depend on the hosting
provider must be configured in the deployment environment; do not commit production
secrets to this repository.

## Required before exposing the site publicly

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the canonical HTTPS origin and configure a valid `APP_KEY`.
- Configure HTTPS at the load balancer/web server. Set `SESSION_SECURE_COOKIE=true`
  when HTTPS is enforced; keep `SESSION_HTTP_ONLY=true` and
  `SESSION_SAME_SITE=lax` unless a documented cross-site integration requires otherwise.
- Use a least-privilege database account; do not deploy with a database root account.
- Configure a durable session/cache/queue backend appropriate to the deployment.
  Run a queue worker under a process supervisor if queued jobs are enabled.
- Configure a real mail transport and verify password-reset delivery. Email verification
  is intentionally disabled in the current application state; decide whether to re-enable
  it before relying on verified email as an identity control.
- Configure public storage and verify uploaded covers are served from the intended
  public disk. Keep private files outside the public web root.
- Run migrations as an explicit deployment step after taking a verified database backup.
  Never use `migrate:fresh` against production data.
- Ensure the web server serves only the Laravel `public/` directory and denies access
  to dotfiles, `.env`, source files, logs, and storage internals.
- Restrict write permissions to the required `storage/` and `bootstrap/cache/`
  directories. Do not make the entire application tree writable.
- Configure database and uploaded-file backups, retention, off-site storage, and perform
  a restore drill before launch.
- Monitor application errors, failed jobs, disk space, database capacity, and suspicious
  authentication activity. Avoid logging passwords, reset tokens, session IDs, or secrets.

## Application safeguards in this phase

- Login attempts retain their existing per-identity-and-IP throttle.
- Registration, password-reset requests, password reset submissions, password
  confirmation, and authenticated password changes have explicit route-level throttles.
- Continue to review authorization and upload validation whenever new write endpoints
  are introduced.

## Deployment verification

1. Confirm the production environment variables without printing secret values.
2. Run `php artisan config:cache` and `php artisan route:cache` as part of deployment.
3. Run `php artisan migrate --force` only as a deliberate, backed-up production migration step.
4. Run `php artisan about`, verify `/up`, test login/logout and password reset, and inspect logs.
5. Verify HTTPS redirects, secure session-cookie attributes, error pages, storage access,
   queue processing, scheduled tasks, backups, and restore procedure.
