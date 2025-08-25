<?php require_once __DIR__.'/config.php'; ?>
<?php include __DIR__.'/partials_header.php'; ?>

<?php
/* -------- Settings -------- */
$pdo = get_pdo();
$home_heading = '';
$home_notice  = '';
try {
  $stm = $pdo->prepare("SELECT `key`,`value` FROM settings WHERE `key` IN ('home_heading','home_notice')");
  $stm->execute();
  $rows = $stm->fetchAll(PDO::FETCH_KEY_PAIR);
  $home_heading = $rows['home_heading'] ?? '';
  $home_notice  = $rows['home_notice']  ?? '';
} catch (Exception $e) {}

/* -------- Filters & Products -------- */
$qParam   = trim($_GET['q'] ?? '');
$catParam = $_GET['cat'] ?? '';
$where    = ["p.active = 1"];
$params   = [];

if ($qParam !== '') { $where[]="(p.name LIKE ? OR p.description LIKE ?)"; $params[]='%'.$qParam.'%'; $params[]='%'.$qParam.'%'; }
if ($catParam !== '' && (int)$catParam > 0) { $where[]="p.category_id = ?"; $params[]=(int)$catParam; }

$whereSql = implode(' AND ', $where);
$stmC = $pdo->prepare("SELECT COUNT(*) c FROM products p WHERE $whereSql");
$stmC->execute($params);
$total = (int)$stmC->fetch()['c'];

