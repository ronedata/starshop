<?php require_once __DIR__.'/auth.php'; ?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?php echo APP_NAME; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{overflow-x:hidden}
    .layout{display:grid;grid-template-columns:240px 1fr;min-height:100vh}
    .sidebar{background:#0f172a;color:#fff;position:sticky;top:0;height:100vh;padding:16px}
    .sidebar a{color:#cbd5e1;text-decoration:none;display:block;padding:8px 12px;border-radius:8px;margin-bottom:6px}
    .sidebar a.active,.sidebar a:hover{background:#1e293b;color:#fff}
    .brand{color:#fff;font-weight:700;display:block;margin-bottom:12px}
    @media(max-width:992px){.layout{grid-template-columns:1fr}.sidebar{position:fixed;left:-260px;width:240px;transition:all .25s}.sidebar.show{left:0}.content{padding-top:56px}}
    .topbar{background:#0f172a;color:#fff;height:56px;display:flex;align-items:center;padding:0 12px}
    .topbar button{border:0;background:transparent;color:#fff}
    .thumb{width:64px;height:64px;object-fit:cover;border:1px solid #e5e7eb;border-radius:6px}
  </style>
</head>
<body>
<div class="topbar d-lg-none">
  <button onclick="document.querySelector('.sidebar').classList.toggle('show')">☰</button>
  <div class="ms-2 fw-semibold">Admin Panel</div>
  <div class="ms-auto small">Hi, <?php echo h($_SESSION['admin_username'] ?? ''); ?></div>
</div>
<div class="layout">
  <aside class="sidebar d-none d-lg-block">
    <a class="brand" href="<?php echo BASE_URL; ?>/admin/index.php">⭐ <?php echo APP_NAME; ?></a>
    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='index.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/index.php">ড্যাশবোর্ড</a>
    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='products.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/products.php">পণ্য ব্যবস্থাপনা</a>
    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='categories.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/categories.php">ক্যাটেগরি</a>
    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='orders.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/orders.php">অর্ডার ব্যবস্থাপনা</a>
    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='users.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/users.php">অ্যাডমিন ইউজার</a>
	<a href="stock_report.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='stock_report.php'?'active':''; ?>">
  স্টক রিপোর্ট
</a>

    <a class="<?php echo basename($_SERVER['PHP_SELF'])==='settings.php'?'active':''; ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">সেটিংস</a>
    <hr><a href="<?php echo BASE_URL; ?>/admin/logout.php">লগআউট</a>
  </aside>
  <main class="content p-3 p-lg-4">
