<?php
/**
 * Badge / Status Pill Component
 *
 * @var string $text    Badge text
 * @var string $type    success | warning | danger | info | neutral | primary
 */
$text = $text ?? '';
$type = $type ?? 'neutral';
?>
<span class="badge badge-<?= htmlspecialchars($type, ENT_QUOTES) ?>"><?= htmlspecialchars($text, ENT_QUOTES) ?></span>
