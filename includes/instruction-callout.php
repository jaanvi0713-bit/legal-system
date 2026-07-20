<?php
/**
 * Primary-coloured instruction panel for forms.
 *
 * Expects: $calloutTitle, $calloutBody
 */
$calloutBody = trim((string) ($calloutBody ?? ''));
if ($calloutBody === '') {
    return;
}
$calloutTitle = (string) ($calloutTitle ?? __('cases.client_instructions'));
?>
<aside class="instruction-callout" role="note">
    <div class="instruction-callout-head">
        <span class="instruction-callout-icon" aria-hidden="true">i</span>
        <strong><?= e($calloutTitle) ?></strong>
    </div>
    <div class="instruction-callout-body"><?= nl2br(e($calloutBody)) ?></div>
</aside>
