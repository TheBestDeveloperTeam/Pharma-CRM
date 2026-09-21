<?php
/**
 * Alert Component
 *
 * @var string $type        info | success | warning | error
 * @var string $message     Alert message text
 * @var bool   $dismissible Whether alert can be closed
 */
$type = $type ?? 'info';
$message = $message ?? '';
$dismissible = $dismissible ?? false;
$cls = $dismissible ? ' alert-dismissible' : '';
?>
<div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES) ?><?= $cls ?>" role="alert">
  <span><?= htmlspecialchars($message, ENT_QUOTES) ?></span>
  <?php if ($dismissible): ?>
  <button type="button" class="alert-close" aria-label="Dismiss">✕</button>
  <?php endif; ?>
</div>
