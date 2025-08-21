<?php require_once __DIR__.'/config.php'; ?>
<?php include __DIR__.'/partials_header.php'; ?>

<?php
$pdo = get_pdo();

$home_heading = '';
$home_notice  = '';

try {
    // একসাথে দুইটা key ফেচ করা
    $stm = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('home_heading','home_notice')");
    $stm->execute();
    $rows = $stm->fetchAll(PDO::FETCH_KEY_PAIR); // key => value আকারে পাবেন

    if (!empty($rows['home_heading'])) $home_heading = $rows['home_heading'];
    if (!empty($rows['home_notice']))  $home_notice  = $rows['home_notice'];

} catch (Exception $e) {
    // fallback
    // error_log($e->getMessage());
}
?>

<style>
  /* Mobile-first grid: মোবাইলে ২-কলাম */
  .grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px; }
  @media (min-width: 768px){ .grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
  @media (min-width: 992px){ .grid { grid-template-columns: repeat(4, minmax(0,1fr)); } }

  .card{ border:1px solid #eee; border-radius:12px; overflow:hidden; background:#fff; transition:.15s ease; }
  .card:hover{ box-shadow:0 .5rem 1rem rgba(0,0,0,.08); transform: translateY(-2px); }
  .card img{ width:100%; aspect-ratio:1/1; object-fit:cover; display:block; }
  .card .body{ padding:10px; }
  .badge{ display:inline-block; font-size:12px; background:#f5f5f5; padding:2px 8px; border-radius:20px; margin-bottom:6px; }
  .title{ margin:6px 0 6px; font-size:15px; line-height:1.3; }
  .price{ font-weight:600; margin-right:6px; }
  .old{ color:#888; text-decoration:line-through; font-size:.9em; }
  .actions{ display:flex; gap:8px; margin-top:8px; }
  .btn{ appearance:none; border:none; padding:8px 10px; border-radius:8px; background:#0d6efd; color:#fff; cursor:pointer; font-size:14px; }
  .btn[disabled]{ opacity:.6; cursor:not-allowed; }
  .btn.full{ width:100%; justify-content:center; display:inline-flex; }
  .notice{ color:#6c757d; }
  .cover-link{ display:block; text-decoration:none; color:inherit; }
</style>

<!-- DB থেকে হেডিং/নোটিশ -->
<?php if ($home_heading !== '' || $home_notice !== ''): ?>
  <div class="text-center my-4">
    <?php if ($home_heading !== ''): ?>
      <h1 class="mb-2"><?php echo h($home_heading); ?></h1>
    <?php endif; ?>

    <?php if ($home_notice !== ''): ?>
      <p class="small notice mb-0"><?php echo h($home_notice); ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
$qParam   = trim($_GET['q']   ?? '');
$catParam = $_GET['cat'] ?? '';

$where  = ["p.active = 1"];
$params = [];

// সার্চ ফিল্টার
if ($qParam !== '') {
  $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
  $params[] = '%'.$qParam.'%';
  $params[] = '%'.$qParam.'%';
}

// ক্যাটেগরি ফিল্টার
if ($catParam !== '' && (int)$catParam > 0) {
  $where[]  = "p.category_id = ?";
  $params[] = (int)$catParam;
}

$whereSql = implode(' AND ', $where);

// মোট কাউন্ট
$sqlCount = "SELECT COUNT(*) c FROM products p WHERE $whereSql";
$stmC = $pdo->prepare($sqlCount);
$stmC->execute($params);
$total = (int)$stmC->fetch()['c'];

// প্রথম লোডে ৮টা প্রোডাক্ট
$initialLimit = 8;
$sql = "SELECT p.*, c.name AS cat_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY p.id DESC
        LIMIT $initialLimit";
$stm = $pdo->prepare($sql);
$stm->execute($params);
$products = $stm->fetchAll();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin:10px 2px">
  <div class="small">
    মোট পাওয়া গেছে: <strong><?php echo $total; ?></strong> টি পণ্য
    <?php if($qParam!==''): ?> — “<?php echo h($qParam); ?>”<?php endif; ?>
  </div>
  <a class="small" href="<?php echo BASE_URL; ?>/index.php">ফিল্টার ক্লিয়ার</a>
</div>

<!-- প্রোডাক্ট গ্রিড: মোবাইলে প্রতি রোতে ২টি -->
<div id="productGrid" class="grid">
<?php foreach($products as $p): ?>
  <div class="card">
    <!-- কার্ডের উপরের অংশ (image + title) = ডিটেইলস লিংক -->
    <a class="cover-link" href="<?php echo BASE_URL; ?>/product.php?id=<?php echo (int)$p['id']; ?>" aria-label="View details: <?php echo h($p['name']); ?>">
      <img src="<?php echo h($p['image'] ?: (BASE_URL.'/uploads/default.jpg')); ?>" alt="<?php echo h($p['name']); ?>">
    </a>
    <div class="body">
      <?php if($p['cat_name']): ?><div class="badge"><?php echo h($p['cat_name']); ?></div><?php endif; ?>
      <a class="cover-link" href="<?php echo BASE_URL; ?>/product.php?id=<?php echo (int)$p['id']; ?>">
        <h3 class="title"><?php echo h($p['name']); ?></h3>
      </a>
      <div>
        <span class="price"><?php echo money_bd($p['price']); ?></span>
        <?php if(!empty($p['compare_at_price'])): ?>
          <span class="old"><?php echo money_bd($p['compare_at_price']); ?></span>
        <?php endif; ?>
      </div>
      <div class="actions">
        <!-- ❌ ডিটেইলস বাটন নেই -->
        <!-- ✅ শুধু Add To Cart -->
        <button class="btn full" data-qa="add-to-cart" onclick="addToCart(<?php echo (int)$p['id']; ?>)">কার্টে যোগ করুন</button>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php $hasMore = $total > $initialLimit; ?>
<?php if ($hasMore): ?>
  <div style="text-align:center;margin-top:16px">
    <button id="btnLoadMore"
            class="btn"
            style="background:#e9ecef;color:#111"
            data-offset="<?php echo $initialLimit; ?>"
            data-step="4"
            data-q="<?php echo h($qParam); ?>"
            data-cat="<?php echo h((string)$catParam); ?>">
      Load more
    </button>
  </div>
<?php endif; ?>

<script>
// Load more
(function(){
  const btn = document.getElementById('btnLoadMore');
  if(!btn) return;

  const grid = document.getElementById('productGrid');

  btn.addEventListener('click', async function(){
    const offset = parseInt(btn.dataset.offset || '0');
    const step   = parseInt(btn.dataset.step   || '4');
    const q      = btn.dataset.q || '';
    const cat    = btn.dataset.cat || '';

    btn.disabled = true; btn.textContent = 'Loading...';

    const params = new URLSearchParams();
    params.set('offset', offset);
    params.set('limit', step);
    if(q.trim() !== '') params.set('q', q.trim());
    if(cat !== '' && parseInt(cat) > 0) params.set('cat', cat);

    try{
      const res = await fetch('<?php echo BASE_URL; ?>/products_more.php?' + params.toString());
      const html = await res.text();

      if(!html || html.trim() === ''){
        btn.style.display = 'none';
        return;
      }

      const temp = document.createElement('div');
      temp.innerHTML = html;
      temp.querySelectorAll('.card').forEach(card => grid.appendChild(card));

      btn.dataset.offset = String(offset + step);
      btn.disabled = false; btn.textContent = 'Load more';

      const added = temp.querySelectorAll('.card').length;
      if (added < step) btn.style.display = 'none';
    }catch(e){
      btn.disabled = false; btn.textContent = 'Load more';
      alert('লোড করা যায়নি, আবার চেষ্টা করুন।');
    }
  });
})();

// Add To Cart (AJAX)
async function addToCart(productId){
  try{
    const fd = new FormData();
    fd.set('action', 'add');
    fd.set('product_id', String(productId));

    const res = await fetch('<?php echo BASE_URL; ?>/cart.php', {
      method: 'POST',
      body: fd
    });

    if(!res.ok) throw new Error('failed');
    // TODO: আপনার cart.php রেসপন্স অনুযায়ী UI আপডেট করুন (mini-cart কাউন্টার ইত্যাদি)
    alert('কার্টে যোগ করা হয়েছে!');
  }catch(e){
    alert('কার্টে যোগ করা যায়নি, আবার চেষ্টা করুন।');
  }
}
</script>

<?php include __DIR__.'/partials_footer.php'; ?>
