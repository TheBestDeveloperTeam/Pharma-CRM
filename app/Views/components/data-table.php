<?php
/**
 * Data Table Component
 *
 * @var string $tableId     Unique ID for the table
 * @var array  $columns     [{key, label, sortable?, class?}]
 * @var string $searchPlaceholder  Search input placeholder text
 * @var bool   $showSearch  Whether to show search bar (default true)
 */
$tableId = $tableId ?? 'data-table-' . uniqid();
$columns = $columns ?? [];
$searchPlaceholder = $searchPlaceholder ?? 'Search...';
$showSearch = $showSearch ?? true;
?>
<div class="card" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-wrapper">
  <div class="card-header">
    <span><?= htmlspecialchars($tableTitle ?? 'Records', ENT_QUOTES) ?></span>
    <div class="btn-group" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-actions"></div>
  </div>
  <div class="card-body" style="padding: 0;">
    <?php if ($showSearch): ?>
    <div class="dt-toolbar" style="padding: 1rem 1.25rem 0;">
      <div class="dt-search">
        <input type="text" class="form-control" placeholder="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES) ?>"
               id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-search" autocomplete="off">
      </div>
      <div class="dt-filters" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-filters"></div>
    </div>
    <?php endif; ?>
    <div class="table-wrapper">
      <table class="data-table" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>">
        <thead>
          <tr>
            <?php foreach ($columns as $col): ?>
            <th <?= !empty($col['sortable']) ? 'class="sortable" data-sort-key="' . htmlspecialchars($col['key'], ENT_QUOTES) . '"' : '' ?>
                <?= !empty($col['class']) ? 'class="' . htmlspecialchars($col['class'], ENT_QUOTES) . '"' : '' ?>>
              <?= htmlspecialchars($col['label'], ENT_QUOTES) ?>
              <?php if (!empty($col['sortable'])): ?>
              <span class="sort-icon">↕</span>
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-body">
          <!-- Rows populated via JS -->
        </tbody>
      </table>
    </div>
    <div id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-empty" class="empty-state hidden">
      <div class="empty-state-title">No records found</div>
      <p class="empty-state-text">There are no records matching your criteria.</p>
    </div>
  </div>
  <div class="card-footer">
    <div class="pagination" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-pagination">
      <span class="pagination-info" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-info"></span>
      <div class="pagination-controls" id="<?= htmlspecialchars($tableId, ENT_QUOTES) ?>-pages"></div>
    </div>
  </div>
</div>
