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
  <title>429 Too Many Requests · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-page-code">429</div>
      <h1 class="error-page-title">Too Many Requests</h1>
      <p class="error-page-text">You have exceeded the request rate limit. Please wait a moment and try again.</p>
      <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
    </div>
  </div>
</body>
</html>
