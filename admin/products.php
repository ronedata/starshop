<?php
// admin/products.php — name no-link + safe toggle + AJAX delete + toast
require_once __DIR__.'/../config.php';
require_once __DIR__.'/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* CSRF (delete API-এর জন্য) */
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

/* --------- Filters --------- */
$q      = trim($_GET['q'] ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$status = $_GET['status'] ?? 'all'; // all|active|inactive

/* --------- Handle status toggle BEFORE any output --------- */
if (isset($_GET['toggle'])) {
  $id = (int)$_GET['toggle'];
  if ($id > 0) {
    $pdo->prepare("UPDATE products SET active = 1 - active WHERE id=?")->execute([$id]);
    $_SESSION['flash'] = ['ok'=>true, 'msg'=>'স্ট্যাটাস আপডেট হয়েছে'];
  }
  // আগের ফিল্টার রেখে রিডাইরেক্ট (toggle বাদ)
  $ret = $_GET; unset($ret['toggle']); $qs = http_build_query($ret);
  header("Location: products.php".($qs ? "?$qs" : ""));
  exit;
}

/* --------- Load categories --------- */
$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

/* --------- Query --------- */
$where  = "1=1";
$params = [];
if ($q !== '') { $where .= " AND p.name LIKE ?"; $params[] = "%$q%"; }
if ($cat > 0)  { $where .= " AND p.category_id=?"; $params[] = $cat; }
if ($status === 'active')   $where .= " AND p.active=1";
elseif ($status === 'inactive') $where .= " AND p.active=0";

$sql = "SELECT p.*, c.name AS cat_name,
        (SELECT image FROM product_images pi WHERE pi.product_id=p.id ORDER BY sort_order, id LIMIT 1) AS image
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        WHERE $where
        ORDER BY p.id DESC";
$stm = $pdo->prepare($sql); $stm->execute($params); $rows = $stm->fetchAll(PDO::FETCH_ASSOC);

/* --------- Helpers --------- */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_bd')) {
  function money_bd($n){ $n=(float)$n; $n=round($n); return '৳'.number_format($n,0,'',','); }
}

