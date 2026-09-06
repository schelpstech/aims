# Database migrations

The database layer targets MySQL/MariaDB with InnoDB and `utf8mb4`. Every schema change must be represented by a timestamped migration; undocumented manual production changes are not permitted.

## Configuration

Copy `.env.example` to `.env` and set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Credentials must never be committed. The database itself must be created by an authorized operator before running migrations.

## Commands

```text
php bin/database.php status
php bin/database.php migrate
php bin/database.php seed
php bin/database.php rollback
php bin/database.php rollback --batches=2
```

Mutating commands require `--force` when `APP_ENV=production`. Take and verify a database backup before production migration or rollback. MySQL DDL can commit implicitly, so a failed schema migration must be inspected before retrying.

Run migrations before seeders. Seeders are repeatable and do not create users or overwrite configured system-setting values.

## Migration rules

- Inspect the destination schema and existing names before adding a migration.
- Use `BIGINT UNSIGNED` consistently for the identity and RBAC foreign keys.
- Use InnoDB, deliberate indexes, and explicit foreign-key actions.
- Add domain tables only in their authorized implementation stage.
- Never edit an applied migration; create a new migration.
- Test both migration and rollback against a disposable database before production use.

## Sensitive settings

Ordinary organisation configuration uses `setting_value`. Sensitive values must be encrypted by an application service before storage and placed only in `encrypted_value` with `is_sensitive = 1`. Database constraints prevent a sensitive setting from using the plaintext value column or being marked public.

Authentication secrets, database passwords, payment credentials, encryption keys, and similar deployment secrets belong in environment configuration rather than `system_settings`.

## Authentication migrations

Stage 4 adds `sessions`, `login_attempts`, `email_verification_tokens`, and `password_reset_tokens`. Session identifiers and one-time tokens are represented only by SHA-256 digests in the database. Apply the migration before enabling account registration or login.

Authentication and recovery email delivery remains disabled until `MAIL_ENABLED=true`, `MAIL_FROM_ADDRESS`, and an HTTPS `APP_URL` are deliberately configured and tested. Login-attempt and expired-token retention should be handled by an authorized scheduled maintenance task when operational scheduling is introduced.

Stage 19 adds `auth_request_rate_limits`. Its fixed-window counters are updated atomically with a unique database bucket, closing concurrent request-limit bypasses for registration and password recovery. Identity and IP scopes are stored only as SHA-256 digests. Apply `20260905_290000_create_auth_request_rate_limits.php` before deploying the corresponding authentication code, and include expired bucket cleanup in the authorized retention task.

## Organisational leadership migrations

Stage 5 adds `people`, `leadership_groups`, `leadership_positions`, and `leadership_assignments`. The foreign keys use the same `BIGINT UNSIGNED` type as their parent keys, and deletion is restricted so an assigned governance record cannot be removed accidentally.

Run the normal migration and seed commands to install this module. Seeder order is significant: `20260904_180000_leadership_groups_seeder.php` creates the four approved groups, followed by `20260904_181000_confirmed_leadership_seeder.php`, which creates the confirmed people, neutral group-member positions, and assignments. Both seeders are repeatable.

The confirmed roster contains only names and honorifics supplied by the project owner. Qualifications, biographies, professional areas, photos, email addresses, LinkedIn URLs, and appointment dates remain empty until verified. Photo administration is intentionally deferred; any future upload handler must store validated images beneath `/assets/uploads/leadership/` and must not trust a client-supplied path or filename.

## Membership foundation migrations

Stage 6 adds `membership_grades`, `members`, `membership_applications`, `membership_application_documents`, and `membership_history`. Run `20260904_190000_create_membership_foundation_tables.php` before `20260904_190000_membership_grades_seeder.php`.

The grade seeder creates Fellow, Member, and Associate without abbreviations, eligibility, benefits, or fees. Those values remain database-configurable because the approved source material contains conflicting abbreviations and no verified fee schedule.

Application status values are `draft`, `submitted`, `under_review`, `query_raised`, `approved`, `rejected`, and `cancelled`. Submission writes a reference and status-history entry but never inserts a `members` row. Future authorized approval and activation are separate operations; even a future member record defaults to `pending_activation`, not `active`.

