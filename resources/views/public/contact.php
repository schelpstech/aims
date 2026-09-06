<?php

declare(strict_types=1);

echo $view->render('components.page-hero', compact('page', 'e'));

$contactRows = array_filter([
    'Official email' => $site['official_email'] ?? '',
    'Membership email' => $site['membership_email'] ?? '',
    'Phone' => $site['phone'] ?? '',
    'WhatsApp' => $site['whatsapp'] ?? '',
    'Address' => $site['address'] ?? '',
]);
?>
<section class="section">
    <div class="container-wide">
        <div class="contact-grid">
            <div class="contact-details">
                <p class="eyebrow">Official channels</p>
                <h2>Contact information you can rely on.</h2>
                <?php if ($contactRows !== []): ?>
                    <dl class="contact-list">
                        <?php foreach ($contactRows as $label => $value): ?>
                            <div>
                                <dt><?= $e($label) ?></dt>
                                <dd><?= $e($value) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <div class="contact-placeholder" role="status">
                        <span class="status-pill">Verification pending</span>
                        <p>Official email, telephone, WhatsApp and office address details have not yet been supplied. They will appear here from verified organisation settings.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-card">
                <p class="eyebrow">Online enquiry</p>
                <h2>Enquiry form coming soon</h2>
                <p class="form-intro">The form is visible as an accessible interface preview but remains disabled until a verified delivery channel and privacy workflow are configured.</p>
                <form aria-describedby="contact-form-status">
                    <fieldset disabled>
                        <legend class="visually-hidden">Contact enquiry details</legend>
                        <div class="form-row">
                            <div>
                                <label for="contact-name">Full name</label>
                                <input class="form-control" id="contact-name" name="name" type="text" autocomplete="name">
                            </div>
                            <div>
                                <label for="contact-email">Email address</label>
                                <input class="form-control" id="contact-email" name="email" type="email" autocomplete="email">
                            </div>
                        </div>
                        <div>
                            <label for="contact-subject">Subject</label>
                            <input class="form-control" id="contact-subject" name="subject" type="text">
                        </div>
                        <div>
                            <label for="contact-message">Message</label>
                            <textarea class="form-control" id="contact-message" name="message" rows="5"></textarea>
                        </div>
                        <button class="btn btn-navy" type="submit">Send Enquiry</button>
                    </fieldset>
                    <p class="form-status" id="contact-form-status">Online submissions are not active. No personal information is being collected by this form.</p>
                </form>
            </div>
        </div>
    </div>
</section>

