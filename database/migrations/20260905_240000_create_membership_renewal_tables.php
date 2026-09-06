<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE membership_renewal_policies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                membership_grade_id BIGINT UNSIGNED NOT NULL,
                fee_setting_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(180) NOT NULL,
                period_value SMALLINT UNSIGNED NOT NULL,
                period_unit VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                start_rule VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                renewal_window_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                effective_from DATE NOT NULL,
                effective_until DATE NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_membership_renewal_policies_public_id (public_id),
                KEY idx_renewal_policies_grade_effective (membership_grade_id,active,effective_from,effective_until),
                KEY idx_renewal_policies_fee (fee_setting_id),
                CONSTRAINT fk_renewal_policies_grade FOREIGN KEY (membership_grade_id) REFERENCES membership_grades(id) ON DELETE RESTRICT,
                CONSTRAINT fk_renewal_policies_fee FOREIGN KEY (fee_setting_id) REFERENCES fee_settings(id) ON DELETE RESTRICT,
                CONSTRAINT fk_renewal_policies_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_renewal_policy_period CHECK (period_value>0 AND period_unit IN ('days','months','years')),
                CONSTRAINT chk_renewal_policy_start CHECK (start_rule IN ('current_expiry','payment_date')),
                CONSTRAINT chk_renewal_policy_dates CHECK (effective_until IS NULL OR effective_until>=effective_from)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE membership_renewals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                member_id BIGINT UNSIGNED NOT NULL,
                renewal_policy_id BIGINT UNSIGNED NOT NULL,
                invoice_id BIGINT UNSIGNED NULL,
                renewal_reference VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'renewal_due',
                previous_expiry_date DATE NULL,
                renewed_from_date DATE NULL,
                renewed_until_date DATE NULL,
                period_value SMALLINT UNSIGNED NOT NULL,
                period_unit VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                start_rule VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                fee_amount_minor BIGINT UNSIGNED NOT NULL,
                fee_currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                due_at DATETIME(6) NULL,
                paid_at DATETIME(6) NULL,
                renewed_at DATETIME(6) NULL,
                expired_at DATETIME(6) NULL,
                override_by_user_id BIGINT UNSIGNED NULL,
                override_reason VARCHAR(500) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), UNIQUE KEY uq_membership_renewals_public_id (public_id),
                UNIQUE KEY uq_membership_renewals_reference (renewal_reference), UNIQUE KEY uq_membership_renewals_invoice (invoice_id),
                KEY idx_membership_renewals_member_status (member_id,status,created_at), KEY idx_membership_renewals_due (status,due_at),
                CONSTRAINT fk_membership_renewals_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_renewals_policy FOREIGN KEY (renewal_policy_id) REFERENCES membership_renewal_policies(id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_renewals_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_renewals_override FOREIGN KEY (override_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT chk_membership_renewals_status CHECK (status IN ('renewal_due','invoice_generated','payment_pending','paid','renewed','expired')),
                CONSTRAINT chk_membership_renewals_period CHECK (period_value>0 AND period_unit IN ('days','months','years')),
                CONSTRAINT chk_membership_renewals_start CHECK (start_rule IN ('current_expiry','payment_date')),
                CONSTRAINT chk_membership_renewals_override CHECK ((override_by_user_id IS NULL AND override_reason IS NULL) OR (override_by_user_id IS NOT NULL AND override_reason IS NOT NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $database->exec(<<<'SQL'
            CREATE TABLE membership_renewal_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                membership_renewal_id BIGINT UNSIGNED NOT NULL,
                from_status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
                to_status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                transition_source VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                note VARCHAR(500) NULL,
                changed_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id), KEY idx_renewal_history_renewal_created (membership_renewal_id,created_at,id),
                KEY idx_renewal_history_actor (changed_by_user_id,created_at),
                CONSTRAINT fk_renewal_history_renewal FOREIGN KEY (membership_renewal_id) REFERENCES membership_renewals(id) ON DELETE RESTRICT,
                CONSTRAINT fk_renewal_history_actor FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT chk_renewal_history_source CHECK (transition_source IN ('member','verified_payment','scheduler','admin_override')),
                CONSTRAINT chk_renewal_history_to CHECK (to_status IN ('renewal_due','invoice_generated','payment_pending','paid','renewed','expired'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE membership_renewal_history');
        $database->exec('DROP TABLE membership_renewals');
        $database->exec('DROP TABLE membership_renewal_policies');
    }
};
