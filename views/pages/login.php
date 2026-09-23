<?php
use App\Helpers\AssetHelper;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pharma CRM | Log in</title>
  
  <link rel="stylesheet" href="/assets/css/theme-tokens.css">
  <link rel="stylesheet" href="/assets/css/adminlte.min.css">
  <link rel="stylesheet" href="/assets/css/assets.css">
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    .login-box {
      width: 400px;
    }
    .lottie-container {
      width: 150px;
      height: 150px;
      margin: 0 auto;
    }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Pharma</b>CRM</a>
  </div>
  <div class="card">
    <div class="card-body login-card-body text-center">
      <div class="lottie-container mb-3" data-lottie="/assets/motion/lottie/hero-aurora-loop.json" data-loop="true" data-autoplay="true"></div>
      
      <p class="login-box-msg">Sign in to start your session</p>
      
      <form id="loginForm">
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email" required>
          <div class="input-group-append">
            <div class="input-group-text">
                <?= AssetHelper::icon('icon-user', 'text-muted') ?>
            </div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append">
            <div class="input-group-text">
                <?= AssetHelper::icon('icon-lock', 'text-muted') ?>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/assets/js/assets.js"></script>
<script src="/assets/js/app.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = 'Signing in...';
    
    try {
        const email = e.target.email.value;
        const password = e.target.password.value;
        const success = await AppClient.login(email, password);
        
        if (success) {
            window.location.href = '/admin/dashboard';
        } else {
            alert('Invalid credentials');
            btn.disabled = false;
            btn.innerHTML = 'Sign In';
        }
    } catch(err) {
        alert('Login failed');
        btn.disabled = false;
        btn.innerHTML = 'Sign In';
    }
});
</script>
</body>
</html>
