# Local deployment record

The AIMS application is installed on the active Laragon stack at:

```text
https://aims.test/
```

Local components:

- Apache: Laragon Apache 2.4, with `aims.test.conf` installed as the AIMS virtual host.
- PHP: Laragon PHP 8.3.
- Database: MySQL 8.4 database `aims_nigeria`, using a dedicated runtime account with CRUD-only privileges.
- Initial administrator: credentials are stored outside the document root in `storage/private/local-admin-credentials.txt`. They are ignored by source control and must not be shared.
- Notification worker: Windows scheduled task `AIMS Local Notification Worker`, once per minute.
- Initial verified backup: stored beneath `storage/private/backups/`.

HTTP requests redirect permanently to HTTPS. The Laragon development certificate is valid for `aims.test` but is for local use only.

Operational checks:

```text
php bin/production-check.php
php bin/database.php status
php bin/notifications.php 50
```

Email and online payment remain disabled. Do not enable them until approved provider adapters and credentials are configured and tested. This local deployment is not an Internet production deployment.
