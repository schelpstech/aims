# cPanel database installation

The `aims-schema-and-seed.sql` file is intended for a new, empty AIMS database.
It contains the complete application schema and approved baseline catalogue data.
It deliberately excludes users, passwords, sessions, applications, payments,
audit events, and private operational records.

## Import with phpMyAdmin

1. Back up the destination database if it is not empty.
2. Open cPanel and select **phpMyAdmin**.
3. Select the exact database configured as `DB_DATABASE` in the production `.env`.
4. Select **Import**, choose `aims-schema-and-seed.sql`, retain UTF-8 and SQL format,
   and start the import.
5. Confirm that phpMyAdmin reports success and that 56 application tables exist.
6. Load `/health`, `/`, `/leadership`, and `/programmes` over HTTPS.

Do not import this file into a database containing application data. The installer
does not create an administrator account; production administrator provisioning
must use a separate controlled process.
