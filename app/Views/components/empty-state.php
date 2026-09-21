<?php
/**
 * Empty State Component
 *
 * @var string $icon    Icon symbol id
 * @var string $title   Heading text
 * @var string $text    Description text
 * @var string $actionLabel  Button label (optional)
 * @var string $actionId     Button id (optional)
 */
$icon = $icon ?? 'search';
$title = $title ?? 'No records found';
$text = $text ?? 'There is nothing here yet.';
$actionLabel = $actionLabel ?? '';
$actionId = $actionId ?? '';
?>
<div class="empty-state">
  <div class="empty-state-icon">
    <svg width="64" height="64"><use href="/assets/img/icons.svg#icon-<?= htmlspecialchars($icon, ENT_QUOTES) ?>"/></svg>
  </div>
  <div class="empty-state-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
  <p class="empty-state-text"><?= htmlspecialchars($text, ENT_QUOTES) ?></p>
  <?php if ($actionLabel): ?>
  <button class="btn btn-primary" id="<?= htmlspecialchars($actionId, ENT_QUOTES) ?>"><?= htmlspecialchars($actionLabel, ENT_QUOTES) ?></button>
  <?php endif; ?>
</div>
