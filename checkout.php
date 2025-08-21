<?php
require_once __DIR__ . '/config.php';
$pdo = get_pdo();

/* ---- "Buy Now" থেকে এলে কার্ট সেট ---- */
if (isset($_GET['buy'])) {
    $buyId = (int)$_GET['buy'];
    $qty   = max(1, (int)($_GET['qty'] ?? 1));
    $_SESSION['cart'] = [$buyId => $qty];
}

/* ---- কার্ট আইটেম ---- */
$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    include __DIR__ . '/partials_header.php';
    echo '<h2>চেকআউট</h2><p>আপনার কার্ট খালি। <a class="btn secondary" href="index.php">শপিং শুরু করুন</a></p>';
    include __DIR__ . '/partials_footer.php';
    exit;
}

$ids  = implode(',', array_map('intval', array_keys($cart)));
$rows = $pdo->query("SELECT id, name, price, image FROM products WHERE id IN ($ids) AND active=1")->fetchAll();

$items = [];
$subtotal = 0.0;
foreach ($rows as $r) {
    $pid  = (int)$r['id'];
    $qty  = max(1, (int)$cart[$pid]);
    $line = $qty * (float)$r['price'];
    $subtotal += $line;
    $items[] = ['p' => $r, 'qty' => $qty, 'line' => $line];
}

/* ---- শিপিং সেটিংস ---- */
$ship_dhaka = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_dhaka'")->fetch()['value'] ?? 60);
$ship_nat   = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_nationwide'")->fetch()['value'] ?? 120);

/* ---- বাংলা অঙ্ক/টাকা ফরম্যাট (PHP side) ---- */
function bn_digits($s){
  return strtr((string)$s, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯', ','=>'٬']);
}
function bn_money($n){
  $n = round((float)$n);
  $s = number_format($n, 0, '.', ',');   // 11,565
  return '৳' . bn_digits($s);
}
function bn_num($n){ return bn_digits($n); }

include __DIR__ . '/partials_header.php';
?>

<style>
  :root{
    --brand:#0ea5e9; --brand-600:#0284c7;
    --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --bg:#f6f8fb; --white:#fff;
    --radius:14px; --shadow:0 10px 24px rgba(2,8,23,.08);
  }
  #co{ max-width:1100px; margin:18px auto 28px; padding:0 14px; }
  #co h2{ margin:6px 0 14px; }

  /* ====== layout: Mobile → summary first; Desktop → form left, summary right ====== */
  #co .layout{
    display:grid; gap:16px;
    grid-template-columns:1fr;
    grid-template-areas:
      "summary"
      "form";
  }
  @media (min-width: 768px){
    #co .layout{
      grid-template-columns:1.2fr .8fr; gap:22px;
      grid-template-areas: "form summary";
      align-items:start;
    }
  }
  .formWrap{ grid-area: form; }
  .summaryWrap{ grid-area: summary; }

  /* card */
  #co .card{
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:0 2px 6px rgba(2,8,23,.02);
    padding:14px;
  }
  @media (min-width: 768px){
    #co .summaryWrap{ position:sticky; top:14px; }
  }
  #co hr{ border:none; border-top:1px solid var(--border); margin:10px 0; }

  /* shipping options – segmented cards with custom radio */
  #co .ship{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  #co .opt{
    position:relative; display:flex; align-items:center; justify-content:space-between;
    gap:12px; padding:12px 14px; border:1px solid var(--border); border-radius:12px;
    background:#fff; cursor:pointer; transition:border .2s, box-shadow .2s, background .2s;
    user-select:none;
  }
  #co .opt input{ position:absolute; inset:0; opacity:0; cursor:pointer; }
  #co .opt .ttl{ font-weight:800; color:#0f172a; }
  #co .opt .sub{ font-weight:700; color:#475569; }
  #co .opt .radio{
    width:22px; height:22px; border-radius:999px; border:2px solid #94a3b8; flex:0 0 auto; position:relative;
    box-shadow:inset 0 0 0 2px #fff;
  }
  #co .opt.active{ border-color:#cfe3ff; background:#f8fbff; box-shadow:var(--shadow); }
  #co .opt.active .radio{ border-color:var(--brand); }
  #co .opt.active .radio::after{
    content:''; position:absolute; inset:4px; background:var(--brand); border-radius:999px;
  }

  /* form */
  #co .form-group{ margin:10px 0; }
  #co label{ font-weight:700; color:#334155; display:block; margin-bottom:6px; }
  #co input, #co textarea{
    width:100%; background:#fff; border:1px solid var(--border); border-radius:10px;
    min-height:44px; padding:10px 12px; font-size:16px; outline:none;
  }
  #co textarea{ min-height:88px; resize:vertical; }
  #co input:focus, #co textarea:focus{ border-color:#cfe3ff; box-shadow:0 0 0 4px #e6f0ff; }

  /* buttons */
  #co .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px;
    min-height:46px; padding:0 16px; border-radius:12px; font-weight:700; cursor:pointer; text-decoration:none; }
  #co .btn.primary{ background:var(--brand); color:#fff; border:none; }
  #co .btn.primary:hover{ background:var(--brand-600); }
  #co .notice{ background:#f8fafc; border:1px solid var(--border); padding:10px 12px; border-radius:10px; }

  /* order list */
  #co .olist{ list-style:none; padding:0; margin:0; display:grid; gap:10px; }
  #co .row{ display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
  #co .itm{ display:flex; align-items:center; gap:10px; }
  #co .thumb{ width:44px; height:44px; border:1px solid var(--border); border-radius:8px; object-fit:cover; background:#fff; }
  #co .money{ font-weight:800; }

  /* mobile tweaks */
  @media (max-width: 480px){
    #co .ship{ grid-template-columns:1fr; }
    #co .btn{ width:100%; }
  }
