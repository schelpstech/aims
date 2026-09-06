# AIMS Nigeria production deployment

This runbook prepares the current application for a controlled production release. It does not authorize or perform a deployment. An authorized operator must run all commands against the intended release and environment.

## Release gates

Run the read-only preflight from the release root and resolve every failure before enabling traffic:

```text
php bin/production-check.php
```

The checker does not print environment values or database connection details. Current release constraints are:

- Online payments must remain disabled. The finance domain exists, but bootstrap wires `UnavailablePaymentGateway`; no approved Paystack or other provider adapter or credential variables exist.
- Email uses PHP `mail()` and the host mail transfer agent. Keep `MAIL_ENABLED=false` until sender authentication, delivery, bounce handling, and verification/reset journeys pass production-path testing. There is no application-managed SMTP transport yet.
- Certificate issuance is unavailable until a secret signing key of at least 32 characters and an HTTPS verification URL are supplied.
- Publish a sitemap only after the official canonical HTTPS domain is verified. Then add its absolute URL to `public/robots.txt`.

## Server requirements

- Apache 2.4 with `mod_rewrite`, or equivalent Nginx/IIS front-controller routing to `public/index.php`.
- The virtual-host document root must be the application's `public/` directory, never the repository root.
- PHP 8.2+ with `fileinfo`, `dom`, `json`, `mbstring`, `pdo`, and `pdo_mysql`. Enable OPcache in production.
- Composer 2 for release installation.
- MySQL 8.0+ or a compatible MariaDB release with InnoDB, foreign keys, transactions, `utf8mb4`, JSON functions, microsecond timestamps, and `INET6_ATON`. Validate the exact server version in staging.
- Valid HTTPS and a scheduler for notification dispatch, log rotation, and backups.

With the supplied limits, PHP needs at least `upload_max_filesize=10M` and `post_max_size=12M`; allow request overhead. Set `display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`, and `expose_php=Off`.

## Installation

1. Create a versioned release directory and retain the previous release for rollback.
2. Install committed dependencies:

   ```text
   composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative
   ```

3. Copy `.env.production.example` to `.env`, fill it through the deployment secret store, and restrict access to the deployment and PHP service identities. Never put secrets in shell history, logs, tickets, or source control.
4. Create these PHP-writable directories:

   ```text
   storage/logs
   storage/sessions
   storage/private/membership-documents
   storage/private/cms-media
   ```

5. Point the web server at `public/`. Deny direct access to `.env`, `app/`, `bootstrap/`, `config/`, `database/`, `storage/`, `tests/`, and `vendor/` as defence in depth.
6. Run `php bin/production-check.php` and clear all failures before traffic is enabled.

## Environment variables

`.env.production.example` is the complete supported production template.

| Group | Production requirement |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Use `production`, `false`, and the canonical HTTPS origin. |
| `LOG_LEVEL`, `LOG_PATH` | Use `info` or stricter; keep logs outside public access and rotate them. |
| `DB_*` | Supply through the secret store. Use a least-privilege application account; a separate migration account is preferable. |
| `SESSION_*` | Require secure cookies. Leave `SESSION_DOMAIN` empty for a host-only cookie unless cross-subdomain sharing is approved. |
| `AUTH_*` | Defaults are the tested baseline; changes require security review. |
| `AIMS_*` | Populate only verified organisation contact information. |
| `MAIL_*` | Enable only after the host MTA and end-to-end delivery are verified. |
| `MEMBERSHIP_*`, `CMS_*` | Paths must remain outside `public/`; PHP and web-server limits must cover the configured sizes. |
| `PAYMENT_*` | Keep disabled and callback empty in this release. Never store provider secrets in `system_settings`. |
| `CERTIFICATE_*` | Keep the signing key in the secret environment; use the canonical HTTPS `/verify/qr` endpoint. |

On a typical Linux host, start with `.env` mode `0600`, directories `0750`, and code files `0640`, adjusted through service-group ownership or ACLs. Grant the PHP worker write access only to `storage/*`. Never use `0777`.

## Database and migrations

Create the database before running application commands. Confirm the target engine/version, `@@default_storage_engine = InnoDB`, `utf8mb4`, consistent collation, and strict SQL behaviour.

1. Restore a production-like backup into staging and test the complete migration sequence there.
2. Take and verify a fresh production database and private-file backup immediately before the change window.
3. Use edge maintenance mode when schema changes are incompatible with the current release.
4. Inspect pending migrations: `php bin/database.php status`.
5. Apply timestamped migrations in filename order: `php bin/database.php migrate --force`.
6. Run repeatable approved seeders only after migrations: `php bin/database.php seed --force`.
7. Re-run database status and production preflight.

Seeders provide base roles, permissions, and verified catalogues; they do not create users or guessed organisation details. MySQL DDL can commit implicitly, so inspect schema and the migration ledger after a failure before retrying. Never edit an applied migration.

## Uploads, sessions, and permissions

- Membership documents and CMS media must stay outside the document root. Back up private uploads with the matching database recovery point.
- Only the PHP worker writes to logs, sessions, and private uploads; application code is otherwise read-only to it.
- File sessions support one application server. Before horizontal scaling, deliberately use sticky sessions or implement a shared session handler; do not use an eventually consistent filesystem.
- Schedule expired-session cleanup through the platform PHP session-GC policy. Never serve, copy to another environment, or include live session files in ordinary backups.
- Keep web-server request-body limits at or above PHP `post_max_size`, while retaining the application's stricter per-file checks.

## HTTPS and security headers

