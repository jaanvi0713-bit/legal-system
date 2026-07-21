<?php
/**
 * Client profile card for lawyer contact pages.
 *
 * Expects $contactClient (client user row).
 * Optional: $contactClientCase (case row with case_number, title, status).
 */
$contactClient = $contactClient ?? null;
if (!$contactClient) {
    return;
}
$contactClientCase = $contactClientCase ?? null;
$initials = strtoupper(substr((string) ($contactClient['first_name'] ?? 'C'), 0, 1) . substr((string) ($contactClient['last_name'] ?? ''), 0, 1));
if (trim($initials) === '') {
    $initials = 'C';
}
?>
<section class="panel contact-info-card contact-client-info-card">
    <div class="contact-info-head">
        <div class="contact-info-avatar" aria-hidden="true"><?= e($initials) ?></div>
        <div class="contact-info-head-copy">
            <p class="contact-info-kicker"><?= __e('lawyer.contact.your_client') ?></p>
            <h2><?= e(full_name($contactClient)) ?></h2>
            <div class="contact-info-links">
                <?php if (!empty($contactClient['email'])): ?>
                <a class="contact-info-chip" href="mailto:<?= e($contactClient['email']) ?>"><?= e($contactClient['email']) ?></a>
                <?php endif; ?>
                <?php if (!empty($contactClient['phone'])): ?>
                <a class="contact-info-chip" href="tel:<?= e(preg_replace('/\s+/', '', $contactClient['phone'])) ?>"><?= e($contactClient['phone']) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="contact-info-body">
        <?php if (!empty($contactClient['company_name']) || !empty($contactClient['address'])): ?>
        <div class="contact-info-meta-grid">
            <?php if (!empty($contactClient['company_name'])): ?>
            <div class="contact-info-meta-block">
                <span class="contact-info-meta-label"><?= __e('client.contact.company') ?></span>
                <span class="contact-info-meta-value"><?= e($contactClient['company_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($contactClient['address'])): ?>
            <div class="contact-info-meta-block">
                <span class="contact-info-meta-label"><?= __e('common.address') ?></span>
                <span class="contact-info-meta-value"><?= nl2br(e($contactClient['address'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($contactClientCase): ?>
        <div class="contact-related-case">
            <div class="contact-related-case-ico" aria-hidden="true"><?= contact_info_icon_svg('case') ?></div>
            <div class="contact-related-case-copy">
                <span class="contact-info-meta-label"><?= __e('form.related_case') ?></span>
                <strong><?= e($contactClientCase['case_number']) ?></strong>
                <?php if (!empty($contactClientCase['title'])): ?>
                <span class="contact-related-case-title"><?= e($contactClientCase['title']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($contactClientCase['status'])): ?>
            <span class="contact-related-case-status"><?= e(translate_status($contactClientCase['status'])) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
