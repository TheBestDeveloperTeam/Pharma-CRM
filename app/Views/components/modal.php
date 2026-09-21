<?php
/**
 * Modal Dialog Component
 *
 * @var string $modalId   Unique ID for the modal
 * @var string $title     Modal title
 * @var string $size      'sm' | '' | 'lg' | 'xl'
 */
$modalId = $modalId ?? 'modal-' . uniqid();
$title = $title ?? 'Dialog';
$sizeClass = match ($size ?? '') {
    'lg' => ' modal-lg',
    'xl' => ' modal-xl',
    default => '',
};
?>
<div class="modal-overlay" id="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>-title">
  <div class="modal-dialog<?= $sizeClass ?>">
    <div class="modal-header">
      <h3 class="modal-title" id="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h3>
      <button type="button" class="modal-close" data-modal-close="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>" aria-label="Close">✕</button>
    </div>
    <div class="modal-body" id="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>-body">
      <!-- Content populated via JS or included inline -->
    </div>
    <div class="modal-footer" id="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>-footer">
      <button type="button" class="btn btn-ghost" data-modal-close="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>">Cancel</button>
      <button type="button" class="btn btn-primary" id="<?= htmlspecialchars($modalId, ENT_QUOTES) ?>-confirm">Confirm</button>
    </div>
  </div>
</div>
