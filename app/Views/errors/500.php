<?php
/** @var string $theme */
/** @var string $surface */
$theme = $theme ?? 'theme-admin';
$surface = $surface ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>500 Server Error · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-page-code">500</div>
      <h1 class="error-page-title">Internal Server Error</h1>
      <p class="error-page-text">Something went wrong on our end. The error has been logged and we are working to fix it.</p>
      <a href="/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/dashboard" class="btn btn-primary">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