The application emits CSP, clickjacking, MIME-sniffing, referrer, and permissions policies. HSTS is sent only when PHP sees HTTPS enabled. If TLS terminates at a proxy, configure the trusted web server to set the CGI/FastCGI HTTPS parameter; the application intentionally does not trust arbitrary `X-Forwarded-Proto` input.

Test headers at the public HTTPS origin. Do not enable the current HSTS `includeSubDomains` policy until HTTPS works on every intended subdomain. Protected pages use `no-store`; static CSS, JavaScript, images, and fonts receive cache lifetimes in `public/.htaccess`. Purge or version assets when replacing them.

## Email, notifications, and cron

The notification worker queues scheduled messages and dispatches one bounded batch. Run one instance at a time from the current release, typically each minute:

```text
* * * * * cd /srv/aims/current && /usr/bin/flock -n /run/lock/aims-notifications.lock /usr/bin/php bin/notifications.php 50 >> /var/log/aims-notifications.log 2>&1
```

Adapt paths for the host. Rotate the worker log and alert on repeated non-zero exits or dead notification records. SMS remains unavailable until an approved provider adapter exists.

The renewal repository exposes controlled expiry reconciliation, but this release has no dedicated renewal-expiry CLI. Do not invent a cron command; treat automatic expiry as an operational feature gate if required at launch.

## Payment callbacks and webhooks

`/payments/callback` and `/payments/webhook` exist, but no live adapter is wired. Browser redirect is never proof of payment. A separately reviewed provider release must:

- authenticate webhook signatures over the raw body;
- verify reference, amount, currency, invoice identity, and success server-to-server;
- preserve provider evidence without card data;
- retain transactions and idempotency against retries, replay, duplicates, and concurrency;
- take production secrets only from the secret environment; and
- pass incorrect-amount, invalid-invoice, duplicate-callback, replay, and provider-sandbox tests.

When approved, expose only the required HTTPS POST webhook, restrict request size, monitor failures, and retain signature verification. An IP allowlist must never be the only authentication control.

## robots.txt, sitemap, and caching

`public/robots.txt` permits public pages and discourages account, admin, portal, payment, and protected-media indexing. Robots rules are advisory and never replace authorization.

Generate `public/sitemap.xml` after the canonical domain is verified. Include public canonical URLs and published dynamic content only; exclude authentication, account, admin, payment, media, draft, and archived records. Add its absolute HTTPS URL to `robots.txt`, and regenerate it when published content changes.

## Logging and rotation

Application logs are newline-delimited JSON and redact common password, token, cookie, authorization, secret, and API-key fields. With debug disabled, users receive only an error reference.

The logger appends to a single file and does not rotate internally. Configure OS rotation: a typical policy rotates daily, retains 14 compressed files, and creates replacements with restrictive ownership. Use `copytruncate` only when the PHP process cannot reopen a renamed file. Verify rotation in staging and alert on error-level records.

## Backup and restore

Use encrypted, access-controlled off-host backups with a documented policy such as 7 daily, 4 weekly, and 12 monthly copies. Follow the 3-2-1 principle and monitor freshness.

- Database: create a consistent backup with `mysqldump --single-transaction --quick --routines --triggers --events` using a restricted client option file or managed backup service. Never put the password on the command line.
- Files: capture membership documents and CMS media from the same recovery point as the database. Back up deployment secrets through the secret-management system.
- Exclude logs, caches, sessions, and reproducible `vendor/` content unless policy requires them.
- Encrypt in transit/at rest, checksum copies, restrict restore access, and test a full isolated restore at least quarterly.

A backup is verified only after a restore confirms database integrity, private-file linkage, login, authorization, and verification journeys.

## Post-deployment tests

Record results without credentials or personal data:

1. `php bin/production-check.php` returns zero failures.
2. `php bin/database.php status` reports no pending migrations.
3. `/health` returns HTTP 200, `{"status":"ok"}`, and `Cache-Control: no-store`.
4. Public home, about, leadership, programmes, events, contact, and verification pages respond correctly over HTTPS.
5. HTTP redirects to HTTPS; HTTPS returns CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`, Referrer-Policy, and Permissions-Policy without `X-Powered-By`.
6. Session cookies are Secure, HttpOnly, and SameSite. Login regenerates the identifier and logout invalidates it.
7. Unauthenticated protected access fails, missing CSRF returns 419, and ID manipulation cannot cross ownership.
8. Registration, email verification, forgot/reset password, throttling, and token expiry pass through the production mail path.
9. Private documents/media have no direct filesystem URL; authorized delivery still works.
10. Notification cron completes without overlap and records attempts.
11. Certificate public verification exposes only approved safe fields.
12. Payments remain unavailable unless the separately approved provider release passed its full sandbox/replay suite.
13. Run repository PHP lint and the complete test suite on the release candidate.

## Rollback

1. Stop new traffic or enable edge maintenance mode. Pause workers and approved webhook delivery where possible.
2. Preserve logs and record the release identifier and migration batch; do not delete evidence.
3. If no incompatible schema change occurred, switch the `current` release pointer to the previous verified code and reload PHP workers/OPcache.
4. If schema or data is incompatible, restore the pre-deployment database and matching private-file snapshot. Migration rollback is not a substitute for a verified restore because MySQL DDL may commit implicitly and live data may have changed.
5. Run smoke and security checks against the restored release before reopening traffic.
6. Resume workers/webhooks only when application and database versions match. Reconcile provider events without manually marking unverified payments successful.

Practice rollback in staging and define recovery-time and recovery-point objectives before the first production deployment.
