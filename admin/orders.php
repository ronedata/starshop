<?php
require_once __DIR__.'/header.php';
$pdo = get_pdo();

/* ---------------------------------------
   Allowed statuses
--------------------------------------- */
$STATUSES = ['pending','processing','shipped','delivered','cancelled'];

/* ---------------------------------------
   Handle Status Update (POST)
--------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  $oid    = (int)($_POST['order_id'] ?? 0);
  $status = $_POST['status'] ?? '';
  $return = $_POST['return'] ?? '';
  if ($oid > 0 && in_array($status, $STATUSES, true)) {
    $stm = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stm->execute([$status, $oid]);
  }
  // ফিল্টার/সার্চ কনটেক্সট রেখে রিডাইরেক্ট
  header('Location: orders.php'.($return ? ('?'.$return) : '')); exit;
}

/* ---------------------------------------
   Filters (GET)
--------------------------------------- */
$q      = trim($_GET['q'] ?? '');
$from   = $_GET['from'] ?? today();
$to     = $_GET['to']   ?? today();
$fstat  = $_GET['status'] ?? 'all'; // all / one of $STATUSES

$where  = "DATE(created_at) BETWEEN ? AND ?";
$params = [$from, $to];

if ($q !== '') {
  $where .= " AND (order_code LIKE ? OR customer_name LIKE ? OR mobile LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}

if ($fstat !== 'all' && in_array($fstat, $STATUSES, true)) {
  $where .= " AND status = ?";
  $params[] = $fstat;
}

/* ---------------------------------------
   Fetch rows
--------------------------------------- */
$sql = "SELECT * FROM orders WHERE $where ORDER BY created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll();

/* helper: badge class (PHP 7 compatible) */
function status_badge_class($s){
  switch ($s) {
    case 'pending':    return 'bg-warning text-dark';
    case 'processing': return 'bg-info text-dark';
    case 'shipped':    return 'bg-primary';
    case 'delivered':  return 'bg-success';
    case 'cancelled':  return 'bg-secondary';
    default:           return 'bg-light text-dark';
  }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">অর্ডার ব্যবস্থাপনা</h5>
  <a class="btn btn-outline-secondary" href="orders.php?from=<?php echo today(); ?>&to=<?php echo today(); ?>&status=all">আজকের অর্ডার</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-3">
    <input class="form-control" name="q" placeholder="Order # / Customer / Mobile" value="<?php echo h($q); ?>">
  </div>
  <div class="col-6 col-md-2">
    <input type="date" class="form-control" name="from" value="<?php echo h($from); ?>">
  </div>
  <div class="col-6 col-md-2">
    <input type="date" class="form-control" name="to" value="<?php echo h($to); ?>">
  </div>
  <div class="col-6 col-md-2">
    <select class="form-select" name="status">
      <option value="all" <?php echo $fstat==='all'?'selected':''; ?>>সব স্ট্যাটাস</option>
      <?php foreach($STATUSES as $s): ?>
        <option value="<?php echo h($s); ?>" <?php echo $fstat===$s?'selected':''; ?>>
          <?php echo ucfirst($s); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-3 d-grid">
    <button class="btn btn-outline-secondary">ফিল্টার</button>
  </div>
</form>

<div class="card"><div class="card-body p-0">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>গ্রাহক</th>
          <th>মোবাইল</th>
          <th>মোট</th>
          <th style="min-width:180px">স্ট্যাটাস</th>
          <th>তারিখ</th>
          <th class="text-end">অ্যাকশন</th>
        </tr>
      </thead>
      <tbody>
      <?php $returnQS = h($_SERVER['QUERY_STRING'] ?? ''); ?>
      <?php foreach($rows as $o): ?>
        <tr>
          <td>#<?php echo h($o['order_code']); ?></td>
          <td><?php echo h($o['customer_name']); ?></td>
          <td><?php echo h($o['mobile']); ?></td>
          <td><?php echo money_bd($o['total']); ?></td>
          <td>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge <?php echo status_badge_class($o['status']); ?>"><?php echo h($o['status']); ?></span>
              <!-- Inline status update -->
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                <input type="hidden" name="return"   value="<?php echo $returnQS; ?>">
                <select name="status" class="form-select form-select-sm" required>
                  <?php foreach($STATUSES as $s): ?>
                    <option value="<?php echo h($s); ?>" <?php echo $o['status']===$s?'selected':''; ?>>
                      <?php echo ucfirst($s); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary" name="update_status" value="1">Save</button>
              </form>
            </div>
          </td>
          <td><?php echo h($o['created_at']); ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="order_print.php?id=<?php echo (int)$o['id']; ?>">Print</a>
            <a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?php echo (int)$o['id']; ?>">View</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted">কোন অর্ডার পাওয়া যায়নি</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div></div>

<?php require __DIR__.'/footer.php'; ?>