</style>

<div id="co">
  <h2>চেকআউট সম্পূর্ণ করুন</h2>

  <div class="layout">
    <!-- ===== Mobile first: Summary on top ===== -->
    <div class="card summaryWrap">
      <h3 style="margin:4px 0 10px;">অর্ডার সামারি</h3>

      <ul class="olist">
        <?php foreach ($items as $it): ?>
          <li class="row">
            <div class="itm">
              <img class="thumb" src="<?php echo h($it['p']['image'] ?: 'assets/placeholder.jpg'); ?>" alt="">
              <div>
                <div><?php echo h($it['p']['name']); ?></div>
                <small>× <?php echo bn_num((int)$it['qty']); ?></small>
              </div>
            </div>
            <div class="money"><?php echo bn_money($it['line']); ?></div>
          </li>
        <?php endforeach; ?>
      </ul>

      <hr>
      <div class="row">
        <div>সাবটোটাল</div>
        <div id="sumSubtotal" data-subtotal="<?php echo (float)$subtotal; ?>">
          <?php echo bn_money($subtotal); ?>
        </div>
      </div>

      <div class="row">
        <div>ডেলিভারি চার্জ</div>
        <div id="sumShip" data-dhaka="<?php echo (float)$ship_dhaka; ?>" data-nat="<?php echo (float)$ship_nat; ?>">
          <?php echo bn_money($ship_dhaka); ?>
        </div>
      </div>

      <hr>
      <div class="row" style="font-weight:800">
        <div>মোট</div>
        <div id="sumTotal"><?php echo bn_money($subtotal + $ship_dhaka); ?></div>
      </div>

      <p class="small" style="margin-top:8px">শিপিং এরিয়া বদলালে টোটাল আপডেট হবে।</p>
    </div>

    <!-- ===== Form (desktop left) ===== -->
    <div class="card formWrap">
      <form method="post" action="place_order.php" id="checkoutForm" novalidate>
        <div class="ship" id="shipWrap">
          <label class="opt active">
            <input type="radio" name="shipping_area" value="dhaka" checked>
            <div>
              <div class="ttl">ঢাকার ভিতরে</div>
              <div class="sub"><?php echo bn_money($ship_dhaka); ?></div>
            </div>
            <span class="radio" aria-hidden="true"></span>
          </label>

          <label class="opt">
            <input type="radio" name="shipping_area" value="nationwide">
            <div>
              <div class="ttl">সারা দেশে</div>
              <div class="sub"><?php echo bn_money($ship_nat); ?></div>
            </div>
            <span class="radio" aria-hidden="true"></span>
          </label>
        </div>

        <div class="form-group" style="margin-top:10px">
          <label>নাম *</label>
          <input name="customer_name" required>
        </div>

        <div class="form-group">
          <label>মোবাইল নম্বর *</label>
          <input name="mobile" required placeholder="01XXXXXXXXX" pattern="01[0-9]{9}">
        </div>

        <div class="form-group">
          <label>ঠিকানা *</label>
          <textarea name="address" rows="3" required></textarea>
        </div>

        <div class="form-group">
          <label>নোট (ঐচ্ছিক)</label>
          <textarea name="note" rows="3"></textarea>
        </div>

        <div class="notice"><strong>পেমেন্ট মেথড:</strong> ক্যাশ অন ডেলিভারি (COD)</div>
        <br>
        <button class="btn primary" type="submit">অর্ডার করুন</button>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  // বাংলা অঙ্কে টাকা (JS side)
  function moneyBD(n){
    n = Math.round(Number(n)||0);
    return '৳' + n.toLocaleString('bn-BD');
  }

  const subtotalEl = document.getElementById('sumSubtotal');
  const shipEl     = document.getElementById('sumShip');
  const totalEl    = document.getElementById('sumTotal');
  const radios     = document.querySelectorAll('input[name="shipping_area"]');

  function recalc(){
    const subtotal = Number(subtotalEl?.dataset.subtotal || 0);
    const d = Number(shipEl?.dataset.dhaka || 0);
    const n = Number(shipEl?.dataset.nat   || 0);
    let ship = d;
    const checked = document.querySelector('input[name="shipping_area"]:checked');
    if (checked && checked.value === 'nationwide') ship = n;

    if (shipEl)  shipEl.textContent  = moneyBD(ship);
    if (totalEl) totalEl.textContent = moneyBD(subtotal + ship);
  }

  // নির্বাচিত অপশনে active স্টাইল
  function syncActive(){
    document.querySelectorAll('#co .opt').forEach(l=>l.classList.remove('active'));
    const checked = document.querySelector('input[name="shipping_area"]:checked');
    checked?.closest('.opt')?.classList.add('active');
  }

  radios.forEach(r => r.addEventListener('change', ()=>{ recalc(); syncActive(); }));
  recalc(); syncActive();
})();
</script>

<?php include __DIR__ . '/partials_footer.php'; ?>
