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
?>
<section class="panel contact-info-card contact-client-info-card">
    <div class="notify-board-banner">
        <div class="notify-board-banner-copy">
            <h2><?= __e('lawyer.contact.client_info') ?></h2>
            <p><?= __e('lawyer.contact.client_info_help') ?></p>
        </div>
    </div>
    <div class="contact-info-body">
        <dl class="contact-info-list">
            <?php if (!empty($contactClient['company_name'])): ?>
            <div class="contact-info-item">
                <span class="contact-info-icon"><?= contact_info_icon_svg('company') ?></span>
                <div class="contact-info-copy">
                    <dt><?= __e('client.contact.company') ?></dt>
                    <dd><?= e($contactClient['company_name']) ?></dd>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($contactClient['address'])): ?>
            <div class="contact-info-item">
                <span class="contact-info-icon"><?= contact_info_icon_svg('location') ?></span>
                <div class="contact-info-copy">
                    <dt><?= __e('common.address') ?></dt>
                    <dd><?= nl2br(e($contactClient['address'])) ?></dd>
                </div>
            </div>
            <?php endif; ?>
            <div class="contact-info-item">
                <span class="contact-info-icon"><?= contact_info_icon_svg('client') ?></span>
                <div class="contact-info-copy">
                    <dt><?= __e('lawyer.contact.your_client') ?></dt>
                    <dd class="contact-info-lawyer">
                        <span class="contact-info-value"><?= e(full_name($contactClient)) ?></span>
                        <?php if (!empty($contactClient['email'])): ?>
                        <a class="contact-info-meta contact-info-link" href="mailto:<?= e($contactClient['email']) ?>"><?= e($contactClient['email']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($contactClient['phone'])): ?>
                        <a class="contact-info-meta contact-info-link" href="tel:<?= e(preg_replace('/\s+/', '', $contactClient['phone'])) ?>"><?= e($contactClient['phone']) ?></a>
                        <?php endif; ?>
                    </dd>
                </div>
            </div>
            <?php if ($contactClientCase): ?>
            <div class="contact-info-item">
                <span class="contact-info-icon"><?= contact_info_icon_svg('case') ?></span>
                <div class="contact-info-copy">
                    <dt><?= __e('form.related_case') ?></dt>
                    <dd>
                        <span class="contact-info-value"><?= e($contactClientCase['case_number']) ?></span>
                        <?php if (!empty($contactClientCase['title'])): ?>
                        <span class="contact-info-meta"><?= e($contactClientCase['title']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($contactClientCase['status'])): ?>
                        <span class="contact-info-meta"><?= e(translate_status($contactClientCase['status'])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</section>
