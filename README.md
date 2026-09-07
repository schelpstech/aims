# AIMS Nigeria Platform

Framework-free PHP 8.2+ modular-monolith foundation for the AIMS Nigeria digital professional association platform.

Production installation, release gates, backups, rollback, and post-deployment verification are documented in [DEPLOYMENT.md](DEPLOYMENT.md).

## Local setup

1. Run `composer install`.
2. Copy `.env.example` to `.env` and provide local values. Never commit `.env`.
3. Configure the web server document root as the `public` directory.
4. Ensure the PHP process can write to `storage/logs`.
5. Open `/health` to confirm that the application bootstrap is responding.

For PHP's local development server, run `php -S 127.0.0.1:8765 -t public public/router.php`. The development router serves existing assets directly and sends application routes through the front controller.

The health endpoint intentionally does not test the database or disclose configuration. Database connections are created lazily when a future application service requests one.

## Authentication configuration

Run the Stage 2, Stage 4, and Stage 19 migrations before enabling account routes against a persistent database. Registration and password-recovery requests use atomic database-backed identity/IP rate limits; tune their `AUTH_REGISTRATION_*` and `AUTH_RESET_*` environment values only after abuse monitoring. Authentication mail is disabled by default; configure a verified sender and HTTPS `APP_URL` before setting `MAIL_ENABLED=true`. Never expose reset or verification tokens in application logs.

The `/account` route is an authenticated foundation page only. Membership application and member-portal functionality are intentionally deferred.

## Leadership directory

The public `/leadership` page is database-driven through the leadership repository and service. Apply the Stage 5 migration and seeders documented in `database/README.md` to publish the confirmed roster. If the leadership data source is unavailable, the page fails safely by showing only the approved group structure and never exposes a database error or stack trace.

Leadership profile uploads and admin editing are not enabled in Stage 5. Optional profile fields are rendered only after validated data has been stored, and public output remains escaped by the view layer.

## Membership applications

Authenticated, email-verified users can open `/account/membership-application` to save a draft, upload private supporting documents, accept the declaration, and submit an application. Every write is protected by authentication and CSRF middleware, and repository queries enforce applicant ownership.

Apply the Stage 6 migration and membership-grade seeder before using this workflow. Submission produces an `AIMS-YYYY-...` reference and immutable history entry, but does not approve the application, create a member, generate a membership number, or activate membership. Review and approval administration remain intentionally deferred.

Private upload configuration is controlled by `MEMBERSHIP_DOCUMENT_PATH` and `MEMBERSHIP_DOCUMENT_MAX_BYTES`. The storage path must remain outside `public`.

## Membership administration

Authorized staff use `/admin/membership/applications` to search and filter submitted records, inspect application sections, download protected supporting documents, record internal review comments, raise applicant-visible queries, approve, or reject. Each route has a dedicated permission middleware and repeats authorization at the service boundary.

Run the Stage 7 review-comment migration and re-run the RBAC seeder before enabling this workspace. `MEMBERSHIP_NUMBER_PREFIX` controls the verified membership-number prefix. Approval is transactional and idempotent; rejection never deletes an application or its history.

## Member portal

Approved members use `/portal` for their dashboard, profile, membership summary, and member navigation. The portal displays membership identity, status, grade, join date, renewal status when an expiry exists, recent notifications, and activity counts.

All member records are resolved from the authenticated account rather than a URL-supplied member ID. Only approved profile fields are editable. Membership number, grade, status, join date, expiry, and account ownership require an authorized administrative workflow. Payments, certificates, downloads, and support remain safe integration hooks; no payment processing is active.

## Professional programmes

The public `/programmes` and `/professional-areas` directories, including slug-based detail pages, are database-driven. Only published programmes and active professional areas are exposed. If the catalogue database is unavailable, public pages show a safe unavailable state without disclosing connection details.

Authorized staff manage programmes, areas, area links, and eligible coordinator assignments at `/admin/programmes`. Programme applications and enrolment remain outside Stage 9 and have not been implemented.

## Programme applications and enrolment

Active authenticated users apply for published professional programmes through `/account/programme-applications`. Drafts are private to the owning account, submission is explicit, and duplicate user/programme applications are prevented at both service and database boundaries.

Authorized programme administrators use `/admin/programme-applications` to move submitted applications into review, approve or reject them, and separately create an enrolment. Approval and enrolment are transactional, idempotent where repeated requests are possible, and recorded in the audit log. Enrolments can be marked completed or withdrawn. Academic grading and student-result functionality are intentionally absent.

## Events

Published events and their public details are available under `/events`. Each event configures public or active-member-only registration, deadline, capacity, fee information, eligibility, venue or HTTPS online details, and publication status. Duplicate registrations are prevented by event/email and event/user database keys.

Authorized event administrators use `/admin/events` to create and update events, cancel events or registrations, inspect attendees, and record manual attendance. Attendance records are idempotent. The schema reserves a hashed attendance-token field and `qr` check-in method for a later QR issuer/scanner without storing plaintext QR secrets or implementing premature scanning complexity.

