# Production readiness checklist

This checklist complements the application code. Values that depend on the hosting
provider must be configured in the deployment environment; do not commit production
secrets to this repository.

## Required before exposing the site publicly

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the canonical HTTPS origin and configure a valid `APP_KEY`.
- Configure HTTPS at the load balancer/web server. Set `SESSION_SECURE_COOKIE=true`
  when HTTPS is enforced and `SESSION_ENCRYPT=true` so session payloads stored in
  the database are encrypted with `APP_KEY`; keep `SESSION_HTTP_ONLY=true` and
  `SESSION_SAME_SITE=lax` unless a documented cross-site integration requires otherwise.
- Use a least-privilege database account; do not deploy with a database root account.
- Configure a durable session/cache/queue backend appropriate to the deployment.
  Run a queue worker under a process supervisor if queued jobs are enabled.
- Password reset logs other devices out by deleting the user's rows in the `sessions`
  table, so that part only applies when the session driver is `database`. With any
  other session driver, other sessions are still invalidated on their next request
  by the `AuthenticateSession` password-hash check — do not rely on the table delete
  alone, and keep `SESSION_DRIVER=database` unless the deployment consciously
  accepts that difference.
- Guest view/read throttling is keyed by client IP. Behind a reverse proxy or CDN,
  verify `request()->ip()` returns the real client IP (trust only that proxy's IP
  via `trustProxies`); otherwise all guests share one throttle bucket and guest
  views/reads are systematically undercounted.
- Configure a real mail transport (SMTP, SES, Postmark, or Resend), sender address,
  sender domain authentication (SPF/DKIM/DMARC where supported), and queue worker if
  mail notifications are queued. The development default `MAIL_MAILER=log` writes
  messages to logs and does not deliver them to users.
- Email verification is enabled: `User` implements `MustVerifyEmail`, the verification
  routes are registered, and the writer area requires the `verified` middleware.
  Before launch, send a real verification email and confirm the signed link completes
  verification. Also test password-reset delivery end-to-end.
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

## Automatic "Hot" stories

- `php artisan stories:refresh-hot` runs hourly from the scheduler (so the scheduler must be
  running). It ranks published stories by the last `STORIES_HOT_WINDOW_DAYS` days of activity
  (real reads x 3 + views, from the `story_daily_stats` table), marks the top
  `STORIES_HOT_LIMIT` that reach `STORIES_HOT_MIN_SCORE`, and unmarks automatic ones that fell out.
- The first ranking only has data for days after this feature was deployed, so the Hot section
  fills up as traffic arrives.
- When an admin toggles Hot on a story the decision is pinned (`hot_override` on/off) and the
  ranking never touches it; the "Hot: tự động" button in the admin list hands it back. The
  migration pins every story that was already hot, since those were all chosen by hand.

## Story URL import (admin)

- Set `STORY_IMPORT_REQUIRE_ALLOWLIST=true` in production and add each permitted
  host to `config/story_import.php` (`sources`) only after confirming the site owner
  allows automated collection. A host entry may be `[]` (generic parser) or contain
  XPath overrides: `title`, `author`, `description`, `chapter_links`,
  `chapter_content`, `remove`.
- Chapters are downloaded by `ImportStoryChapter` queue jobs, so a queue worker must
  run under a process supervisor. Jobs are rate limited per source host
  (`STORY_IMPORT_REQUESTS_PER_MINUTE`, default 60), retry with backoff and stop after
  3 real failures. Monitor `failed_jobs`; failed chapters can be retried from the
  batch progress page.
- Fetch safeguards: HTTPS on port 443 only, no redirects, no credentials in the URL,
  every resolved IP must be public (IPv4, IPv6, mapped/NAT64/6to4/Teredo checked), the
  validated IP is pinned for the request (requires `ext-curl`; otherwise the import
  fails closed), HTML/XML only, 2 MB maximum.
- Imported stories and chapters are unpublished drafts until an admin publishes them.
- Before importing, the admin can edit the title, author, description, status, categories,
  tags and cover. A cover URL is downloaded through the same SSRF-safe client (JPEG, PNG
  or WebP verified from the bytes, 3 MB maximum, not limited to the allowlist hosts because
  covers usually live on a CDN); an uploaded cover takes precedence and SVG is refused.
- File import (`.txt` split on "Chương N" headings, or `.zip` with one `.txt` per chapter)
  is read in memory with size checks (20 MB upload, 2 MB per chapter, 30 MB unpacked,
  500 chapters). Uploads wait in `storage/app/private/story-imports` until imported and
  are deleted after import or after one day; the web server must never expose that path.

## Database integration testing

The default `phpunit.xml` intentionally uses in-memory SQLite for fast, isolated
feature and unit tests. MySQL-only integration tests have a separate configuration:
`phpunit.mysql.xml`.

Before running it locally, create a dedicated test database (never point this test
configuration at development or production data):

```sql
CREATE DATABASE webtruyen_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure the MySQL host, port, username, and password in `.env.testing` (or in
the shell environment). The MySQL PHPUnit configuration defaults to the database
name `webtruyen_testing`; an explicitly exported `DB_DATABASE` takes precedence.

Run the MySQL FULLTEXT integration test with:

```bash
vendor/bin/pest --configuration=phpunit.mysql.xml --filter=MySqlFullTextSearchTest
```

This test uses Laravel's `RefreshDatabase` behavior and runs migrations against the
configured database. Use only the dedicated, disposable test database. The normal
`php artisan test` command continues to use in-memory SQLite.

## Deployment verification

1. Confirm the production environment variables without printing secret values.
2. Run `php artisan config:cache` and `php artisan route:cache` as part of deployment.
3. Run `php artisan migrate --force` only as a deliberate, backed-up production migration step.
4. Run `php artisan about`, verify `/up`, test registration, email verification, login/logout,
   password reset, and inspect logs.
5. Verify HTTPS redirects, secure session-cookie attributes, error pages, storage access,
   queue processing, scheduled tasks, backups, and restore procedure.
