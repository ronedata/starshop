<?php
require_once __DIR__.'/config.php';
$pdo = get_pdo();

/* ---------- Product read ---------- */
$id = (int)($_GET['id'] ?? 0);
$stm = $pdo->prepare("
  SELECT p.*, c.name AS cat_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.id = ? AND p.active = 1
  LIMIT 1
");
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) { http_response_code(404); echo "Not Found"; exit; }

/* ---------- Stock flags (column fallback supported) ---------- */
$stock   = max(0, (int)($p['stock'] ?? $p['quantity'] ?? $p['qty'] ?? 0));
$isOut   = ($stock === 0);
$isLimited = (!$isOut && $stock < 10);

/* ---------- Specifications parse ---------- */
$specsRows = [];
$rawSpecs  = trim((string)($p['specifications'] ?? ''));
if ($rawSpecs !== '') {
  $json = json_decode($rawSpecs, true);
  if (is_array($json)) {
    if (array_keys($json) !== range(0, count($json)-1)) {
      foreach ($json as $k=>$v) $specsRows[]=[(string)$k, is_scalar($v)?(string)$v:json_encode($v, JSON_UNESCAPED_UNICODE)];
    } else {
      foreach ($json as $item) {
        if (is_array($item) && isset($item['key'],$item['value'])) $specsRows[]=[(string)$item['key'],(string)$item['value']];
        elseif (is_array($item) && count($item)===2){ $vals=array_values($item); $specsRows[]=[(string)$vals[0],(string)$vals[1]]; }
      }
    }
  }
  if (!$specsRows) {
    $tokens = preg_split('/\r\n|\r|\n|;|,/', $rawSpecs);
    foreach ($tokens as $ln) {
      $ln = trim($ln); if ($ln==='') continue;
      if (preg_match('/^\s*[-*•]?\s*([^:=|—–]+?)\s*[:=\-|—–]\s*(.+)\s*$/u', $ln, $m)) {
        $k=trim($m[1]); $v=trim($m[2]); if ($k!=='' && $v!=='') $specsRows[]=[$k,$v];
      }
    }
  }
}

/* ---------- Images: product_images > products.image fallback ---------- */
$images=[];
$imgStm=$pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY COALESCE(sort_order,999999), id");
$imgStm->execute([$id]);
$images=$imgStm->fetchAll(PDO::FETCH_COLUMN);
if (!$images) { if (!empty($p['image'])) $images=[$p['image']]; }