Uploaded application files are stored outside the public document root under `storage/private/membership-documents` by default. The upload layer verifies PHP upload provenance, actual server-side file size, MIME type, a randomized storage name, and a SHA-256 digest. Only PDF, JPEG, and PNG are accepted, with a default 5 MB limit. Do not serve that directory directly through the web server.

## Membership administration

Stage 7 adds `membership_review_comments` and extends the RBAC seeder with `member.view`, `member.review`, `member.approve`, `member.reject`, and `member.query`. Re-run the RBAC seeder after applying `20260904_191000_create_membership_review_comments_table.php`. The Membership Reviewer role receives view, review, and query access; final approval and rejection remain with the Membership Administrator and Super Administrator roles.

Approval locks the application and checks its status inside one transaction, generates a unique membership number, creates the active member, appends membership history, and writes the audit trail. Repeating approval returns the existing member rather than creating another. Rejection changes status and retains the application, documents, comments, history, and audit records.

## Member portal foundation

Stage 8 adds `member_profiles` and `member_notifications` through `20260904_192000_create_member_portal_foundation_tables.php`. Apply it after the membership foundation and administration migrations.

Portal membership identity is always resolved from the authenticated `users.id`; there are no member-ID-based profile routes. Members may update only the separate profile fields. Membership number, grade, status, join date, expiry, and ownership remain in administrator-controlled records. Profile updates lock the authenticated user's active member row and write an audit entry.

Programme-enrolment and event-registration dashboard counts use their implemented Stage 10 and Stage 11 records. Certificate counts remain zero until that domain is introduced.

## Professional areas and programmes

Stage 9 adds `professional_areas`, `programme_types`, `programmes`, `programme_areas`, and `coordinator_assignments`. Apply `20260904_200000_create_professional_programme_tables.php`, then run the repeatable catalogue seeder. The seeder creates only the six confirmed professional areas and four confirmed programme types; it deliberately creates no programme, fee, duration, requirement, delivery-mode, or coordinator-assignment records.

Public pages read only active areas and `published` programmes. Draft and archived records remain private. Programme coordinators can be selected only from active people with a current assignment in the Programme Coordinators leadership group. Archiving is used instead of destructive deletion, and a professional area linked to a programme cannot be archived.

Re-run the RBAC seeder to install `programme.view`, `programme.create`, `programme.update`, `programme.delete`, and `programme.assign_coordinators`. Programme Administrator and Super Administrator receive catalogue-management permissions. All catalogue writes are CSRF-protected, checked in route middleware and again in the service, and recorded in `audit_logs`.

## Programme applications and enrolments

Stage 10 adds `programme_applications` and `programme_enrolments` through `20260904_210000_create_programme_application_enrolment_tables.php`. Apply it after the Stage 9 programme catalogue migration. No seed records are required.

Only active, email-verified users may save an application for a published programme. A database unique key on `(user_id, programme_id)` prevents duplicate applications. Applicant queries always include the authenticated user ID, and applicants may withdraw only a draft, submitted, or review-stage application.

The controlled lifecycle is Draft → Submitted → Review → Approved or Rejected, followed by a separate Approved → Enrolled transition. Enrolled records may become Completed or Withdrawn. Approval never creates an enrolment automatically. Administrative access is separated across `programme.application_view`, `programme.application_review`, `programme.application_approve`, `programme.application_reject`, and `programme.enrol`; affected records are locked, changes run transactionally, and audit records identify the acting user. Enrolment creation is idempotent and generates a unique, configurable number using `PROGRAMME_ENROLMENT_PREFIX`.

This module stores professional programme workflow records only. It contains no academic courses, semesters, scores, grades, transcripts, or student-result processing.

## Event management

Stage 11 adds `event_types`, `events`, `event_registrations`, and `event_attendance` through `20260905_220000_create_event_management_tables.php`. Run the matching repeatable event-type seeder after migration. It creates only Induction, Investiture, Conference, Workshop, Webinar, AGM, Training, Awards, and Other; no event dates or event records are invented.

Each published event controls whether registration is public or restricted to an active member. Registration locks the event while checking deadline and capacity, and unique event/email plus event/user constraints prevent duplicates. All registration and attendance writes are audited. Event administration requires the existing `event.manage` permission at both middleware and service boundaries.

Attendance currently supports authorized manual check-in. The schema is QR-ready through a unique nullable SHA-256 token-digest field and an explicit `qr` check-in method, but Stage 11 does not issue plaintext tokens, render QR codes, or add scanner complexity. Event fees are informational until the authorized payment-processing stage is implemented.

