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

    <!-- সার্চ + ক্যাটেগরি ফিল্টার -->
    <form class="search" id="searchForm" method="get" action="<?php echo BASE_URL; ?>/index.php" autocomplete="off" style="position:relative;">
      <div style="display:grid; grid-template-columns:1fr 180px 110px; gap:8px;">
        <!-- Search box -->
        <div style="position:relative;">
          <input id="searchInput" name="q" value="<?php echo h($qParam); ?>" placeholder="খুঁজুন... (টি-শার্ট, হুডি...)">
          <div id="suggestBox" class="suggest-box" style="display:none;"></div>
        </div>

        <!-- Category filter -->
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

        <!-- Submit -->
        <button class="btn" type="submit">খুঁজুন</button>
      </div>
    </form>

    <!-- ন্যাভ -->
    <div class="nav">
      <a class="btn secondary" href="<?php echo BASE_URL; ?>/cart.php">
       <i class="fa-solid fa-cart-shopping"></i> <span class="small">(</span><span id="cartCount">0</span><span class="small">)</span>
      </a>
    </div>
  </div>
</header>

<main>
  <div class="container">

<script>
// cat বদলালে URL রিরাইট: cat খালি হলে param বাদ
(function(){
  const form = document.getElementById('searchForm');
  const q    = document.getElementById('searchInput');
  const cat  = document.getElementById('catFilter');

  function go(){
    const params = new URLSearchParams();
    const qv = (q?.value || '').trim();
    const cv = cat?.value || '';  // '' = ALL

    if (qv !== '') params.set('q', qv);
    if (cv !== '') params.set('cat', cv);

    const url = '<?php echo BASE_URL; ?>/index.php' + (params.toString() ? ('?' + params.toString()) : '');
    window.location.href = url;
  }

  cat?.addEventListener('change', function(e){
    e.preventDefault();
    go();
  });
})();
</script>
