<?php

declare(strict_types=1);

use App\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec(<<<'SQL'
            CREATE TABLE membership_review_comments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                membership_application_id BIGINT UNSIGNED NOT NULL,
                author_user_id BIGINT UNSIGNED NOT NULL,
                comment_type VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'review',
                body TEXT NOT NULL,
                visible_to_applicant TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                PRIMARY KEY (id),
                UNIQUE KEY uq_membership_review_comments_public_id (public_id),
                KEY idx_membership_review_comments_application_created (membership_application_id, created_at, id),
                KEY idx_membership_review_comments_author_created (author_user_id, created_at),
                CONSTRAINT fk_membership_review_comments_application
                    FOREIGN KEY (membership_application_id) REFERENCES membership_applications (id) ON DELETE RESTRICT,
                CONSTRAINT fk_membership_review_comments_author
                    FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE RESTRICT,
                CONSTRAINT chk_membership_review_comments_type
                    CHECK (comment_type IN ('review', 'query', 'approval', 'rejection'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE membership_review_comments');
    }
};
