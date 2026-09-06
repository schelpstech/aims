<?php declare(strict_types=1); ?>
<section class="admin-membership-shell section-space" aria-labelledby="admin-membership-heading">
    <div class="container-wide">
        <div class="membership-page-heading">
            <div>
                <p class="eyebrow">Authorized workspace</p>
                <h1 id="admin-membership-heading">Membership applications</h1>
                <p>Search and filter submitted applications. Draft applications remain private to applicants.</p>
            </div>
            <div class="application-status-card"><span>Total records</span><strong><?= $e($total) ?></strong></div>
        </div>

        <?php if (is_string($message) && $message !== ''): ?><div class="alert alert-success" role="status"><?= $e($message) ?></div><?php endif; ?>
        <?php if (is_string($error) && $error !== ''): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>

        <form class="admin-filter-bar" method="get" action="/admin/membership/applications">
            <label>Search <input class="form-control" name="search" maxlength="100" value="<?= $e($filters['search'] ?? '') ?>" placeholder="Reference, name or email"></label>
            <label>Status
                <select class="form-control" name="status">
                    <option value="">All submitted records</option>
                    <?php foreach (['submitted' => 'Submitted', 'under_review' => 'Under Review', 'query_raised' => 'Query Raised', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"<?= ($filters['status'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Apply filters</button>
        </form>

        <div class="admin-application-table-wrap" role="region" aria-label="Membership applications table" tabindex="0">
            <table class="admin-application-table">
                <thead><tr><th>Applicant</th><th>Reference</th><th>Grade</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                <?php if ($applications === []): ?>
                    <tr><td colspan="6">No matching membership applications.</td></tr>
                <?php endif; ?>
                <?php foreach ($applications as $item): ?>
                    <tr>
                        <td><strong><?= $e(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?></strong><small><?= $e($item['email'] ?? '') ?></small></td>
                        <td><?= $e($item['application_reference'] ?? 'Pending') ?></td>
                        <td><?= $e($item['grade_name'] ?? 'Not selected') ?></td>
                        <td><span class="status-pill"><?= $e(ucwords(str_replace('_', ' ', $item['status'] ?? ''))) ?></span></td>
                        <td><?= $e($item['submitted_at'] ?? '') ?></td>
                        <td><a class="btn btn-outline-navy btn-sm" href="/admin/membership/applications/<?= $e(rawurlencode($item['public_id'] ?? '')) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-pagination">Page <?= $e($pageNumber) ?> of <?= $e($pageCount) ?></p>
    </div>
</section>
