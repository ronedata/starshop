<?php
// admin/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Fallback esc helper (যদি গ্লোবালি সংজ্ঞায়িত না থাকে) */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* Active class helper */
$SELF = basename($_SERVER['PHP_SELF']);
function is_active($files){
  $self = basename($_SERVER['PHP_SELF']);
  if (is_array($files)) { return in_array($self, $files, true) ? 'active' : ''; }
  return $self === $files ? 'active' : '';
}
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?php echo h(defined('APP_NAME') ? APP_NAME : 'Shop'); ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (CDN) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --sidebar-bg:#0f172a;
      --sidebar-hover:#1e293b;
      --sidebar-text:#cbd5e1;
      --sidebar-active:#ffffff;
    }
    body{overflow-x:hidden;background:#f6f8fb}
    .layout{
      display:grid; grid-template-columns:240px 1fr; min-height:100vh;
    }
    /* Topbar (mobile) */
    .topbar{
      background:var(--sidebar-bg); color:#fff; height:56px;
      display:flex; align-items:center; padding:0 12px;
    }
    .topbar .btn-nav{
      border:0; background:transparent; color:#fff; font-size:1.25rem;
    }

    /* Sidebar */
    .sidebar{
      background:var(--sidebar-bg); color:#fff; position:sticky; top:0; height:100vh; padding:16px;
    }
    .brand{
      color:#fff; font-weight:700; display:flex; align-items:center; gap:8px;
      text-decoration:none; margin-bottom:14px;
    }
    .brand i{color:#60a5fa}

    .navy a{
      color:var(--sidebar-text); text-decoration:none; display:flex; align-items:center; gap:10px;
      padding:10px 12px; border-radius:10px; margin-bottom:6px; font-weight:600;
    }
    .navy a i{ width:1.25em; text-align:center; }
    .navy a:hover{ background:var(--sidebar-hover); color:#fff; }
    .navy a.active{ background:var(--sidebar-hover); color:var(--sidebar-active); }

    /* Mobile: sidebar becomes offcanvas-like */
    @media(max-width:992px){
      .layout{ grid-template-columns:1fr; }
      .sidebar{
        position:fixed; left:-260px; top:56px; height:calc(100vh - 56px);
        width:240px; z-index:1040; transition:left .25s ease; border-right:1px solid rgba(255,255,255,.08);
      }
      .sidebar.show{ left:0; }
      .content{ padding-top:56px; }
      .backdrop{
        content:""; position:fixed; inset:56px 0 0 0; background:rgba(0,0,0,.35);
        display:none; z-index:1039;
      }
      .backdrop.show{ display:block; }
    }

    /* Utilities */
    .thumb{width:64px;height:64px;object-fit:cover;border:1px solid #e5e7eb;border-radius:6px;background:#fff}
  </style>
</head>
<body>

<!-- Topbar (mobile) -->
<div class="topbar d-lg-none">
  <button class="btn-nav" id="btnSidebarToggle" aria-label="Toggle menu">
    <i class="fa-solid fa-bars"></i>
  </button>
  <div class="ms-2 fw-semibold d-flex align-items-center gap-2">
    <i class="fa-solid fa-gauge-high"></i>
    <span>Admin Panel</span>
  </div>
  <div class="ms-auto small d-flex align-items-center gap-2">
    <i class="fa-regular fa-user"></i>
    <span><?php echo h($_SESSION['admin_username'] ?? ''); ?></span>
  </div>
</div>

<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <a class="brand" href="<?php echo BASE_URL; ?>/admin/index.php">
      <i class="fa-solid fa-star"></i>
      <span><?php echo h(defined('APP_NAME') ? APP_NAME : 'Shop'); ?></span>
    </a>

    <nav class="navy">
      <a class="<?php echo is_active('index.php'); ?>"
         href="<?php echo BASE_URL; ?>/admin/index.php">
        <i class="fa-solid fa-gauge-high"></i>
        <span>ড্যাশবোর্ড</span>
      </a>

      <a class="<?php echo is_active(['products.php','product_form.php']); ?>"
         href="<?php echo BASE_URL; ?>/admin/products.php">
        <i class="fa-solid fa-boxes-stacked"></i>
        <span>পণ্য ব্যবস্থাপনা</span>
      </a>

      <a class="<?php echo is_active('categories.php'); ?>"
         href="<?php echo BASE_URL; ?>/admin/categories.php">
        <i class="fa-solid fa-tags"></i>
        <span>ক্যাটেগরি</span>
      </a>

      <a class="<?php echo is_active(['orders.php','order_view.php','order_print.php']); ?>"
         href="<?php echo BASE_URL; ?>/admin/orders.php">
        <i class="fa-solid fa-list-check"></i>
        <span>অর্ডার ব্যবস্থাপনা</span>
      </a>

      <a class="<?php echo is_active('users.php'); ?>"
         href="<?php echo BASE_URL; ?>/admin/users.php">
        <i class="fa-solid fa-users-gear"></i>
        <span>অ্যাডমিন ইউজার</span>
      </a>

      <a class="<?php echo is_active('stock_report.php'); ?>"
         href="<?php echo BASE_URL; ?>/admin/stock_report.php">
        <i class="fa-solid fa-warehouse"></i>
        <span>স্টক রিপোর্ট</span>
      </a>

      <a class="<?php echo is_active('settings.php'); ?>"
         href="<?php echo BASE_URL; ?>/admin/settings.php">
        <i class="fa-solid fa-gear"></i>
        <span>সেটিংস</span>
      </a>

      <hr class="border-secondary">

      <a href="<?php echo BASE_URL; ?>/admin/logout.php">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>লগআউট</span>
      </a>
    </nav>
  </aside>

  <!-- Backdrop for mobile -->
  <div id="sidebarBackdrop" class="backdrop d-lg-none"></div>

  <!-- Content -->
  <main class="content p-3 p-lg-4">