$initialLimit = 8;
$stm = $pdo->prepare("
  SELECT p.*, c.name AS cat_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE $whereSql
  ORDER BY p.id DESC
  LIMIT $initialLimit
");
$stm->execute($params);
$products = $stm->fetchAll();
?>

<!-- ✅ Scoped notification stack (ডিজাইন-সেফ) -->
<div id="bsNotifyArea" class="bs-alert-stack" aria-live="polite" aria-atomic="true"></div>

<style>
/* ---------------- Grid & Cards (আপনার আগের ডিজাইন) --------------- */
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
@media (min-width:768px){.grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (min-width:992px){.grid{grid-template-columns:repeat(4,minmax(0,1fr));}}
.card{border:1px solid #eee;border-radius:12px;overflow:hidden;background:#fff;transition:.15s ease;}
.card:hover{box-shadow:0 .5rem 1rem rgba(0,0,0,.08);transform:translateY(-2px);}
.card img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;}
.card .body{padding:10px;}
.badge{display:inline-block;font-size:12px;background:#f5f5f5;padding:2px 8px;border-radius:20px;margin-bottom:6px;}
.title{margin:6px 0;font-size:15px;line-height:1.3;}
.price{font-weight:600;margin-right:6px;}
.old{color:#888;text-decoration:line-through;font-size:.9em;}
.actions{display:flex;gap:8px;margin-top:8px;}
.btn{appearance:none;border:none;padding:8px 10px;border-radius:8px;background:#0d6efd;color:#fff;cursor:pointer;font-size:14px;}
.btn[disabled]{opacity:.6;cursor:not-allowed;}
.btn.full{width:100%;display:inline-flex;justify-content:center;}
.notice{color:#6c757d;}
.cover-link{display:block;text-decoration:none;color:inherit;}
.spinner{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;display:inline-block;animation:spin .8s linear infinite;vertical-align:-2px;margin-left:6px;}
@keyframes spin{to{transform:rotate(360deg);}}

/* ---------------- Scoped Bootstrap-like Alerts (শুধু নোটিফিকেশন) --------------- */
.bs-alert-stack{position:fixed;right:12px;top:12px;z-index:1080;display:flex;flex-direction:column;gap:8px;max-width:min(92vw,420px);}
.bs-alert{position:relative;padding:.75rem 2.25rem .75rem 1rem;border:1px solid transparent;border-radius:.375rem;box-shadow:0 12px 24px rgba(2,8,23,.12);font-size:14px;line-height:1.35;opacity:1;transition:opacity .2s ease;}
.bs-alert .bs-close{position:absolute;top:.35rem;right:.5rem;background:transparent;border:0;font-size:1.25rem;line-height:1;color:inherit;cursor:pointer;}
.bs-alert-success{color:#0f5132;background-color:#d1e7dd;border-color:#badbcc;}
.bs-alert-danger {color:#842029;background-color:#f8d7da;border-color:#f5c2c7;}
.bs-alert-warning{color:#664d03;background-color:#fff3cd;border-color:#ffecb5;}
.bs-alert-info   {color:#055160;background-color:#cff4fc;border-color:#b6effb;}

/* বাটন “added” অবস্থা: আপনার .btn স্টাইল রেখে শুধু রং পাল্টাই */
.btn-added{ background:#16a34a !important; color:#fff !important; }
</style>

<?php if ($home_heading || $home_notice): ?>
  <div class="text-center my-4">
    <?php if ($home_heading): ?><h1 class="mb-2"><?=h($home_heading)?></h1><?php endif; ?>
    <?php if ($home_notice): ?><p class="small notice mb-0"><?=h($home_notice)?></p><?php endif; ?>
  </div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin:10px 2px">
  <div class="small">মোট পাওয়া গেছে: <strong><?=$total?></strong> টি পণ্য <?php if($qParam!==''): ?> — “<?=h($qParam)?>”<?php endif; ?></div>
  <a class="small" href="<?=BASE_URL?>/index.php">ফিল্টার ক্লিয়ার</a>
</div>

<div id="productGrid" class="grid">
  <?php foreach($products as $p): ?>
    <div class="card">
      <a class="cover-link" href="<?=BASE_URL?>/product.php?id=<?=(int)$p['id']?>" aria-label="View details: <?=h($p['name'])?>">
        <img src="<?=h($p['image'] ?: (BASE_URL.'/uploads/default.jpg'))?>" alt="<?=h($p['name'])?>">
      </a>
      <div class="body">
        <?php if($p['cat_name']): ?><div class="badge"><?=h($p['cat_name'])?></div><?php endif; ?>
        <a class="cover-link" href="<?=BASE_URL?>/product.php?id=<?=(int)$p['id']?>">
          <h3 class="title"><?=h($p['name'])?></h3>
        </a>
        <div>
          <span class="price"><?=money_bd($p['price'])?></span>
          <?php if(!empty($p['compare_at_price'])): ?><span class="old"><?=money_bd($p['compare_at_price'])?></span><?php endif; ?>
        </div>
        <div class="actions">
          <!-- type="button" + return; পুরনো ক্লাসগুলোই রাখা হলো -->
          <button type="button"
                  class="btn full"
                  data-product-id="<?=(int)$p['id']?>"
                  onclick="return addToCart(this.dataset.productId, event, this)">
            কার্টে যোগ করুন
          </button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php $hasMore = $total > $initialLimit; ?>
<?php if ($hasMore): ?>
  <div style="text-align:center;margin-top:16px">
    <button id="btnLoadMore" class="btn" style="background:#e9ecef;color:#111"
      data-offset="<?=$initialLimit?>" data-step="4"
      data-q="<?=h($qParam)?>" data-cat="<?=h((string)$catParam)?>">
      Load more
    </button>
  </div>
<?php endif; ?>

<script>
/* ---------- Scoped Bootstrap-like alert (১ সেকেন্ড) ---------- */
function bsNotify(message, type='success', duration=1000){
  const area = document.getElementById('bsNotifyArea');
  if(!area) return;
  const el = document.createElement('div');
  el.className = `bs-alert bs-alert-${type}`;
  el.setAttribute('role','alert');
  el.textContent = message;

  const closeBtn = document.createElement('button');
  closeBtn.className = 'bs-close';
  closeBtn.setAttribute('aria-label','Close');
  closeBtn.innerHTML = '&times;';
  closeBtn.addEventListener('click', ()=> el.remove());
  el.appendChild(closeBtn);

  area.appendChild(el);
  setTimeout(()=> el.remove(), Math.max(500, duration));
}

/* ---------- নেটিভ alert → scoped alert (ডিফল্ট ডায়ালগ বন্ধ) ---------- */
(function(){
  const nativeAlert = window.alert;
  window.alert = function(msg){
    try{ bsNotify(String(msg || ''), 'info', 1000); }
    catch(e){ nativeAlert(msg); }
  };
})();

/* ---------- Load More (আপনার আগের লজিক রেখেছি) ---------- */
(function(){
  const btn = document.getElementById('btnLoadMore'); if(!btn) return;
  const grid = document.getElementById('productGrid');

  btn.addEventListener('click', async function(){
    const offset = parseInt(btn.dataset.offset || '0');
    const step   = parseInt(btn.dataset.step   || '4');
    const q      = btn.dataset.q || '';
    const cat    = btn.dataset.cat || '';
    btn.disabled = true; btn.textContent = 'Loading...';

    const qs = new URLSearchParams({offset:String(offset),limit:String(step)});
    if(q.trim()!=='') qs.set('q', q.trim());
    if(cat!=='' && parseInt(cat)>0) qs.set('cat', cat);

    try{
      const res = await fetch('<?=BASE_URL?>/products_more.php?'+qs.toString(), {
        headers:{ 'X-Requested-With':'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      if(!res.ok){ throw new Error('HTTP '+res.status); }
      const html = await res.text();
      if(!html.trim()){ btn.style.display='none'; bsNotify('আর কোনো পণ্য নেই।','info'); return; }
      const temp = document.createElement('div'); temp.innerHTML = html;
      const cards = temp.querySelectorAll('.card'); cards.forEach(c=>grid.appendChild(c));
      btn.dataset.offset = String(offset + step); btn.disabled=false; btn.textContent='Load more';
      const added = cards.length;
      if(added < step){ btn.style.display='none'; bsNotify('সব দেখানো হয়েছে','info'); }
      else{ bsNotify(`${added}টি নতুন পণ্য যোগ হয়েছে।`,'success'); }
    }catch(e){
      btn.disabled=false; btn.textContent='Load more';
      bsNotify('লোড ব্যর্থ। পরে আবার চেষ্টা করুন।','danger');
    }
  });
})();

/* ---------- Add To Cart (AJAX + single add + button state) ---------- */
async function addToCart(productId, evt, btnEl){
  evt?.preventDefault?.();
  evt?.stopPropagation?.();

  // দ্বিতীয়বার ব্লক
  if (btnEl?.dataset.added === '1' || btnEl?.disabled) return false;

  const originalHTML = btnEl ? btnEl.innerHTML : '';
  try{
    if(btnEl){ btnEl.disabled = true; btnEl.innerHTML = 'যোগ হচ্ছে <span class="spinner"></span>'; }

    const fd = new FormData();
    fd.set('product_id', String(productId));
    fd.set('qty', '1');

    const res = await fetch('<?=BASE_URL?>/cart_add.php', {
      method:'POST',
      body: fd,
      headers:{ 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' },
      credentials: 'same-origin'
    });

    let data = {};
    try { data = await res.json(); } catch(_) {}

    if(!res.ok || !data.ok){ throw new Error(data?.message || 'Failed'); }

    // Success → শুধু এই বাটন পরিবর্তন ও লক
    if(btnEl){
      btnEl.classList.add('btn-added');
      btnEl.textContent = 'কার্টে যোগ হয়েছে';
      btnEl.dataset.added = '1';
      btnEl.disabled = true;
      btnEl.setAttribute('aria-pressed','true');
      btnEl.setAttribute('title','এটি ইতিমধ্যে কার্টে যুক্ত হয়েছে');
    }

    bsNotify('কার্টে যোগ হয়েছে', 'success', 1000);
  }catch(e){
    if(btnEl){
      btnEl.disabled = false;
      btnEl.innerHTML = originalHTML || 'কার্টে যোগ করুন';
    }
    bsNotify('কার্টে যোগ করা যায়নি। পরে চেষ্টা করুন।', 'danger', 1000);
  }

  return false; // default prevent
}
</script>

<?php include __DIR__.'/partials_footer.php'; ?>
