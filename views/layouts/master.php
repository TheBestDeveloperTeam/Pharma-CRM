<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pharma CRM</title>

  <!-- Custom Core Variables (Themes) -->
  <link rel="stylesheet" href="/assets/css/theme-tokens.css">
  
  <!-- Theme style -->
  <link rel="stylesheet" href="/assets/css/adminlte.min.css">
  
  <!-- Custom Asset System (Icons, Vectors, Utils) -->
  <link rel="stylesheet" href="/assets/css/assets.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  <?php include __DIR__ . '/../components/_navbar.php'; ?>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <?php include __DIR__ . '/../components/_sidebar.php'; ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <?= $content ?? '' ?>
  </div>
  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2026 Pharma CRM.</strong> All rights reserved.
  </footer>

</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="/assets/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="/assets/js/adminlte.min.js"></script>
<!-- Custom app script -->
<script src="/assets/js/app.js"></script>
</body>
</html>
