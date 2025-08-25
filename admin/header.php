<?php
// admin/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// ছোট্ট fallback helper (যদি config.php তে h() না থাকে)
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - <?php echo defined('APP_NAME') ? h(APP_NAME) : 'Dashboard'; ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --sb-bg:#0f172a;    /* sidebar bg */
      --sb-bg-2:#1e293b;  /* hover */
      --sb-fg:#cbd5e1;    /* text */
      --sb-fg-2:#fff;     /* active */
      --sb-w:240px;
    }
    html,body{height:100%}
    body{overflow-x:hidden}

    /* ======= Layout ======= */
    .layout{
      display:grid;
      grid-template-columns: var(--sb-w) 1fr;
      min-height:100vh;
    }
    .content{ padding: 1rem; }
    @media (min-width: 992px){
      .content{ padding: 1.25rem 1.5rem; }
    }

    /* ======= Sidebar ======= */
    .sidebar{
      background:var(--sb-bg);
      color:#fff;
      position:sticky; top:0; height:100vh;
      padding:16px; z-index:1041; /* desktop z-index */
    }
    .sidebar a{
      color:var(--sb-fg);
      text-decoration:none;
      display:block;
      padding:8px 12px;
      border-radius:8px;
      margin-bottom:6px;
      transition: background .18s ease, color .18s ease;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .sidebar a.active, .sidebar a:hover{
      background:var(--sb-bg-2);
      color:var(--sb-fg-2);
    }
    .brand{
      color:#fff; font-weight:700; display:block; margin-bottom:12px;
    }

    /* ======= Topbar (mobile only) ======= */
    .topbar{
      background:var(--sb-bg);
      color:#fff;
      height:56px;
      display:flex;
      align-items:center;
      padding:0 12px;
      position:sticky; top:0; z-index:1042;
    }
    .topbar button{ border:0; background:transparent; color:#fff; font-size:22px; line-height:1; }
    .topbar .brand-mini{ font-weight:600; margin-left:8px; }

    /* ======= Responsive Sidebar (off-canvas on mobile) ======= */
    @media (max-width: 992px){
      .layout{ grid-template-columns: 1fr; }
      .sidebar{
        position:fixed;
        left:-260px;  /* hidden */
        top:0;
        width:var(--sb-w);
        height:100vh;
        transition: left .25s ease;
        z-index:1045; /* above backdrop */
        display:block !important; /* ensure visible even if any d-none added elsewhere */
      }
      .sidebar.show{ left:0; }
      .content{ padding-top: 56px; } /* leave space for mobile topbar */
    }

    /* ======= Backdrop for mobile ======= */
    #sbBackdrop{
      position:fixed; inset:0;
      background:rgba(0,0,0,.35);
      z-index:1044;
      opacity:0; visibility:hidden;
      transition: opacity .2s ease, visibility .2s ease;
    }
    #sbBackdrop.show{ opacity:1; visibility:visible; }

    .thumb{width:64px;height:64px;object-fit:cover;border:1px solid #e5e7eb;border-radius:6px}
  </style>
</head>
<body>

<!-- Topbar (mobile) -->
<div class="topbar d-lg-none">
  <button type="button" aria-label="সাইডবার টগল" onclick="toggleSidebar()">☰</button>
  <div class="brand-mini ms-2">Admin Panel</div>
  <div class="ms-auto small">Hi, <?php echo h($_SESSION['admin_username'] ?? ''); ?></div>
</div>

<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <a class="brand" href="<?php echo h((defined('BASE_URL')?BASE_URL:'') . '/admin/index.php'); ?>">
      ⭐ <?php echo defined('APP_NAME') ? h(APP_NAME) : 'App'; ?>
    </a>

    <?php
      $self = basename($_SERVER['PHP_SELF']);
      $link = function($file, $text){
        $is = basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
        $href = (defined('BASE_URL')?BASE_URL:'') . '/admin/' . $file;
        echo '<a class="'.$is.'" href="'.h($href).'">'.h($text).'</a>';
      };
      $link('index.php',      'ড্যাশবোর্ড');
      $link('products.php',   'পণ্য ব্যবস্থাপনা');
      $link('categories.php', 'ক্যাটেগরি');
      $link('orders.php',     'অর্ডার ব্যবস্থাপনা');
      $link('users.php',      'অ্যাডমিন ইউজার');
      $link('stock_report.php','স্টক রিপোর্ট');
      $link('settings.php',   'সেটিংস');
    ?>
    <hr>
    <a href="<?php echo h((defined('BASE_URL')?BASE_URL:'') . '/admin/logout.php'); ?>">লগআউট</a>
  </aside>

  <!-- Backdrop (mobile) -->
  <div id="sbBackdrop" class="d-lg-none" onclick="toggleSidebar()"></div>

  <!-- Page Content Start -->
  <main class="content p-3 p-lg-4">
  
<script>
  // Sidebar toggle + backdrop (works without Bootstrap JS)
  function toggleSidebar(){
    const sb = document.querySelector('.sidebar');
    const bd = document.getElementById('sbBackdrop');
    if(!sb || !bd) return;
    sb.classList.toggle('show');
    bd.classList.toggle('show');
  }

  // Close on ESC (mobile)
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      const sb = document.querySelector('.sidebar');
      const bd = document.getElementById('sbBackdrop');
      if(sb?.classList.contains('show')){
        sb.classList.remove('show');
        bd?.classList.remove('show');
      }
    }
  });
</script>
