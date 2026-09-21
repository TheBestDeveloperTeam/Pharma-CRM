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
  <title>404 Not Found · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-page-code">404</div>
      <h1 class="error-page-title">Page Not Found</h1>
      <p class="error-page-text">The page you are looking for does not exist or has been moved.</p>
      <a href="/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/dashboard" class="btn btn-primary">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
