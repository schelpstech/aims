<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $roleStatement = $database->prepare(<<<'SQL'
            INSERT INTO roles (name, slug, description, is_system, active)
            VALUES (:name, :slug, :description, 1, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                is_system = 1
            SQL);

        $roles = [
            ['name' => 'Super Administrator', 'slug' => 'super-administrator', 'description' => 'Full platform administration.'],
            ['name' => 'Membership Administrator', 'slug' => 'membership-administrator', 'description' => 'Manages membership operations.'],
            ['name' => 'Membership Reviewer', 'slug' => 'membership-reviewer', 'description' => 'Reviews membership applications.'],
            ['name' => 'Programme Administrator', 'slug' => 'programme-administrator', 'description' => 'Manages professional programmes.'],
            ['name' => 'Programme Reviewer', 'slug' => 'programme-reviewer', 'description' => 'Reviews submitted programme applications without decision or enrolment authority.'],
            ['name' => 'Finance Officer', 'slug' => 'finance-officer', 'description' => 'Manages financial operations.'],
            ['name' => 'Event Administrator', 'slug' => 'event-administrator', 'description' => 'Manages events.'],
            ['name' => 'Certificate Officer', 'slug' => 'certificate-officer', 'description' => 'Manages certificate issuance and revocation.'],
            ['name' => 'Content Manager', 'slug' => 'content-manager', 'description' => 'Manages website and resource content.'],
            ['name' => 'Management Viewer', 'slug' => 'management-viewer', 'description' => 'Read-only management reporting access.'],
            ['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read-only audit and security review access.'],
        ];

        foreach ($roles as $role) {
            $roleStatement->execute($role);
        }

        $permissionStatement = $database->prepare(<<<'SQL'
            INSERT INTO permissions (name, module, description)
            VALUES (:name, :module, :description)
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                description = VALUES(description)
            SQL);

        $permissions = [
            ['member.view', 'member', 'View member records.'],
            ['member.create', 'member', 'Create member records.'],
            ['member.update', 'member', 'Update member records.'],
            ['member.review', 'member', 'Review membership applications and record internal comments.'],
            ['member.approve', 'member', 'Approve membership applications.'],
            ['member.reject', 'member', 'Reject membership applications.'],
            ['member.query', 'member', 'Raise applicant-visible membership application queries.'],
            ['member.suspend', 'member', 'Suspend memberships.'],
            ['member.renewal_override', 'member', 'Override membership renewal status with an audited reason.'],
            ['member.renewal_policy', 'member', 'Configure membership renewal periods, windows and fees.'],
            ['programme.view', 'programme', 'View programmes and applications.'],
            ['programme.create', 'programme', 'Create programmes.'],
            ['programme.update', 'programme', 'Update programmes.'],
            ['programme.delete', 'programme', 'Archive or remove unpublished programmes and professional areas.'],
            ['programme.assign_coordinators', 'programme', 'Assign confirmed programme coordinators to programmes and professional areas.'],
            ['programme.application_view', 'programme', 'View submitted programme applications.'],
            ['programme.application_review', 'programme', 'Move submitted programme applications into review.'],
            ['programme.application_approve', 'programme', 'Approve programme applications.'],
            ['programme.application_reject', 'programme', 'Reject programme applications with a retained reason.'],
            ['programme.enrol', 'programme', 'Create and manage programme enrolments.'],
            ['payment.view', 'payment', 'View financial transactions.'],
            ['payment.verify', 'payment', 'Verify payment transactions.'],
            ['payment.refund', 'payment', 'Authorize payment refunds.'],
            ['certificate.issue', 'certificate', 'Issue certificates.'],
            ['certificate.revoke', 'certificate', 'Revoke certificates.'],
            ['certificate.type_manage', 'certificate', 'Configure certificate types and validity policies.'],
            ['event.manage', 'event', 'Manage events and registrations.'],
            ['cms.edit', 'cms', 'Edit content.'],
            ['cms.publish', 'cms', 'Publish content.'],
            ['report.view', 'report', 'View reports.'],
            ['report.export', 'report', 'Export reports.'],
            ['report.finance', 'report', 'View and export restricted financial reports.'],
            ['admin.manage_users', 'admin', 'Manage administrative users.'],
            ['admin.manage_roles', 'admin', 'Manage roles and permissions.'],
            ['system.settings.view', 'system', 'View system settings.'],
            ['system.settings.update', 'system', 'Update system settings.'],
            ['audit.view', 'audit', 'View the audit trail.'],
            ['security.events.view', 'security', 'View security events.'],
            ['security.events.resolve', 'security', 'Resolve security events.'],
        ];

        foreach ($permissions as [$name, $module, $description]) {
            $permissionStatement->execute(compact('name', 'module', 'description'));
        }

        $database->exec(<<<'SQL'
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT roles.id, permissions.id
            FROM roles
            CROSS JOIN permissions
            WHERE roles.slug = 'super-administrator'
            SQL);

        $database->exec(<<<'SQL'
            DELETE role_permissions
            FROM role_permissions
            INNER JOIN roles ON roles.id = role_permissions.role_id
            INNER JOIN permissions ON permissions.id = role_permissions.permission_id
            WHERE roles.slug = 'membership-reviewer'
              AND permissions.name IN ('member.approve', 'member.reject')
            SQL);

        $assignments = [
            'membership-administrator' => ['member.view', 'member.create', 'member.update', 'member.review', 'member.approve', 'member.reject', 'member.query', 'member.suspend', 'member.renewal_override', 'member.renewal_policy'],
            'membership-reviewer' => ['member.view', 'member.review', 'member.query'],
            'programme-administrator' => ['programme.view', 'programme.create', 'programme.update', 'programme.delete', 'programme.assign_coordinators', 'programme.application_view', 'programme.application_review', 'programme.application_approve', 'programme.application_reject', 'programme.enrol'],
            'programme-reviewer' => ['programme.view', 'programme.application_view', 'programme.application_review'],
            'finance-officer' => ['payment.view', 'payment.verify', 'payment.refund', 'report.view', 'report.export', 'report.finance'],
            'event-administrator' => ['event.manage'],
            'certificate-officer' => ['certificate.issue', 'certificate.revoke', 'certificate.type_manage'],
            'content-manager' => ['cms.edit', 'cms.publish'],
            'management-viewer' => ['member.view', 'programme.view', 'payment.view', 'report.view'],
            'auditor' => ['member.view', 'programme.view', 'payment.view', 'report.view', 'report.finance', 'audit.view', 'security.events.view'],
        ];

        $assignmentStatement = $database->prepare(<<<'SQL'
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT roles.id, permissions.id
            FROM roles
            INNER JOIN permissions ON permissions.name = :permission
            WHERE roles.slug = :role
            SQL);

        foreach ($assignments as $role => $permissionNames) {
            foreach ($permissionNames as $permission) {
                $assignmentStatement->execute(['role' => $role, 'permission' => $permission]);
            }
        }
    }
};
