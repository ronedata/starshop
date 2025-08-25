<?php
// admin/orders.php (Mobile + PC friendly, header-safe redirect)
require_once __DIR__.'/../config.php';
require_once __DIR__.'/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* -------- Helpers (fallback) -------- */
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_bd')) {
  function money_bd($n){ $n=(float)$n; $n=round($n); return '৳'.number_format($n,0,'',','); }
}
if (!function_exists('today')) {
  function today(){ return date('Y-m-d'); }
}

/* -------- Allowed statuses -------- */
$STATUSES = ['pending','processing','shipped','delivered','cancelled'];

/* -------- Handle Status Update (POST) BEFORE any HTML -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
  $oid    = (int)($_POST['order_id'] ?? 0);
  $status = $_POST['status'] ?? '';
  $return = $_POST['return'] ?? '';
  if ($oid > 0 && in_array($status, $STATUSES, true)) {
    $stm = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stm->execute([$status, $oid]);
  }
  header('Location: orders.php'.($return ? ('?'.$return) : '')); exit;
}

/* -------- Filters (GET) -------- */
$q      = trim($_GET['q'] ?? '');
$from   = $_GET['from'] ?? today();
$to     = $_GET['to']   ?? today();
$fstat  = $_GET['status'] ?? 'all'; // all | in $STATUSES

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

/* -------- Fetch rows -------- */
$sql = "SELECT * FROM orders WHERE $where ORDER BY created_at DESC";
$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll();

/* -------- Badge class -------- */
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

/* -------- Now load layout header (HTML starts here) -------- */
require_once __DIR__.'/header.php';
?>
<style>
  /* Mobile card-view for table */
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
      padding:6px 0 !important; border:0 !important;
    }
    table.table td::before{
      content:attr(data-label);
      font-weight:600; color:#64748b; min-width:120px; text-align:left;
    }
    /* Action buttons full-width on mobile */
    .td-actions .btn{ width:100%; }
    .td-actions{ display:flex; flex-direction:column; gap:8px; }
    /* Status section wraps nicely */
    .status-inline{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .status-inline .form-select{ min-width:140px; }
  }
  /* Small UX touches */
  .badge-status{ min-width:94px; display:inline-flex; justify-content:center; align-items:center; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">অর্ডার ব্যবস্থাপনা</h5>
  <a class="btn btn-outline-secondary" href="orders.php?from=<?php echo h(today()); ?>&to=<?php echo h(today()); ?>&status=all">আজকের অর্ডার</a>
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
          <th class="text-end">মোট</th>
          <th style="min-width:220px">স্ট্যাটাস</th>
          <th>তারিখ</th>
          <th class="text-end">অ্যাকশন</th>
        </tr>
      </thead>
      <tbody>
      <?php $returnQS = h($_SERVER['QUERY_STRING'] ?? ''); ?>
      <?php foreach($rows as $o): ?>
        <tr>
          <td data-label="অর্ডার">#<?php echo h($o['order_code']); ?></td>
          <td data-label="গ্রাহক" class="fw-semibold"><?php echo h($o['customer_name']); ?></td>
          <td data-label="মোবাইল"><?php echo h($o['mobile']); ?></td>
          <td data-label="মোট" class="text-end"><?php echo money_bd($o['total']); ?></td>
		<td data-label="স্ট্যাটাস">
		  <?php
			// ডট রং ম্যাপ
			$dotMap = [
			  'pending'    => '#f59e0b',
			  'processing' => '#06b6d4',
			  'shipped'    => '#3b82f6',
			  'delivered'  => '#16a34a',
			  'cancelled'  => '#6b7280',
			];
			$dotColor = $dotMap[$o['status']] ?? '#94a3b8';
		  ?>
		  <div class="d-flex align-items-center gap-2 flex-wrap">
			<!-- বর্তমান স্ট্যাটাস ব্যাজ -->
			<span class="badge rounded-pill px-3 py-2 badge-status <?php echo status_badge_class($o['status']); ?> status-pill">
			  <span class="status-dot" style="background:<?php echo $dotColor; ?>"></span>
			  <?php echo ucfirst(h($o['status'])); ?>
			</span>

			<!-- সুন্দর ড্রপডাউন: ক্লিক করে সঙ্গে সঙ্গে আপডেট -->
			<div class="btn-group">
			  <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
					  data-bs-toggle="dropdown" aria-expanded="false">
				পরিবর্তন
			  </button>
			  <div class="dropdown-menu dropdown-menu-end p-0">
				<form method="post" class="py-1 px-1">
				  <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
				  <input type="hidden" name="return"   value="<?php echo h($_SERVER['QUERY_STRING'] ?? ''); ?>">
				  <input type="hidden" name="update_status" value="1">
				  <?php foreach($STATUSES as $s):
						$c = $dotMap[$s] ?? '#94a3b8';
						$active = ($o['status'] === $s);
				  ?>
					<button type="submit"
							name="status" value="<?php echo h($s); ?>"
							class="dropdown-item d-flex align-items-center gap-2 <?php echo $active?'active':''; ?>">
					  <span class="status-dot" style="background:<?php echo $c; ?>"></span>
					  <?php echo ucfirst($s); ?>
					  <?php if ($active): ?><span class="ms-auto">✔</span><?php endif; ?>
					</button>
				  <?php endforeach; ?>
				</form>
			  </div>
			</div>
		  </div>
		</td>

          <td data-label="তারিখ"><?php echo h($o['created_at']); ?></td>
          <td data-label="অ্যাকশন" class="text-end td-actions">
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
