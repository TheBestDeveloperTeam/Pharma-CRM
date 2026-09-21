<?php
/**
 * Form Group Component
 *
 * @var string $name       Field name
 * @var string $label      Label text
 * @var string $type       input type (text, email, number, password, select, textarea)
 * @var bool   $required   Whether field is required
 * @var string $value      Current value
 * @var string $hint       Help text
 * @var string $error      Error message
 * @var array  $options    For select: [{value, label}]
 * @var string $placeholder
 */
$name = $name ?? '';
$label = $label ?? '';
$type = $type ?? 'text';
$required = $required ?? false;
$value = $value ?? '';
$hint = $hint ?? '';
$error = $error ?? '';
$options = $options ?? [];
$placeholder = $placeholder ?? '';
$inputId = 'field-' . htmlspecialchars($name, ENT_QUOTES);
$invalidClass = $error ? ' is-invalid' : '';
?>
<div class="form-group">
  <label class="form-label" for="<?= $inputId ?>">
    <?= htmlspecialchars($label, ENT_QUOTES) ?>
    <?php if ($required): ?><span class="required">*</span><?php endif; ?>
  </label>
  <?php if ($type === 'select'): ?>
  <select class="form-control<?= $invalidClass ?>" id="<?= $inputId ?>" name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
          <?= $required ? 'required' : '' ?>>
    <option value=""><?= htmlspecialchars($placeholder ?: '— Select —', ENT_QUOTES) ?></option>
    <?php foreach ($options as $opt): ?>
    <option value="<?= htmlspecialchars($opt['value'] ?? '', ENT_QUOTES) ?>"
            <?= ($opt['value'] ?? '') === $value ? 'selected' : '' ?>>
      <?= htmlspecialchars($opt['label'] ?? $opt['value'] ?? '', ENT_QUOTES) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <?php elseif ($type === 'textarea'): ?>
  <textarea class="form-control<?= $invalidClass ?>" id="<?= $inputId ?>" name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
            placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES) ?>"
            <?= $required ? 'required' : '' ?>><?= htmlspecialchars($value, ENT_QUOTES) ?></textarea>
  <?php else: ?>
  <input type="<?= htmlspecialchars($type, ENT_QUOTES) ?>" class="form-control<?= $invalidClass ?>"
         id="<?= $inputId ?>" name="<?= htmlspecialchars($name, ENT_QUOTES) ?>"
         value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"
         placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES) ?>"
         <?= $required ? 'required' : '' ?>>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="form-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
  <?php elseif ($hint): ?>
  <div class="form-hint"><?= htmlspecialchars($hint, ENT_QUOTES) ?></div>
  <?php endif; ?>
</div>
