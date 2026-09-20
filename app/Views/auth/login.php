<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
  <div class="login-box">
    <div class="login-logo"><?= htmlspecialchars($title, ENT_QUOTES) ?></div>
    
    <div id="error-message" class="toast toast-error" style="display: none; margin-bottom: 1rem;"></div>

    <form id="login-form">
      <?php if (!empty($frnRequired)): ?>
      <div class="form-group">
        <label class="form-label" for="franchise_code">Franchise Code</label>
        <input class="form-control" type="text" id="franchise_code" name="franchise_code" required autofocus placeholder="e.g. MUMBAI">
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email" required placeholder="name@company.com" <?= empty($frnRequired) ? 'autofocus' : '' ?>>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required placeholder="••••••••">
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;" id="login-btn">
        Sign In
      </button>
    </form>
  </div>

  <div id="toasts" aria-live="polite"></div>

  <script type="module">
    import { api, tokens, toast } from '/assets/js/crm-ui.js';

    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');
    const errBox = document.getElementById('error-message');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      btn.disabled = true;
      btn.textContent = 'Authenticating...';
      errBox.style.display = 'none';

      const payload = {
        grant_type: 'password',
        client_id: '<?= htmlspecialchars($clientId, ENT_QUOTES) ?>',
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
      };

      const frnInput = document.getElementById('franchise_code');
      if (frnInput) {
        payload.franchise_code = frnInput.value;
      }

      try {
        const res = await api('POST', '/oauth/token', payload);
        if (res && res.status === 200 && res.data.success) {
          tokens.setAccess(res.data.data.access_token);
          tokens.setRefresh(res.data.data.refresh_token);
          window.location.href = '/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/dashboard';
        } else {
          const msg = (res && res.data && res.data.error) ? res.data.error.message : 'Login failed';
          errBox.textContent = msg;
          errBox.style.display = 'block';
        }
      } catch (err) {
        errBox.textContent = err.message || 'An error occurred';
        errBox.style.display = 'block';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Sign In';
      }
    });
  </script>
</body>
</html>
