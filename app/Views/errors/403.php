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
  <title>403 Forbidden · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-page-code">403</div>
      <h1 class="error-page-title">Access Denied</h1>
      <p class="error-page-text">You do not have permission to access this resource. Contact your administrator if you believe this is an error.</p>
      <a href="/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/dashboard" class="btn btn-primary">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