/* ---------- Related ---------- */
$related=[];
if (!empty($p['category_id'])) {
  $rel=$pdo->prepare("
    SELECT id,name,price,compare_at_price,image
    FROM products
    WHERE active=1 AND category_id=? AND id<>?
    ORDER BY created_at DESC
    LIMIT 12
  ");
  $rel->execute([(int)$p['category_id'], (int)$p['id']]);
  $related=$rel->fetchAll();
}

/* ---------- Flags & Tags ---------- */
$tags     = (string)($p['tags'] ?? '');
$hasB1G1  = (bool)preg_match('/\b(b1g1|buy\s*1\s*get\s*1|buy1get1)\b/i',$tags);
$hasFree  = (bool)preg_match('/\bfree(ship(ping)?)?\b/i',$tags);

/* NEW: tag list as badges (split: comma/semicolon/pipe/#/newline) */
$tagItems = [];
if ($tags !== '') {
  $parts = preg_split('/[\n\r;,|#]+/u', $tags);
  foreach ($parts as $t) {
    $t = trim($t);
    if ($t === '') continue;
    // ট্যাগে শুরুর # কেটে দেই
    $t = ltrim($t, "# \t");
    if ($t !== '') $tagItems[] = $t;
  }
  $tagItems = array_values(array_unique($tagItems));
  // খুব বেশি হলে প্রথম 10টা দেখাই
  if (count($tagItems) > 10) $tagItems = array_slice($tagItems, 0, 10);
}

include __DIR__.'/partials_header.php';
?>

<!-- Font Awesome load (head-এ ইনজেক্ট) -->
<script>
(function(){
  if(!document.querySelector('link[href*="font-awesome"][href*="cdnjs"]')){
    var l=document.createElement('link');
    l.rel='stylesheet';
    l.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
    document.head.appendChild(l);
  }
})();
</script>

<style>
  :root{--brand:#0ea5e9;--brand-600:#0284c7;--text:#0f172a;--muted:#64748b;--border:#e5e7eb;--bg:#f6f8fb;--white:#fff;--radius:14px;--shadow:0 10px 24px rgba(2,8,23,.08);}
  #pdp{max-width:1100px;margin:18px auto 24px;padding:0 14px;}

  /* ===== Hero (Gallery + Info) ===== */
  #pdp .hero{display:grid;gap:14px;grid-template-areas:"media" "info";}
  #pdp .hero .media{grid-area:media;}
  #pdp .hero .info{grid-area:info;}

  /* Gallery (fixed height) */
  #pdp .gallery{--gal-h:300px;position:relative;border:1px solid var(--border);border-radius:var(--radius);background:#f4f6f8;overflow:hidden;}
  #pdp .slides{display:flex;width:100%;height:var(--gal-h);transform:translateX(0%);transition:transform .25s ease;}
  #pdp .slide{min-width:100%;height:var(--gal-h);display:flex;align-items:center;justify-content:center;}
  #pdp .slide img{width:100%;height:100%;object-fit:contain;display:block;}
  #pdp .nav{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border:none;border-radius:999px;background:rgba(255,255,255,.95);box-shadow:var(--shadow);cursor:pointer;display:grid;place-items:center}
  #pdp .nav.prev{left:8px;} #pdp .nav.next{right:8px;} #pdp .nav:disabled{opacity:.5;cursor:default}

  #pdp .thumbs{display:flex;gap:8px;margin-top:8px;overflow:auto;}
  #pdp .thumbs img{width:60px;height:60px;object-fit:cover;border:1px solid var(--border);border-radius:8px;background:#fff;opacity:.85;cursor:pointer;}
  #pdp .thumbs img.active{outline:2px solid var(--brand);opacity:1;}

  /* Info text & actions */
  #pdp .cat{color:var(--muted);font-size:.92rem;margin-bottom:4px;display:block;}
  #pdp h1{margin:6px 0 8px;font-size:clamp(1.1rem,1.6vw + .8rem,1.7rem);line-height:1.25;}
  #pdp .chips{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 4px;}
  #pdp .chip{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;font-size:.84rem;border-radius:999px;border:1px solid var(--border);background:var(--white)}
  #pdp .chip.green{background:#ecfdf5;color:#065f46;border-color:#d1fae5;}
  #pdp .chip.blue{background:#eff6ff;color:#1e40af;border-color:#dbeafe;}
  #pdp .chip.purple{background:#f5f3ff;color:#6d28d9;border-color:#ede9fe;}
  /* NEW: stock status chips */
  #pdp .chip.red{background:#fee2e2;color:#b91c1c;border-color:#fecaca;}
  #pdp .chip.orange{background:#fff7ed;color:#9a3412;border-color:#fed7aa;}

  #pdp .price-row{margin:10px 0;display:flex;align-items:baseline;gap:10px;}
  #pdp .price{font-weight:800;color:var(--text);font-size:clamp(1.2rem,1.8vw + .9rem,1.8rem);}
  #pdp .old{color:#94a3b8;text-decoration:line-through;}

  /* Qty + Buttons */
  #pdp .action-row{display:grid;grid-template-columns:1fr;gap:10px;margin:12px 0 6px;}
  #pdp .qty{display:grid;grid-template-columns:44px 1fr 44px;align-items:center;border:1px solid var(--border);border-radius:12px;overflow:hidden;background:#fff;}
  #pdp .qty input{height:44px;text-align:center;border:none;border-inline:1px solid var(--border);font-size:16px;}
  #pdp .qty button{height:44px;border:none;background:#f8fafc;font-size:18px;cursor:pointer;}
  #pdp .qty button:hover{background:#f1f5f9;}
  /* NEW: disabled looks */
  .btn[disabled], .btn.disabled{opacity:.6; cursor:not-allowed; pointer-events:none;}
  #pdp .qty.disabled{opacity:.6;}
  #pdp .qty.disabled button, #pdp .qty.disabled input{pointer-events:none;}

  #pdp .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:0 16px;border-radius:12px;font-weight:700;text-decoration:none;cursor:pointer;transition:background .2s ease,box-shadow .2s ease,transform .02s ease;}
  #pdp .btn i{font-size:1rem;line-height:1; width:1em; text-align:center; pointer-events:none;}
  #pdp .btn:active{transform:translateY(1px);}
  #pdp .btn.primary{background:var(--brand);color:#fff;border:none;}
  #pdp .btn.primary:hover{background:var(--brand-600);}
  #pdp .btn.ghost{background:#fff;border:1px solid var(--border);color:var(--text);}
  #pdp .btn.ghost:hover{box-shadow:var(--shadow);}

  #pdp .call{display:flex;align-items:center;gap:8px;margin-top:8px;color:var(--text);}
  #pdp .call i{color:#0f172a;}

  /* ===== Full-width Tabs (below Hero) ===== */
  #pdp .tabs{margin-top:18px;border-bottom:1px solid var(--border);display:flex;gap:8px;}
  #pdp .tab{background:#fff;border:1px solid var(--border);border-bottom:none;padding:10px 12px;border-top-left-radius:10px;border-top-right-radius:10px;cursor:pointer;font-weight:600;color:#334155;}
  #pdp .tab.active{background:#eff6ff;color:#1e40af;border-color:#cfe3ff;}
  #pdp .panel{display:none;background:#fff;border:1px solid var(--border);border-radius:0 10px 10px 10px;padding:12px;}
  #pdp .panel.active{display:block;}
  #pdp .specs{width:100%;border-collapse:collapse;font-size:.96rem;}
  #pdp .specs th,#pdp .specs td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:top;}
  #pdp .specs th{width:42%;color:#475569;text-align:left;background:#f8f8fb;}
  #pdp .specs td{word-break:break-word;}

  /* Related */
  #pdp .rel-head{display:flex;align-items:center;justify-content:space-between;margin:18px 0 10px;}
  #pdp .rel-link{font-weight:600;color:#1e40af;text-decoration:none;}
  #pdp .rel-wrap{overflow:auto;}
  #pdp .rel-row{display:grid;grid-auto-flow:column;grid-auto-columns:70%;gap:10px;}
  #pdp .rel-card{background:#fff;border:1px solid var(--border);border-radius:12px;min-width:210px;overflow:hidden;text-decoration:none;color:inherit;}
  #pdp .rel-card .thumb{width:100%;aspect-ratio:1/1;object-fit:cover;background:#f4f6f8;display:block;}
  #pdp .rel-card .body{padding:10px;}
  #pdp .rel-card .ttl{font-weight:600;margin:4px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  #pdp .rel-card .pr{font-weight:800;}
  #pdp .rel-card .old{color:#94a3b8;margin-left:6px;text-decoration:line-through;}

  /* Desktop */
  @media (min-width:768px){
    #pdp .hero{grid-template-columns:1.1fr 1fr;grid-template-areas:"media info";gap:24px;}
    #pdp .gallery{--gal-h:460px;}
    #pdp .action-row{grid-template-columns:220px auto auto; align-items:center;} /* qty + add + buy side-by-side */
    #pdp .rel-row{grid-auto-columns:unset;grid-template-columns:repeat(4,1fr);}
    #pdp .btn{min-width:200px;}
  }
</style>

<div id="pdp">

  <!-- ===== Hero: Gallery + Info ===== -->
  <div class="hero">
    <!-- Gallery -->
    <div class="media">
      <div class="gallery" id="gal" data-index="0">
        <div class="slides" id="slides">
          <?php foreach ($images as $i => $img): ?>
            <div class="slide"><img src="<?php echo h($img); ?>" alt="<?php echo h($p['name']); ?>"></div>
          <?php endforeach; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <button class="nav prev" id="prev" aria-label="পূর্ববর্তী"><i class="fa-solid fa-chevron-left"></i></button>
          <button class="nav next" id="next" aria-label="পরবর্তী"><i class="fa-solid fa-chevron-right"></i></button>
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="thumbs" id="thumbs">
          <?php foreach ($images as $i => $img): ?>
            <img src="<?php echo h($img); ?>" data-idx="<?php echo $i; ?>" class="<?php echo $i===0?'active':''; ?>" alt="thumb <?php echo $i+1; ?>">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div class="info">
      <h1><?php echo h($p['name']); ?></h1>

      <div class="chips">
        <?php if($hasB1G1): ?><span class="chip purple"><i class="fa-solid fa-gift"></i> Buy 1 Get 1</span><?php endif; ?>

        <?php if($isOut): ?>
          <span class="chip red"><i class="fa-solid fa-circle-xmark"></i> স্টক নেই</span>
        <?php elseif($isLimited): ?>
          <span class="chip orange"><i class="fa-solid fa-triangle-exclamation"></i> স্টক সীমিত</span>
        <?php else: ?>
          <span class="chip green"><i class="fa-solid fa-check"></i> স্টকে আছে</span>
        <?php endif; ?>

        <?php if($hasFree): ?><span class="chip blue"><i class="fa-solid fa-truck"></i> Free Shipping</span><?php endif; ?>
        <span class="chip"><i class="fa-solid fa-money-bill-wave"></i> COD প্রযোজ্য</span>
      </div>
	  
      <span class="cat">ক্যাটেগরি :<?php echo h($p['cat_name'] ?: ''); ?></span>
	  
	  <!-- NEW: Tag badges (ABOVE price row) -->
      <?php if (!empty($tagItems)): ?>
        <div class="tags" aria-label="ট্যাগ">
          <?php foreach ($tagItems as $tg): ?>
            <span class="badge"><i class="fa-solid fa-tag"></i> <?php echo h($tg); ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
	  
      <div class="price-row">
        <div class="price"><?php echo money_bd($p['price']); ?></div>
        <?php if(!empty($p['compare_at_price'])): ?><div class="old"><?php echo money_bd($p['compare_at_price']); ?></div><?php endif; ?>
      </div>

      <div class="action-row">
        <div class="qty <?php echo $isOut ? 'disabled' : ''; ?>">
          <button type="button" id="btnDec" aria-label="কমান" <?php echo $isOut ? 'disabled' : ''; ?>>−</button>
          <input id="qty" value="1" inputmode="numeric" aria-label="পরিমাণ" <?php echo $isOut ? 'disabled' : ''; ?>>
          <button type="button" id="btnInc" aria-label="বাড়ান" <?php echo $isOut ? 'disabled' : ''; ?>>+</button>
        </div>
      </div>

      <div class="action-row">
        <!-- Add to cart -->
        <button class="btn primary" id="btnAdd"
                <?php echo $isOut ? 'disabled aria-disabled="true"' : ''; ?>
                <?php if(!$isOut): ?>onclick="addToCart(<?php echo (int)$p['id']; ?>, getQty())"<?php endif; ?>>
          <i class="fa-solid fa-cart-plus"></i> ADD TO CART
        </button>

        <!-- Buy Now -->
        <?php if($isOut): ?>
          <a class="btn ghost disabled" id="btnBuy" aria-disabled="true" role="button" tabindex="-1">
            <i class="fa-solid fa-bolt"></i> BUY NOW
          </a>
        <?php else: ?>
          <a class="btn ghost" id="btnBuy" href="checkout.php?buy=<?php echo (int)$p['id']; ?>&qty=1">
            <i class="fa-solid fa-bolt"></i> BUY NOW
          </a>
        <?php endif; ?>
      </div>

      <?php $phone = defined('SHOP_PHONE') ? SHOP_PHONE : (defined('CONTACT_PHONE') ? CONTACT_PHONE : null); ?>
      <?php if ($phone): ?>
        <div class="call">
          <i class="fa-solid fa-phone"></i>
          <strong>Call For Order:</strong> <?php echo h($phone); ?>
        </div>
      <?php endif; ?>
    </div>
  </div><!-- /hero -->

  <!-- ===== Tabs (Full width below Hero) ===== -->
  <div class="tabs" role="tablist">
    <button class="tab active" data-tab="desc" role="tab" aria-selected="true">Description</button>
    <button class="tab" data-tab="spec" role="tab" aria-selected="false">Specification</button>
  </div>
  <div class="panel active" id="tab-desc" role="tabpanel">
    <?php echo $p['description'] ? nl2br(h($p['description'])) : 'পণ্যের বর্ণনা প্রদান করা হয়নি।'; ?>
  </div>
  <div class="panel" id="tab-spec" role="tabpanel">
    <?php if ($specsRows): ?>
      <table class="specs"><tbody>
        <?php foreach($specsRows as $row): ?>
          <tr><th><?php echo h($row[0]); ?></th><td><?php echo nl2br(h($row[1])); ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php elseif ($rawSpecs!==''): ?>
      <?php echo nl2br(h($rawSpecs)); ?>
    <?php else: ?>স্পেসিফিকেশন পাওয়া যায়নি।<?php endif; ?>
  </div>

  <!-- ===== Related products (after Tabs) ===== -->
  <?php if ($related): ?>
    <div class="rel-head">
      <h3 style="margin:0;">Related products</h3>
      <?php if (!empty($p['category_id'])): ?>
        <a class="rel-link" href="index.php?cat=<?php echo (int)$p['category_id']; ?>">More Products →</a>
      <?php endif; ?>
    </div>
    <div class="rel-wrap">
      <div class="rel-row">
        <?php foreach($related as $r): ?>
          <a class="rel-card" href="product.php?id=<?php echo (int)$r['id']; ?>">
            <img class="thumb" loading="lazy" decoding="async" src="<?php echo h($r['image']); ?>" alt="<?php echo h($r['name']); ?>">
            <div class="body">
              <div class="ttl"><?php echo h($r['name']); ?></div>
              <div><span class="pr"><?php echo money_bd($r['price']); ?></span>
                <?php if(!empty($r['compare_at_price'])): ?><span class="old"><?php echo money_bd($r['compare_at_price']); ?></span><?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- Expose OOS flag early -->
<script>
  window.OUT_OF_STOCK = <?php echo $isOut ? 'true' : 'false'; ?>;
</script>

<script>
  // Qty + Buy Now href update
  function getQty(){ const v=parseInt(document.getElementById('qty').value); return Math.max(1, isNaN(v)?1:v); }
  function refreshBuyLink(){
    if (window.OUT_OF_STOCK) return; // স্টক না থাকলে href আপডেট নয়
    var q=getQty();
    var buy=document.getElementById('btnBuy');
    if(buy){ buy.href='checkout.php?buy=<?php echo (int)$p['id']; ?>&qty='+q; }
  }
  document.getElementById('btnInc')?.addEventListener('click', ()=>{ const i=document.getElementById('qty'); i.value=(parseInt(i.value)||1)+1; refreshBuyLink(); });
  document.getElementById('btnDec')?.addEventListener('click', ()=>{ const i=document.getElementById('qty'); i.value=Math.max(1,(parseInt(i.value)||1)-1); refreshBuyLink(); });
  document.getElementById('qty')?.addEventListener('input', refreshBuyLink);
  document.addEventListener('DOMContentLoaded', refreshBuyLink);

  // Tabs
  document.querySelectorAll('#pdp .tab').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('#pdp .tab').forEach(b=>b.classList.remove('active'));
      document.querySelectorAll('#pdp .panel').forEach(p=>p.classList.remove('active'));
      btn.classList.add('active');
      document.querySelector(btn.dataset.tab === 'desc' ? '#tab-desc' : '#tab-spec').classList.add('active');
    });
  });

  // Slider
  (function(){
    const slides=document.getElementById('slides'); if(!slides) return;
    const thumbs=document.getElementById('thumbs'); const prev=document.getElementById('prev'); const next=document.getElementById('next');
    const total=slides.children.length; let idx=0;
    function go(i){
      idx=Math.max(0, Math.min(total-1, i));
      slides.style.transform='translateX(' + (-idx*100) + '%)';
      if(thumbs){ [...thumbs.querySelectorAll('img')].forEach((t,ti)=>t.classList.toggle('active', ti===idx)); }
      if(prev) prev.disabled=(idx===0); if(next) next.disabled=(idx===total-1);
    }
    if(prev) prev.addEventListener('click', ()=>go(idx-1));
    if(next) next.addEventListener('click', ()=>go(idx+1));
    if(thumbs){ thumbs.addEventListener('click', e=>{ const t=e.target.closest('img[data-idx]'); if(!t) return; go(parseInt(t.dataset.idx)||0); }); }
    let startX=null;
    slides.addEventListener('touchstart', e=>{ startX=e.touches[0].clientX; }, {passive:true});
    slides.addEventListener('touchmove', e=>{ if(startX==null) return; const dx=e.touches[0].clientX-startX; if(Math.abs(dx)>50){ go(idx+(dx<0?1:-1)); startX=null; } }, {passive:true});
    go(0);
  })();
</script>

<?php include __DIR__.'/partials_footer.php'; ?>
