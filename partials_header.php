<?php require_once __DIR__.'/config.php'; ?>
<!doctype html>
<html lang="bn">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo APP_NAME; ?></title>

  <!-- CSS / JS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
  <script defer src="<?php echo BASE_URL; ?>/assets/app.js"></script>

  <!-- Responsive Header overrides -->
<style>
  :root{ --blue:#0ea5e9; --blue-600:#0284c7; --border:#e5e7eb; --text:#0f172a; }

  .header{position:sticky; top:0; z-index:50; background:#fff; border-bottom:1px solid var(--border);}
  .header .bar{display:flex; align-items:center; gap:12px; padding:10px 0;}
  .brand a{font-weight:800; color:var(--text); text-decoration:none;}

  /* Search layout (desktop/tablet default) */
  #searchForm{flex:1;}
  .search-row{display:grid; grid-template-columns:1fr 180px 110px; gap:8px;}
  .search-input{position:relative;}
  .search-input i{position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8;}
  .search-input input{
    width:100%; padding:10px 12px 10px 36px; min-height:40px;
    border:1px solid var(--border); border-radius:10px;
  }
  .search-input input:focus{border-color:#cfe3ff; box-shadow:0 0 0 4px #e6f0ff; outline:none;}

  /* Cart button (text hidden globally = only icon) */
  .cart-btn{position:relative; display:inline-flex; align-items:center; gap:8px; text-decoration:none; padding:8px 10px;}
  .cart-btn .label{display:none !important;}   /* ✅ শুধু আইকন */
  .cart-btn .count#cartCount{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:22px; height:22px; padding:0 6px; border-radius:999px;
    background:var(--blue); color:#fff; font-weight:800; font-size:12px;
  }

  /* =================== Mobile: 2-row (brand-left | cart-right + search) =================== */
  @media (max-width:640px){
    .header{ background:var(--blue); border-bottom:none; }
    .header .bar{
      display:grid;
      grid-template-columns:1fr 1fr;     /* দুইটা কলাম */
      grid-template-areas:
        "brand cart"                      /* উপরের রো: Left=Logo, Right=Cart */
        "search search";                  /* নিচের রো: ফুল-উইডথ সার্চ */
      gap:10px;
      padding:12px 0 14px;               /* ✔️ একটু padding */
    }
    .brand{ grid-area:brand; justify-self:start; }
    .brand a{ color:#fff; font-weight:800; letter-spacing:.2px; }

    .header .nav{ grid-area:cart; justify-self:end; }
    .cart-btn{
      width:42px; height:42px; padding:0; justify-content:center; gap:0; border:none;
      margin-right:2px;                  /* ✔️ সামান্য মার্জিন */
    }
    .cart-btn i{ color:#fff; font-size:18px; }
    .cart-btn .count#cartCount{
      position:absolute; top:-6px; right:-6px;
      background:#fff; color:var(--blue); box-shadow:0 0 0 2px var(--blue);
      min-width:20px; height:20px; font-size:12px;
    }

    /* Search pill (সাদা, রাউন্ডেড) */
    #searchForm{ grid-area:search; width:100%; }
    .search-row{ grid-template-columns:1fr; }
    .search-input i{ left:14px; color:#94a3b8; }
    .search-input input{
      min-height:44px; background:#fff; border:1px solid #dbeafe; border-radius:999px;
      padding-left:40px; box-shadow:0 2px 0 rgba(2,8,23,.05);
    }
    .search-input input::placeholder{ color:#94a3b8; }
    .search-input input:focus{ border-color:#bfdbfe; box-shadow:0 0 0 4px rgba(191,219,254,.45); }

    /* Mobile-এ ক্যাটেগরি/সাবমিট হাইড */
    #catFilter, #searchSubmit{ display:none !important; }
  }

  /* Tablet/Desktop ছোট টিউন */
  @media (min-width:641px){
    .cart-btn .count#cartCount{ margin-left:0; } /* শুধু ব্যাজ */
  }
  /* === Common tweaks === */
.cart-btn .label{ display:none !important; }      /* ✅ Cart-এ শুধু আইকন */
.brand a{ display:inline-block; padding:4px 6px; } /* Logo একটু padding */

/* === Mobile (<=640px): দুইটা রো + extra spacing === */
@media (max-width:640px){
  .header{ background:#0ea5e9; border-bottom:none; }
  .header .bar.container{
    display:grid;
    grid-template-columns:1fr 1fr;
    grid-template-areas:
      "brand cart"          /* ১ম রো: Left=Logo, Right=Cart */
      "search search";      /* ২য় রো: ফুল-উইডথ সার্চ */
    gap:12px;
    padding:16px 14px 18px; /* ✅ বাড়তি padding */
  }

  /* placement */
  .brand{ grid-area:brand; justify-self:start; }
  .brand a{ color:#fff; font-weight:800; letter-spacing:.2px; margin-left:2px; }

  .header .nav{ grid-area:cart; justify-self:end; }

  /* cart look (only icon + badge) */
  .cart-btn{
    width:44px; height:44px; padding:8px; margin-right:2px; /* ✅ সামান্য margin/padding */
    border-radius:12px; background:#fff; border:none;
    display:grid; place-items:center;
    box-shadow:0 4px 10px rgba(2,8,23,.12);
  }
  .cart-btn i{ color:#0ea5e9; font-size:18px; }
  .cart-btn .count#cartCount{
    position:absolute; top:-6px; right:-6px;
    min-width:20px; height:20px; padding:0 6px; border-radius:999px;
    background:#0ea5e9; color:#fff; font-weight:700; font-size:12px;
    box-shadow:0 0 0 2px #fff;       /* সাদা রিং */
  }

  /* search pill spacing */
  #searchForm{ grid-area:search; width:100%; }
  .search-row{ grid-template-columns:1fr; }
  .search-input input{ min-height:44px; background:#fff; border:1px solid #dbeafe; border-radius:999px; padding-left:40px; }
}

/* === Desktop/Tablet (>=641px): আইকন-অনলি কার্ট + একটু padding === */
@media (min-width:641px){
  .cart-btn{
    padding:8px; border-radius:10px; background:#fff;
    border:1px solid #e5e7eb; gap:0;
  }
  .cart-btn i{ color:#0f172a; }
  .cart-btn .count#cartCount{
    min-width:22px; height:22px; padding:0 6px; border-radius:999px;
    background:#0ea5e9; color:#fff; font-weight:800; font-size:12px; margin-left:6px;
  }
}

</style>

</head>
<body>

<?php
  // ক্যাটেগরি/কোয়েরি রিড
  $pdo      = get_pdo();
  $cats     = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
  $qParam   = trim($_GET['q']   ?? '');
  $catParam = $_GET['cat'] ?? '';           // '' হলে All
  $isAll    = ($catParam === '' || $catParam === null || (string)$catParam === '0');
?>

<header class="header">
  <div class="bar container">
    <div class="brand">
      <a href="<?php echo BASE_URL; ?>/index.php"><?php echo h(APP_NAME); ?></a>
    </div>

    <!-- সার্চ + ক্যাটেগরি -->
    <form class="search" id="searchForm" method="get" action="<?php echo BASE_URL; ?>/index.php" autocomplete="off">
      <div class="search-row">
        <!-- Search input -->
        <div class="search-input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input id="searchInput" name="q" value="<?php echo h($qParam); ?>" placeholder="Search for products, brands and more">
          <div id="suggestBox" class="suggest-box" style="display:none;"></div>
        </div>

        <!-- Category (hidden on mobile by CSS) -->
        <select name="cat" id="catFilter">
          <option value="" <?php echo $isAll ? 'selected' : ''; ?>>সকল ক্যাটেগরি</option>
          <?php foreach ($cats as $c):
            $cid = (int)$c['id'];
            $selected = (!$isAll && ((int)$catParam === $cid)) ? 'selected' : '';
          ?>
            <option value="<?php echo $cid; ?>" <?php echo $selected; ?>>
              <?php echo h($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Submit (hidden on mobile; Enter চাপলেই সার্চ হবে) -->
        <button class="btn" id="searchSubmit" type="submit">খুঁজুন</button>
      </div>
    </form>

    <!-- Cart -->
    <div class="nav">
      <a class="btn secondary cart-btn" href="<?php echo BASE_URL; ?>/cart.php" aria-label="কার্ট">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="label">কার্ট</span>
        <span class="count" id="cartCount">0</span>
      </a>
    </div>
  </div>
</header>

<main>
  <div class="container">

<script>
/* ক্যাটেগরি বদলালে URL রিরাইট: cat খালি হলে param বাদ */
(function(){
  const q   = document.getElementById('searchInput');
  const cat = document.getElementById('catFilter');

  function go(){
    const params = new URLSearchParams();
    const qv = (q?.value || '').trim();
    const cv = cat?.value || '';  // '' = ALL
    if (qv !== '') params.set('q', qv);
    if (cv !== '') params.set('cat', cv);
    const url = '<?php echo BASE_URL; ?>/index.php' + (params.toString() ? ('?' + params.toString()) : '');
    window.location.href = url;
  }
  cat?.addEventListener('change', function(e){ e.preventDefault(); go(); });
})();
</script>
