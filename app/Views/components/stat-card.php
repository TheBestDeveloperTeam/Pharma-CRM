<?php
/**
 * Stat Card Component
 *
 * @var string $value   The displayed value
 * @var string $label   Label under value
 * @var string $icon    Icon symbol id (e.g. 'orders')
 * @var string $color   bg-primary | bg-success | bg-warning | bg-danger | bg-info
 * @var string $trend   e.g. '+12%' or '-3%'
 * @var string $trendDir  'up' | 'down'
 */
$value = $value ?? '0';
$label = $label ?? 'Metric';
$icon = $icon ?? 'chart';
$color = $color ?? 'bg-primary';
$trend = $trend ?? '';
$trendDir = $trendDir ?? '';
?>
<div class="stat-card">
  <div>
    <div class="stat-value"><?= htmlspecialchars($value, ENT_QUOTES) ?></div>
    <div class="stat-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></div>
    <?php if ($trend): ?>
    <div class="stat-trend <?= htmlspecialchars($trendDir, ENT_QUOTES) ?>">
      <?= $trendDir === 'up' ? '↑' : '↓' ?> <?= htmlspecialchars($trend, ENT_QUOTES) ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="stat-icon <?= htmlspecialchars($color, ENT_QUOTES) ?>">
    <svg width="24" height="24"><use href="/assets/img/icons.svg#icon-<?= htmlspecialchars($icon, ENT_QUOTES) ?>"/></svg>
  </div>
</div>
