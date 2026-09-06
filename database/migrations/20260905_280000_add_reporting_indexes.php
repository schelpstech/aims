<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec('ALTER TABLE members ADD KEY idx_members_joined_status_grade (joined_at, status, membership_grade_id)');
        $database->exec('ALTER TABLE programme_applications ADD KEY idx_programme_applications_submitted_programme_status (submitted_at, programme_id, status)');
        $database->exec('ALTER TABLE programme_enrolments ADD KEY idx_programme_enrolments_enrolled_programme_status (enrolled_at, programme_id, status)');
        $database->exec('ALTER TABLE event_registrations ADD KEY idx_event_registrations_registered_event_status (registered_at, event_id, status)');
        $database->exec('ALTER TABLE payments ADD KEY idx_payments_paid_status_currency (paid_at, status, currency)');
        $database->exec('ALTER TABLE invoices ADD KEY idx_invoices_paid_status_currency (paid_at, status, currency)');
        $database->exec('ALTER TABLE invoice_items ADD KEY idx_invoice_items_type_invoice (fee_type, invoice_id)');
    }

    public function down(\PDO $database): void
    {
        $database->exec('ALTER TABLE invoice_items DROP INDEX idx_invoice_items_type_invoice');
        $database->exec('ALTER TABLE invoices DROP INDEX idx_invoices_paid_status_currency');
        $database->exec('ALTER TABLE payments DROP INDEX idx_payments_paid_status_currency');
        $database->exec('ALTER TABLE event_registrations DROP INDEX idx_event_registrations_registered_event_status');
        $database->exec('ALTER TABLE programme_enrolments DROP INDEX idx_programme_enrolments_enrolled_programme_status');
        $database->exec('ALTER TABLE programme_applications DROP INDEX idx_programme_applications_submitted_programme_status');
        $database->exec('ALTER TABLE members DROP INDEX idx_members_joined_status_grade');
    }
};
