<?php
// admin/order_edit.php — Edit order with qty stepper + live totals (mobile & PC friendly)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* ---------- Helpers (fallback) ---------- */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_bd')) {
  function money_bd($n){ $n=(float)$n; $n=round($n); return '৳'.number_format($n,0,'',','); }
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ---------- Allowed statuses & shipping areas ---------- */
$STATUSES = ['pending','processing','shipped','delivered','cancelled'];
$AREAS    = ['dhaka' => 'Dhaka', 'nationwide' => 'Nationwide'];

/* ---------- Load order ---------- */
$id       = (int)($_GET['id'] ?? 0);
$returnQS = trim($_GET['return'] ?? '');

$stm = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$stm->execute([$id]);
$o = $stm->fetch();
if (!$o) {
  $_SESSION['flash'] = ['ok'=>false,'message'=>'Order not found'];
  header('Location: orders.php'); exit;
}

/* ---------- Load items ---------- */
$it = $pdo->prepare("
  SELECT oi.id, oi.product_id, oi.qty, oi.price, p.name
  FROM order_items oi
  LEFT JOIN products p ON p.id=oi.product_id
  WHERE oi.order_id=?
  ORDER BY oi.id
");
$it->execute([$id]);
$items = $it->fetchAll();

/* ---------- Handle Save (POST) BEFORE HTML ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = ['ok'=>false,'message'=>'CSRF যাচাইকরণ ব্যর্থ হয়েছে'];
    header('Location: orders.php'.($returnQS ? '?'.$returnQS : '')); exit;
  }

  $customer_name = trim($_POST['customer_name'] ?? '');
  $mobile        = trim($_POST['mobile'] ?? '');
  $address       = trim($_POST['address'] ?? '');
  $note          = trim($_POST['note'] ?? '');
  $shipping_area = $_POST['shipping_area'] ?? 'dhaka';
  $shipping_fee  = (float)($_POST['shipping_fee'] ?? 0);
  $status        = $_POST['status'] ?? $o['status'];

  // Basic validations
  $err = '';
  if ($customer_name === '') $err = 'গ্রাহকের নাম প্রয়োজন';
  elseif ($mobile === '')    $err = 'মোবাইল নম্বর প্রয়োজন';
  elseif (!preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $mobile)) $err = 'সঠিক বাংলাদেশি মোবাইল নম্বর দিন';
  elseif (!in_array($status, $STATUSES, true)) $err = 'অবৈধ স্ট্যাটাস';

  // Qty array
  $qtyPosted = $_POST['qty'] ?? []; // key = order_item_id, value = qty

  if ($err !== '') {
    $_SESSION['flash'] = ['ok'=>false,'message'=>$err];
    header('Location: order_edit.php?id='.$id.'&return='.urlencode($returnQS)); exit;
  }

  try{
    $pdo->beginTransaction();

    // 1) Update basic order fields (except totals for now)
    $upd1 = $pdo->prepare("
      UPDATE orders
      SET customer_name=?, mobile=?, address=?, note=?, shipping_area=?, shipping_fee=?, status=?
      WHERE id=?
    ");
    $upd1->execute([$customer_name, $mobile, $address, $note, $shipping_area, $shipping_fee, $status, $id]);

    // 2) Update each item's qty (min 1)
    if (is_array($qtyPosted)) {
      $u = $pdo->prepare("UPDATE order_items SET qty=? WHERE id=? AND order_id=?");
      foreach ($qtyPosted as $oiId => $q) {
        $oiId = (int)$oiId;
        $q    = max(1, (int)$q);
        $u->execute([$q, $oiId, $id]);
      }
    }

    // 3) Recalculate subtotal from DB (safe)
    $sum = $pdo->prepare("SELECT SUM(qty * price) FROM order_items WHERE order_id=?");
    $sum->execute([$id]);
    $subtotal = (float)$sum->fetchColumn();
    $total    = $subtotal + $shipping_fee;

    // 4) Save totals
    $upd2 = $pdo->prepare("UPDATE orders SET subtotal=?, total=? WHERE id=?");
    $upd2->execute([$subtotal, $total, $id]);

    $pdo->commit();

    $_SESSION['flash'] = ['ok'=>true,'message'=>'অর্ডার সফলভাবে আপডেট হয়েছে।'];
    header('Location: orders.php'.($returnQS ? '?'.$returnQS : '')); exit;
  } catch(Throwable $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['ok'=>false,'message'=>'সার্ভার ত্রুটি: আপডেট ব্যর্থ।'];
    header('Location: order_edit.php?id='.$id.'&return='.urlencode($returnQS)); exit;
  }
}

/* ---------- Render page ---------- */
require_once __DIR__ . '/header.php';
?>
<style>
  .o-card{ margin-bottom: 16px; }
  .qtyBox{
    display:grid; grid-template-columns:38px 60px 38px; align-items:center;
    border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff;
  }
  .qtyBox input{ height:38px; text-align:center; border:none; border-inline:1px solid #e5e7eb; width:100%; }
  .qtyBox button{ height:38px; border:none; background:#f8fafc; font-size:18px; cursor:pointer; }
  .qtyBox button:hover{ background:#f1f5f9; }
  .summary-kv .k{ color:#64748b; }
  .summary-kv .v{ font-weight:700; text-align:right; }
  @media (max-width:576px){
    .table-responsive{ border:0 }
    table.table{ display:block }
    table.table thead{ display:none }
    table.table tbody{ display:block }
    table.table tr{
      display:block; background:#fff; border:1px solid #e9ecef; border-radius:12px;
      padding:12px; margin-bottom:10px; box-shadow:0 6px 16px rgba(2,8,23,.04);
    }
    table.table td{
      display:flex; justify-content:space-between; gap:12px;
      padding:6px 0 !important; border:0 !important;
    }
    table.table td::before{
      content:attr(data-label);
      font-weight:600; color:#64748b; min-width:120px; text-align:left;
    }
  }
</style>

<?php if(!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?php echo $f['ok']?'success':'danger'; ?> alert-dismissible fade show" role="alert">
    <?php echo h($f['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">Edit Order — #<?php echo h($o['order_code']); ?></h5>
  <div class="d-flex gap-2">
    <a class="btn btn-secondary"
       href="orders.php<?php echo $returnQS ? ('?'.h($returnQS)) : ''; ?>">Back to Orders</a>
    <a class="btn btn-outline-primary" target="_blank"
       href="order_print.php?id=<?php echo (int)$o['id']; ?>">Print</a>
  </div>
</div>

<form method="post" id="editForm" class="row">
  <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
  <input type="hidden" name="save" value="1">
  <input type="hidden" name="return" value="<?php echo h($returnQS); ?>">

  <!-- Left: Customer -->
  <div class="col-lg-6 o-card">
    <div class="card"><div class="card-body">
      <h6 class="mb-3">গ্রাহকের তথ্য</h6>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">নাম</label>
          <input name="customer_name" class="form-control" required value="<?php echo h($o['customer_name']); ?>">
        </div>
        <div class="col-12">
          <label class="form-label">মোবাইল</label>
          <input name="mobile" class="form-control" required
                 pattern="^(?:\+?88)?01[3-9]\d{8}$"
                 title="বাংলাদেশি মোবাইল: 01XXXXXXXXX বা +8801XXXXXXXXX"
                 value="<?php echo h($o['mobile']); ?>">
        </div>
        <div class="col-12">
          <label class="form-label">ঠিকানা</label>
          <textarea name="address" class="form-control" rows="3" required><?php echo h($o['address']); ?></textarea>
        </div>

        <!-- ✅ ঠিকানা’র নিচে নোট -->
        <div class="col-12">
          <label class="form-label">নোট</label>
          <textarea name="note" class="form-control" rows="2" placeholder="অর্ডার সংক্রান্ত নোট..."><?php echo h($o['note']); ?></textarea>
        </div>
      </div>
    </div></div>
  </div>

  <!-- Right: Shipping/Status & Summary -->
  <div class="col-lg-6 o-card">
    <div class="card"><div class="card-body">
      <h6 class="mb-3">শিপিং ও স্ট্যাটাস</h6>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">শিপিং এলাকা</label>
          <select name="shipping_area" class="form-select">
            <?php foreach($AREAS as $k=>$v): ?>
              <option value="<?php echo h($k); ?>" <?php echo $o['shipping_area']===$k?'selected':''; ?>>
                <?php echo h($v); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">শিপিং ফি</label>
          <input type="number" step="0.01" name="shipping_fee" id="shippingFee" class="form-control"
                 value="<?php echo h($o['shipping_fee']); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">স্ট্যাটাস</label>
          <select name="status" class="form-select" required>
            <?php foreach($STATUSES as $s): ?>
              <option value="<?php echo h($s); ?>" <?php echo $o['status']===$s?'selected':''; ?>>
                <?php echo ucfirst($s); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">পেমেন্ট</label>
          <input class="form-control" value="<?php echo h($o['payment_method']); ?>" readonly>
        </div>
      </div>

      <hr class="my-3">

      <h6 class="mb-2">অর্ডার সারসংক্ষেপ</h6>
      <div class="row summary-kv small gy-1">
        <div class="col-6 k">Subtotal</div><div class="col-6 v" id="sumSubtotal"><?php echo money_bd($o['subtotal']); ?></div>
        <div class="col-6 k">Shipping</div><div class="col-6 v" id="sumShipping"><?php echo money_bd($o['shipping_fee']); ?></div>
        <div class="col-12"><hr class="my-2"></div>
        <div class="col-6 k">Total</div><div class="col-6 v" id="sumTotal" style="font-weight:800"><?php echo money_bd($o['total']); ?></div>
      </div>
    </div></div>
  </div>

  <!-- Items -->
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="mb-3">আইটেমসমূহ</h6>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0" id="itemsTable">
          <thead class="table-light">
            <tr>
              <th>পণ্য</th>
              <th class="text-end">দাম</th>
              <th class="text-center">পরিমাণ</th>
              <th class="text-end">লাইন</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($items as $r): ?>
              <tr data-id="<?php echo (int)$r['id']; ?>">
                <td data-label="পণ্য"><?php echo h($r['name']); ?></td>
                <td data-label="দাম" class="text-end priceCell" data-price="<?php echo (float)$r['price']; ?>">
                  <?php echo money_bd($r['price']); ?>
                </td>
                <td data-label="পরিমাণ" class="text-center">
                  <div class="qtyBox">
                    <button type="button" class="btnDec" aria-label="কমান">−</button>
                    <input type="number" name="qty[<?php echo (int)$r['id']; ?>]"
                           class="qtyInput" value="<?php echo (int)$r['qty']; ?>" min="1" inputmode="numeric">
                    <button type="button" class="btnInc" aria-label="বাড়ান">+</button>
                  </div>
                </td>
                <td data-label="লাইন" class="text-end lineCell">
                  <?php echo money_bd($r['qty'] * $r['price']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$items): ?>
              <tr><td colspan="4" class="text-center text-muted">কোন আইটেম নেই</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-outline-secondary" href="orders.php<?php echo $returnQS ? ('?'.h($returnQS)) : ''; ?>">Cancel</a>
      </div>
    </div></div>
  </div>
</form>

<script>
// ---- Helpers ----
function bdMoney(n){
  n = Math.round(Number(n)||0);
  return '৳' + n.toLocaleString('bn-BD');
}

// ---- Live recalculation ----
(function(){
  const tbl = document.getElementById('itemsTable');
  const feeEl = document.getElementById('shippingFee');
  const sumSub = document.getElementById('sumSubtotal');
  const sumShip= document.getElementById('sumShipping');
  const sumTot = document.getElementById('sumTotal');

  function recalc(){
    let subtotal = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(row=>{
      const price = parseFloat(row.querySelector('.priceCell')?.dataset.price || 0);
      const qtyEl  = row.querySelector('.qtyInput');
      const qty    = Math.max(1, parseInt(qtyEl?.value)||1);
      const line   = price * qty;
      row.querySelector('.lineCell').textContent = bdMoney(line);
      subtotal += line;
    });
    const ship = parseFloat(feeEl?.value || 0) || 0;
    if (sumSub)  sumSub.textContent = bdMoney(subtotal);
    if (sumShip) sumShip.textContent= bdMoney(ship);
    if (sumTot)  sumTot.textContent = bdMoney(subtotal + ship);
  }

  // Qty input typing
  document.querySelectorAll('.qtyInput').forEach(inp=>{
    inp.addEventListener('input', ()=>{
      if ((inp.value||'').trim()==='') return;
      if (parseInt(inp.value) < 1) inp.value = 1;
      recalc();
    });
    inp.addEventListener('change', ()=>{
      if (parseInt(inp.value) < 1 || isNaN(parseInt(inp.value))) inp.value = 1;
      recalc();
    });
  });

  // + / - buttons (delegation)
  tbl?.addEventListener('click', function(e){
    const dec = e.target.closest('.btnDec');
    const inc = e.target.closest('.btnInc');
    if (!dec && !inc) return;
    const wrap = e.target.closest('.qtyBox');
    const inp = wrap?.querySelector('.qtyInput');
    if (!inp) return;
    let v = parseInt(inp.value)||1;
    v += inc ? 1 : -1;
    if (v < 1) v = 1;
    inp.value = v;
    recalc();
  });

  // Shipping fee change
  feeEl?.addEventListener('input', recalc);
  feeEl?.addEventListener('change', recalc);

  // init
  recalc();
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