## Finance and payments

The Stage 12 migration adds configurable fee definitions, line-item invoices, payment attempts, immutable provider transaction evidence, and scoped idempotency claims. Monetary values use integer minor units and explicit ISO-style currency codes. Member-owned invoices are available at `/account/invoices`.

Online payment is disabled by default. The approved charter does not name a provider, so no Paystack or other provider credentials or assumptions are embedded in source. A provider adapter must implement `PaymentGatewayInterface`, authenticate webhook signatures, perform a fresh server-to-server verification, and map the response into the provider-neutral verification value object. Set `PAYMENT_GATEWAY` and an HTTPS `PAYMENT_CALLBACK_URL` only when that adapter has been approved and installed.

Browser redirects and webhook bodies are never accepted as payment proof. Settlement locks the payment and invoice inside one database transaction and verifies provider reference, successful status, exact amount in minor units, currency, and invoice metadata. Provider event IDs and payment references are unique, so callbacks and webhook replays are repeat-safe. Provider payloads are retained with a SHA-256 evidence hash after card-like fields are removed; card details must never be stored. Manual payment verification is intentionally not enabled.

Apply migrations using the guarded commands in `database/README.md`. Do not use `--force` against production until a reviewed backup and maintenance plan are in place.

## Membership renewals

Members manage renewal at `/account/membership-renewal`. An eligible request snapshots the active grade-specific policy, creates an auditable renewal record, and generates a linked invoice. Policy administrators configure the fee reference, period value and unit, date anchor, renewal window, grace period, effective dates, and active status; no annual period or fee is hard-coded or seeded.

Renewal is connected to the verified-payment listener and is applied only when the invoice is paid and an exact successful payment record has `verified_at`. Browser callbacks cannot renew membership. Renewal, expiry, policy edits, and reasoned administrative overrides retain renewal history and audit records. The expiry reconciliation hook is available for a controlled scheduler; production scheduling must be configured operationally.

## Certificates and public verification

Public verification at `/verify` supports membership numbers and certificate numbers. Signed QR links target `/verify/qr?token=...`. Results use an explicit public projection containing only holder name, public number, grade or certificate type, status, relevant dates, and applicable programme/event title. Contact details, addresses, private documents, and account identifiers are never selected for public results.

Certificate issuance is disabled until `CERTIFICATE_SIGNING_KEY` contains at least 32 characters and `CERTIFICATE_VERIFICATION_URL` is set to the HTTPS QR endpoint. QR tokens are deterministic HMAC signatures over opaque certificate public IDs, allowing authorized certificate rendering to regenerate a valid link without storing the raw token. The database retains only a SHA-256 digest. Certificate types and validity policies are database-configurable and no unverified types are seeded.

The `CertificateRendererInterface` and `CertificateDocument` contract prepare PDF/download generation without selecting a rendering library or exposing an incomplete download route. A renderer must embed the signed verification URL and consume only authorized certificate data before downloads are enabled.

## CMS and content management

The CMS provides database-driven pages, News, Announcements, Resources, Downloads, FAQs, and protected media. Public queries expose only records in `published` state whose publication time has arrived; drafts and archived records never enter public listings or detail routes. Titles generate lowercase hyphenated slugs, with repository-level collision suffixes, and content supports optional meta titles and descriptions.

Rich content is processed by an allowlist sanitizer before persistence. Script, style, iframe, object, embed, SVG, MathML, template, and form elements are removed; event handlers and arbitrary attributes are stripped; links are restricted to safe local paths or HTTPS. PHP now explicitly requires the DOM extension used by this sanitizer.

CMS media is stored outside `public` under `CMS_MEDIA_PATH`. Uploads require PHP HTTP-upload provenance, server-detected MIME validation, random filenames, a size limit, and restrictive filesystem permissions. Only JPEG, PNG, WebP, PDF, plain text, and CSV are allowed. Public delivery resolves a published database record and a validated relative path rather than accepting filesystem paths from requests.

Content Managers receive only `cms.edit` and `cms.publish`; they do not inherit Super Administrator capabilities. All administrative writes remain authenticated, CSRF-protected, permission checked, and audited.

## Administration and account security

Authenticated staff open `/admin` for a permission-aware dashboard. It links only to modules authorized for the current account; every destination retains its own route middleware and service-level authorization. The account overview links authorized staff to this dashboard and gives every authenticated user access to `/account/security`.

Changing a password requires the current password and the existing password policy. The update uses an optimistic hash check inside the database transaction, revokes unused reset tokens and all existing server-side sessions, rotates the current PHP session and CSRF token, and establishes one new current session. Successful changes and rejected or throttled attempts are recorded through the existing audit and security-event foundations. `AUTH_PASSWORD_CHANGE_*` environment settings control the dedicated rate limit.

## Architecture rules

- Controllers coordinate HTTP concerns and remain thin.
- Business rules belong in domain services.
- Repositories isolate persistence operations.
- External input is validated before use.
- State-changing web requests pass through CSRF middleware by default.
- Secrets are supplied through environment configuration, never source files.
