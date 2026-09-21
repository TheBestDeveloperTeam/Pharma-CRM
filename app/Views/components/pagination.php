<?php
/**
 * Pagination Component
 *
 * @var string $paginationId   Unique ID prefix
 */
$paginationId = $paginationId ?? 'pg-' . uniqid();
?>
<div class="pagination" id="<?= htmlspecialchars($paginationId, ENT_QUOTES) ?>">
  <span class="pagination-info" id="<?= htmlspecialchars($paginationId, ENT_QUOTES) ?>-info">
    <!-- e.g. "Showing 1–20 of 156" populated by JS -->
  </span>
  <div class="pagination-controls" id="<?= htmlspecialchars($paginationId, ENT_QUOTES) ?>-controls">
    <!-- Page buttons populated by JS -->
  </div>
</div>