## Finance and membership renewal

Stage 12 adds `fee_settings`, `invoices`, `invoice_items`, `payments`, `payment_transactions`, and `idempotency_keys`. Stage 13 then adds `membership_renewal_policies`, `membership_renewals`, and `membership_renewal_history`. Apply them in timestamp order; the renewal migration deliberately depends on the finance and membership tables.

Renewal fees must be active `fee_settings` records with type `membership_renewal`. Policies are grade-specific and store a configurable period value/unit, `current_expiry` or `payment_date` anchor, renewal window, grace period, and effective range. Overlapping active policies for the same grade are rejected by the repository. No policy or fee is seeded because no approved duration or price has been supplied.

Generating a renewal locks the authenticated member record, prevents a second open renewal, snapshots the policy and fee, creates a linked invoice, and records each workflow transition. Only an invoice with an exact successful server-verified payment can move the renewal through Paid to Renewed. Expiry reconciliation and authorized overrides preserve history and audit records; override and policy configuration use separate permissions.

## Certificates and verification

Stage 14 adds `certificate_types`, `certificates`, and `certificate_verifications` through `20260905_250000_create_certificate_tables.php`. It depends on the existing users, members, people, programmes, and events tables. No certificate type is seeded because no approved type catalogue or validity policy has been supplied.

Certificates belong to exactly one member or person and may optionally reference a programme or event. Revocation updates the existing record rather than deleting it, requires a reason, and writes an audit event. Public verification logs store only a one-way lookup digest, outcome, request address, and user agent. Public lookup queries deliberately omit email, phone, address, private documents, and internal account identifiers.

Set the signing key through environment configuration only. Never store the raw QR verification token in the database. Apply this migration and re-run the RBAC seeder to install `certificate.issue`, `certificate.revoke`, and `certificate.type_manage` permissions.

## CMS and managed content

Stage 15 adds `pages`, `posts`, `media`, `faqs`, and `downloads` through `20260905_260000_create_cms_tables.php`. Each managed content table uses Draft, Published, and Archived states. Publication timestamps and indexed public queries prevent draft or future content from appearing publicly.

Media metadata stores a randomized relative path, server-detected MIME type, byte size, and SHA-256 digest. Files themselves remain outside the public document root and are delivered only after a published media or download lookup. Do not move `CMS_MEDIA_PATH` beneath `public`; application bootstrap rejects that configuration.

No content is seeded. Apply the migration and use the existing repeatable RBAC seeder to retain the narrowly scoped `cms.edit` and `cms.publish` permissions assigned to Content Manager. Super Administrator remains a separate role with broader permissions.

## Notifications and communication

Stage 16 adds `notification_outbox` and `notification_deliveries` through `20260905_270000_create_notification_tables.php`, and links generated in-app records to their outbox item. Domain transactions enqueue deterministic messages only; external delivery happens later, so an email or SMS failure cannot roll back the business action. Unique deduplication hashes and per-channel attempt keys make replay repeat-safe.

Run `php bin/notifications.php 50` from a scheduler (typically once per minute). It also queues policy-driven renewal reminders and certificate notifications before dispatching pending work. Email attempts are retained in `notification_deliveries`; failure details are deliberately bounded and must never contain tokens or message bodies. SMS uses a disabled adapter until an approved provider implementation is configured.

## Reporting

Stage 17 adds administrative reporting over the existing membership, programme, event, renewal, invoice, and payment tables. Migration `20260905_280000_add_reporting_indexes.php` adds date-first composite indexes used by bounded dashboard and export queries; it creates no reporting snapshots or duplicate business data.

Dashboard access requires `report.view`. CSV download additionally requires `report.export`, while all payment and revenue metrics require the independent `report.finance` permission at the service boundary. Re-run the RBAC seeder to add the finance permission. Exports are capped at 10,000 rows, neutralize spreadsheet formulas, omit contact details, and use a validated reporting interval of no more than ten years.

The key-value design supports organisation fields including `organisation_name`, `short_name`, `registration_number`, `official_email`, `membership_email`, `phone`, `whatsapp`, `website`, `address`, `logo`, and `social_links`. Only the organisation name and short name verified by the approved project charter are seeded.