/* --------- Now load layout header (HTML starts) --------- */
require_once __DIR__.'/header.php';
?>
<style>
  .thumb{width:60px;height:60px;object-fit:cover;border:1px solid #e5e7eb;border-radius:8px;background:#fff}
  .btn-pill{border-radius:999px}

  /* Mobile card table */
  @media (max-width: 576px){
    .table-responsive{border:0}
    table.table{display:block}
    table.table thead{display:none}
    table.table tbody{display:block}
    table.table tr{
      display:block; background:#fff; border:1px solid #e9ecef; border-radius:12px;
      padding:12px; margin-bottom:10px; box-shadow:0 6px 16px rgba(2,8,23,.04);
    }
    table.table td{
      display:flex; justify-content:space-between; gap:12px;
      padding:8px 0 !important; border:0 !important; vertical-align:middle !important;
    }
    table.table td::before{
      content:attr(data-label); font-weight:600; color:#64748b; min-width:110px; text-align:left;
    }
    .td-actions{ display:flex; flex-direction:column; gap:8px; }
    .td-actions .btn{ width:100%; }
    .cell-img{ display:grid; grid-template-columns:64px 1fr; gap:10px; align-items:center; }
    .cell-img::before{ content:''; display:none; }
    .cell-name .name{ font-weight:700; }
  }

  /* Toast area */
  #toastArea{ position:fixed; right:16px; bottom:16px; z-index:1080; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">পণ্য ব্যবস্থাপনা</h5>
  <a class="btn btn-primary btn-pill" href="product_form.php">+ নতুন পণ্য</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-4">
    <input class="form-control" name="q" placeholder="পণ্য খুঁজুন..." value="<?php echo h($q); ?>">
  </div>
  <div class="col-6 col-md-3">
    <select class="form-select" name="cat">
      <option value="0">সব ক্যাটেগরি</option>
      <?php foreach($cats as $c): ?>
        <option value="<?php echo (int)$c['id']; ?>" <?php echo $cat===(int)$c['id']?'selected':''; ?>>
          <?php echo h($c['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <select class="form-select" name="status">
      <option value="all" <?php echo $status==='all'?'selected':''; ?>>সব স্ট্যাটাস</option>
      <option value="active" <?php echo $status==='active'?'selected':''; ?>>Active</option>
      <option value="inactive" <?php echo $status==='inactive'?'selected':''; ?>>Inactive</option>
    </select>
  </div>
  <div class="col-12 col-md-2 d-grid">
    <button class="btn btn-outline-secondary btn-pill">ফিল্টার</button>
  </div>
</form>

<div class="card"><div class="card-body p-0">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>ছবি</th>
          <th>নাম</th>
          <th>ক্যাটেগরি</th>
          <th class="text-end">দাম</th>
          <th class="text-end">স্টক</th>
          <th>স্ট্যাটাস</th>
          <th class="text-end">অ্যাকশন</th>
        </tr>
      </thead>
      <tbody id="rowsBody">
      <?php foreach($rows as $r):
        $img = $r['image'] ?: (defined('UPLOAD_URL') ? (rtrim(UPLOAD_URL,'/').'/default.jpg') : '');
      ?>
        <tr id="row-<?php echo (int)$r['id']; ?>">
          <td data-label="ID">#<?php echo (int)$r['id']; ?></td>

          <td data-label="ছবি" class="cell-img">
            <img class="thumb" src="<?php echo h($img); ?>" alt="">
          </td>

          <!-- ✅ নাম: আর কোনো লিঙ্ক নেই -->
          <td data-label="নাম" class="cell-name">
            <span class="name"><?php echo h($r['name']); ?></span>
          </td>

          <td data-label="ক্যাটেগরি"><?php echo h($r['cat_name']); ?></td>

          <td data-label="দাম" class="text-end"><?php echo money_bd($r['price']); ?></td>

          <td data-label="স্টক" class="text-end"><?php echo (int)$r['stock']; ?></td>

          <td data-label="স্ট্যাটাস">
            <!-- টগল ব্যাজ-বাটন -->
            <a class="btn btn-sm btn-pill <?php echo $r['active']?'btn-success':'btn-outline-secondary'; ?>"
               title="স্ট্যাটাস টগল করুন"
               href="?<?php $qs = $_GET; $qs['toggle']=(int)$r['id']; echo h(http_build_query($qs)); ?>">
              <?php echo $r['active'] ? 'Active' : 'Inactive'; ?>
            </a>
          </td>

          <td data-label="অ্যাকশন" class="text-end td-actions">
            <div class="btn-group">
              <a class="btn btn-sm btn-outline-primary btn-pill" href="product_form.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
              <button type="button" class="btn btn-sm btn-outline-danger btn-pill btn-del"
                      data-id="<?php echo (int)$r['id']; ?>"
                      data-name="<?php echo h($r['name']); ?>">
                Delete
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">কোনো পণ্য পাওয়া যায়নি</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div></div>

<!-- Delete confirm modal -->
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">পণ্য ডিলিট</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        আপনি কি নিশ্চিত? <br>
        <strong id="delName"></strong> ডিলিট হলে সংশ্লিষ্ট ইমেজগুলোও (default.jpg ছাড়া) ডিলিট হবে।
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-pill" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-danger btn-pill" id="confirmDel">ডিলিট</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast area -->
<div id="toastArea"></div>

<script>
(function(){
  /* ------- Delete with AJAX ------- */
  const delModalEl = document.getElementById('delModal');
  const delNameEl  = document.getElementById('delName');
  const confirmBtn = document.getElementById('confirmDel');
  let delId = null;

  document.querySelectorAll('.btn-del').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      delId = btn.dataset.id;
      delNameEl.textContent = btn.dataset.name || ('#'+delId);
      bootstrap.Modal.getOrCreateInstance(delModalEl).show();
    });
  });

  confirmBtn.addEventListener('click', async ()=>{
    if(!delId) return;
    confirmBtn.disabled = true; confirmBtn.textContent = 'মুছে ফেলা হচ্ছে...';
    try{
      const fd = new FormData();
      fd.set('id', delId);
      fd.set('csrf', '<?php echo h($_SESSION["csrf"]); ?>');

		const res = await fetch('product_delete.php', {
		  method: 'POST',
		  body: fd,
		  headers: {
			'X-Requested-With': 'XMLHttpRequest',   // ✅ Ajax ডিটেক্ট হবে
			'Accept': 'application/json'            // ✅ JSON চান
		  }
		});
      const data = await res.json().catch(()=>({ok:false}));
      if(!res.ok || !data.ok){ throw new Error(data.message||'Failed'); }

      // ✅ DOM থেকে রো রিমুভ → সাথে সাথে আর দেখাবে না
      document.getElementById('row-'+delId)?.remove();
      showToast('সফল', 'পণ্যটি ডিলিট হয়েছে।', 'success');
    }catch(e){
      showToast('ব্যর্থ', 'ডিলিট করা যায়নি। পরে চেষ্টা করুন।', 'danger');
    }finally{
      confirmBtn.disabled=false; confirmBtn.textContent='ডিলিট';
      bootstrap.Modal.getOrCreateInstance(delModalEl).hide();
      delId=null;
    }
  });

  /* ------- Bootstrap toast helper ------- */
  window.showToast = function(title, msg, variant){
    const id = 't'+Math.random().toString(36).slice(2);
    const html = `
      <div id="${id}" class="toast align-items-center text-bg-${variant||'secondary'} border-0 mb-2" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body"><strong>${title}:</strong> ${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>`;
    const wrap = document.getElementById('toastArea');
    wrap.insertAdjacentHTML('beforeend', html);
    const el = document.getElementById(id);
    const t = new bootstrap.Toast(el, {delay: 2000});
    t.show();
    el.addEventListener('hidden.bs.toast', ()=> el.remove());
  };

  /* ------- Flash from PHP (delete via POST redirect, toggle etc.) ------- */
  <?php if (!empty($_SESSION['flash'])):
        $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    showToast('<?php echo $f['ok']?'সফল':'বার্তা'; ?>', '<?php echo h($f['msg']); ?>', '<?php echo $f['ok']?'success':'secondary'; ?>');
  <?php endif; ?>
})();
</script>

<?php require __DIR__.'/footer.php'; ?>
