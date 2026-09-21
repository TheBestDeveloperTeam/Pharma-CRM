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
  <title>422 Validation Error · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-page-code">422</div>
      <h1 class="error-page-title">Validation Error</h1>
      <p class="error-page-text">The submitted data did not pass validation. Please review the form and correct any errors.</p>
      <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
    </div>
  </div>
</body>
</html>
