<?php
require_once __DIR__.'/config.php';
$pdo = get_pdo();

$id  = (int)($_GET['id'] ?? 0);
$stm = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$stm->execute([$id]);
$o = $stm->fetch();

/* ========== Settings / Support ========== */
$store_phone = (string)($pdo->query("SELECT value FROM settings WHERE `key`='store_phone'")->fetch()['value'] ?? '');
$ship_dhaka  = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_dhaka'")->fetch()['value'] ?? 60);
$ship_nat    = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_nationwide'")->fetch()['value'] ?? 120);

/* ========== BN helpers ========== */
function bn_digits($s){
  return strtr((string)$s, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯', ','=>'٬']);
}
function bn_money($n){
  $n = round((float)$n);
  $s = number_format($n, 0, '.', ',');     // 11,565
  return '৳' . bn_digits($s);
}
function dateBD($ts){
  return bn_digits(date('d M, Y g:i A', is_numeric($ts)? (int)$ts : strtotime($ts)));
}

/* ========== Status badge map ========== */
function status_view($raw){
  $s = strtolower(trim((string)$raw));
  $map = [
    'pending'    => ['পেন্ডিং','badge gray'],
    'processing' => ['প্রসেসিং','badge blue'],
    'packed'     => ['প্যাকড','badge blue'],
    'shipped'    => ['শিপড','badge indigo'],
    'delivered'  => ['ডেলিভারড','badge green'],
    'cancelled'  => ['ক্যানসেল্ড','badge red'],
    'canceled'   => ['ক্যানসেল্ড','badge red'],
    'returned'   => ['রিটার্নড','badge orange'],
  ];
  return $map[$s] ?? [($raw ?: 'অজানা'), 'badge gray'];
}

/* ========== Totals (fallback friendly) ========== */
$ship_area_raw = strtolower(trim($o['shipping_area'] ?? $o['shipping_area_code'] ?? ''));
$ship_fee      = (float)($o['shipping_fee'] ?? $o['delivery_fee'] ?? $o['delivery_charge'] ?? 0);

if ($ship_fee <= 0) {
  if (in_array($ship_area_raw, ['nationwide','outside_dhaka','outside-dhaka'])) {
    $ship_fee = $ship_nat;
  } else {
    // default dhaka
    $ship_fee = $ship_dhaka;
    $ship_area_raw = $ship_area_raw ?: 'dhaka';
  }
}
$ship_area_label = (in_array($ship_area_raw, ['nationwide','outside_dhaka','outside-dhaka'])) ? 'সারা দেশে' : 'ঢাকার ভিতরে';

$grand_total = (float)($o['grand_total'] ?? $o['total'] ?? $o['payable'] ?? $o['amount'] ?? 0);
$subtotal    = $o['subtotal'] ?? $o['sub_total'] ?? $o['items_total'] ?? $o['total_without_shipping'] ?? null;
if ($subtotal === null && $grand_total > 0) {
  $subtotal = max(0, $grand_total - $ship_fee);
}
$subtotal = (float)$subtotal;
if ($grand_total <= 0) $grand_total = $subtotal + $ship_fee;

[$status_text, $status_class] = status_view($o['status'] ?? '');

include __DIR__.'/partials_header.php';
?>

<style>
  :root{
    --brand:#16a34a; /* success */
    --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --white:#fff;
    --radius:16px; --shadow:0 12px 28px rgba(2,8,23,.08);
  }
  #od{ max-width:760px; margin:22px auto 28px; padding:0 14px; }
  #od .card{
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow); padding:18px;
  }
  #od .head{ display:flex; align-items:center; gap:12px; margin-bottom:10px; }
  #od .tick{
    width:56px; height:56px; border-radius:999px; background:#dcfce7; display:grid; place-items:center; flex:0 0 auto;
    border:1px solid #bbf7d0;
  }
  #od .tick svg{ width:30px; height:30px; color:var(--brand); }
  #od h2{ margin:0; color:var(--brand); font-size:clamp(1.2rem,1.4vw + .9rem,1.8rem); }
  #od p.lead{ margin:4px 0 12px; color:#334155; }

  #od .rows{ display:grid; gap:10px; margin:10px 0 14px; }
  #od .row{ display:flex; justify-content:space-between; gap:10px; }
  #od .row .lbl{ color:#475569; font-weight:600; }
  #od .chip{
    display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px;
    background:#f8fafc; border:1px solid var(--border); font-weight:700;
  }
  #od .chip.cod{ background:#ecfeff; border-color:#bae6fd; color:#0369a1; }

  /* badges for status */
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border:1px solid var(--border); border-radius:999px; font-weight:700; background:#fff; }
  .badge.gray{ color:#334155; }
  .badge.blue{ color:#1e40af; background:#eff6ff; border-color:#dbeafe; }
  .badge.indigo{ color:#3730a3; background:#eef2ff; border-color:#e0e7ff; }
  .badge.green{ color:#065f46; background:#ecfdf5; border-color:#d1fae5; }
  .badge.red{ color:#b91c1c; background:#fee2e2; border-color:#fecaca; }
  .badge.orange{ color:#9a3412; background:#fff7ed; border-color:#fed7aa; }

  #od .btnbar{ display:flex; gap:10px; flex-wrap:wrap; margin-top:8px; }
  #od .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px;
           padding:0 16px; border-radius:12px; font-weight:700; text-decoration:none; cursor:pointer; }
  #od .btn.primary{ background:#0ea5e9; color:#fff; border:none; }
  #od .btn.primary:hover{ background:#0284c7; }
  #od .btn.secondary{ background:#fff; border:1px solid var(--border); color:#0f172a; }
  #od .btn.secondary:hover{ box-shadow:0 6px 14px rgba(2,8,23,.06); }

  #od .help{ margin-top:10px; color:var(--muted); }
  #od .copy{ border:none; background:#fff; border:1px solid var(--border); padding:6px 10px; border-radius:10px; cursor:pointer; }
  #od .copy:active{ transform:translateY(1px); }

  /* মোবাইলে বোতাম ফুল-উইডথ */
  @media (max-width: 520px){
    #od .btn{ flex:1 1 100%; }
    #od .row{ flex-direction:column; align-items:flex-start; }
  }
</style>

<div id="od">
  <?php if(!$o): ?>
    <div class="card">
      <div class="head">
        <div class="tick">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z" stroke="#16a34a" stroke-width="1.4"/></svg>
        </div>
        <h2>অর্ডার পাওয়া যায়নি</h2>
      </div>
      <p class="lead">লিংকটি সঠিক নয় বা অর্ডারটি নেই।</p>
      <div class="btnbar">
        <a class="btn primary" href="index.php">শপিং করুন</a>
        <a class="btn secondary" href="index.php">হোমে ফিরুন</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="head">
        <div class="tick">
          <!-- success icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M8 12l3 3 5-6"></path>
          </svg>
        </div>
        <h2>অর্ডার সফল!</h2>
      </div>

      <p class="lead">আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে। ধন্যবাদ ✅</p>

      <div class="rows">
        <div class="row">
          <div class="lbl">অর্ডার আইডি</div>
          <div>
            <span class="chip" id="orderCode" data-code="<?php echo h($o['order_code']); ?>">
              #<?php echo h($o['order_code']); ?>
            </span>
            <button class="copy" id="btnCopy" type="button">কপি</button>
          </div>
        </div>

        <!-- নতুন: অর্ডার তারিখ -->
        <?php if(!empty($o['created_at'])): ?>
        <div class="row">
          <div class="lbl">তারিখ</div>
          <div><?php echo dateBD($o['created_at']); ?></div>
        </div>
        <?php endif; ?>

        <!-- নতুন: স্ট্যাটাস -->
        <div class="row">
          <div class="lbl">স্ট্যাটাস</div>
          <div><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></div>
        </div>

        <!-- নতুন: শিপিং (এলাকা + ফি) -->
        <div class="row">
          <div class="lbl">শিপিং</div>
          <div>
            <?php echo $ship_area_label; ?> (<?php echo bn_money($ship_fee); ?>)
          </div>
        </div>

        <!-- নতুন: সাবটোটাল -->
        <div class="row">
          <div class="lbl">সাবটোটাল</div>
          <div><?php echo bn_money($subtotal); ?></div>
        </div>

        <!-- নতুন: মোট -->
        <div class="row">
          <div class="lbl">মোট</div>
          <div><strong><?php echo bn_money($grand_total); ?></strong></div>
        </div>

        <?php if ($store_phone): ?>
        <div class="row">
          <div class="lbl">সাপোর্ট</div>
          <div><a href="tel:<?php echo h($store_phone); ?>" class="chip">📞 <?php echo h($store_phone); ?></a></div>
        </div>
        <?php endif; ?>
      </div>

      <div class="btnbar">
        <a class="btn primary" href="index.php">আবার কেনাকাটা করুন</a>
        <a class="btn secondary" href="index.php">হোমে ফিরুন</a>
        <button class="btn secondary" type="button" onclick="window.print()">প্রিন্ট</button>
      </div>

      <p class="help">অর্ডার সংক্রান্ত সাহায্যের জন্য উপরের সাপোর্ট নম্বরে কল করুন।</p>
    </div>
  <?php endif; ?>
</div>

<script>
  // অর্ডার আইডি কপি
  (function(){
    const btn = document.getElementById('btnCopy');
    const codeEl = document.getElementById('orderCode');
    if(!btn || !codeEl) return;
    btn.addEventListener('click', async ()=>{
      try{
        await navigator.clipboard.writeText(codeEl.dataset.code || '');
        btn.textContent = 'কপি হয়েছে';
        setTimeout(()=>btn.textContent='কপি', 1400);
      }catch(e){
        btn.textContent = 'কপি ব্যর্থ';
        setTimeout(()=>btn.textContent='কপি', 1400);
      }
    });
  })();
</script>

<?php include __DIR__.'/partials_footer.php'; ?>
