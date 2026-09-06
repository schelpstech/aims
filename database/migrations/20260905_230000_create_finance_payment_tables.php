<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE fee_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                name VARCHAR(180) NOT NULL,
                fee_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                amount_minor BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_fee_settings_public_id (public_id), UNIQUE KEY uq_fee_settings_code (code),
                KEY idx_fee_settings_type_active (fee_type,active,deleted_at), KEY idx_fee_settings_creator (created_by_user_id),
                CONSTRAINT fk_fee_settings_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_fee_settings_type CHECK (fee_type IN ('membership_application','membership_renewal','programme_application','programme_tuition','event_registration','other'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE invoices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                invoice_number VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                bill_to_name VARCHAR(200) NOT NULL,
                bill_to_email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
                source_type VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
                source_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
                currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                subtotal_minor BIGINT UNSIGNED NOT NULL,
                total_minor BIGINT UNSIGNED NOT NULL,
                amount_paid_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                due_at DATETIME(6) NULL,
                paid_at DATETIME(6) NULL,
                cancelled_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_invoices_public_id (public_id), UNIQUE KEY uq_invoices_number (invoice_number),
                UNIQUE KEY uq_invoices_source (source_type,source_public_id), KEY idx_invoices_user_status (user_id,status,created_at), KEY idx_invoices_status_due (status,due_at),
                CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT chk_invoices_status CHECK (status IN ('pending','paid','cancelled','expired','refunded')),
                CONSTRAINT chk_invoices_totals CHECK (subtotal_minor=total_minor AND amount_paid_minor<=total_minor)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE invoice_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id BIGINT UNSIGNED NOT NULL,
                fee_setting_id BIGINT UNSIGNED NULL,
                fee_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                unit_amount_minor BIGINT UNSIGNED NOT NULL,
                line_total_minor BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), KEY idx_invoice_items_invoice (invoice_id,id), KEY idx_invoice_items_fee_setting (fee_setting_id),
                CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
                CONSTRAINT fk_invoice_items_fee_setting FOREIGN KEY (fee_setting_id) REFERENCES fee_settings(id) ON DELETE SET NULL,
                CONSTRAINT chk_invoice_items_type CHECK (fee_type IN ('membership_application','membership_renewal','programme_application','programme_tuition','event_registration','other')),
                CONSTRAINT chk_invoice_items_total CHECK (quantity>0 AND line_total_minor=quantity*unit_amount_minor)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                invoice_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                gateway VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                gateway_reference VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                amount_minor BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'initiated',
                verified_at DATETIME(6) NULL,
                paid_at DATETIME(6) NULL,
                failure_reason VARCHAR(255) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_payments_public_id (public_id), UNIQUE KEY uq_payments_gateway_reference (gateway,gateway_reference),
                KEY idx_payments_invoice_status (invoice_id,status), KEY idx_payments_user_created (user_id,created_at),
                CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
                CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT chk_payments_status CHECK (status IN ('initiated','pending','successful','failed','reversed','refunded'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE payment_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                payment_id BIGINT UNSIGNED NULL,
                gateway VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                gateway_reference VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NULL,
                provider_event_id VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
                transaction_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                provider_status VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
                amount_minor BIGINT UNSIGNED NULL,
                currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NULL,
                response_payload JSON NULL,
                response_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                processed TINYINT(1) NOT NULL DEFAULT 0,
                processed_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_payment_transactions_public_id (public_id),
                UNIQUE KEY uq_payment_transactions_event (gateway,provider_event_id),
                KEY idx_payment_transactions_reference (gateway,gateway_reference,created_at), KEY idx_payment_transactions_payment (payment_id,created_at),
                CONSTRAINT fk_payment_transactions_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
                CONSTRAINT chk_payment_transactions_type CHECK (transaction_type IN ('initialize','verify','webhook','refund','manual'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE idempotency_keys (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                scope VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'processing',
                resource_type VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
                resource_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
                response_status SMALLINT UNSIGNED NULL,
                response_body JSON NULL,
                expires_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_idempotency_scope_key (scope,key_hash), KEY idx_idempotency_expiry (expires_at),
                CONSTRAINT chk_idempotency_status CHECK (status IN ('processing','completed','failed'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
    public function down(\PDO $database): void { $database->exec('DROP TABLE idempotency_keys');$database->exec('DROP TABLE payment_transactions');$database->exec('DROP TABLE payments');$database->exec('DROP TABLE invoice_items');$database->exec('DROP TABLE invoices');$database->exec('DROP TABLE fee_settings'); }
};
