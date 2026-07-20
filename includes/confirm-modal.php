<div class="lexora-confirm-modal" id="lexoraConfirmModal" hidden aria-hidden="true">
    <div class="lexora-confirm-backdrop" data-confirm-dismiss tabindex="-1"></div>
    <div class="lexora-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="lexoraConfirmTitle" aria-describedby="lexoraConfirmMessage" tabindex="-1">
        <div class="lexora-confirm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 9v4m0 4h.01"/>
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            </svg>
        </div>
        <h2 class="lexora-confirm-title" id="lexoraConfirmTitle"><?= __e('confirm.title') ?></h2>
        <p class="lexora-confirm-message" id="lexoraConfirmMessage"></p>
        <div class="lexora-confirm-actions">
            <button type="button" class="btn btn-secondary" id="lexoraConfirmCancel" data-confirm-dismiss><?= __e('common.cancel') ?></button>
            <button type="button" class="btn btn-primary lexora-confirm-accept" id="lexoraConfirmAccept" data-confirm-accept><?= __e('confirm.proceed') ?></button>
        </div>
    </div>
</div>
