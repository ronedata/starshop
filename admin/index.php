<?php
require_once __DIR__.'/header.php';
$pdo = get_pdo();

/* ========== স্ট্যাটস ========== */
$stats = [
  'products'       => 0,
  'orders'         => 0,
  'pending'        => 0,
  'delivered'      => 0,
  'today_orders'   => 0,
  'revenue_total'  => 0.0,
];

$row = $pdo->query("SELECT COUNT(*) c FROM products")->fetch();
$stats['products'] = (int)($row['c'] ?? 0);

$row = $pdo->query("SELECT COUNT(*) c FROM orders")->fetch();
$stats['orders'] = (int)($row['c'] ?? 0);

$row = $pdo->query("SELECT COUNT(*) c FROM orders WHERE status='pending'")->fetch();
$stats['pending'] = (int)($row['c'] ?? 0);

$row = $pdo->query("SELECT COUNT(*) c FROM orders WHERE status='delivered'")->fetch();
$stats['delivered'] = (int)($row['c'] ?? 0);

$stm = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE DATE(created_at)=?");
$stm->execute([today()]);
$stats['today_orders'] = (int)($stm->fetch()['c'] ?? 0);

$row = $pdo->query("SELECT COALESCE(SUM(total),0) s FROM orders")->fetch();
$stats['revenue_total'] = (float)($row['s'] ?? 0);

/* ========== আজকের সাম্প্রতিক অর্ডার ========== */
$recent = [];
$stm = $pdo->prepare("SELECT id,order_code,customer_name,mobile,total,status,created_at
                      FROM orders
                      WHERE DATE(created_at)=?
                      ORDER BY id DESC");
$stm->execute([today()]);
$recent = $stm->fetchAll();
?>
<div class="row g-3">
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo (int)$stats['products']; ?></div>
      <div class="text-muted">মোট পণ্য</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo (int)$stats['orders']; ?></div>
      <div class="text-muted">মোট অর্ডার</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo (int)$stats['pending']; ?></div>
      <div class="text-muted">পেন্ডিং অর্ডার</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo (int)$stats['delivered']; ?></div>
      <div class="text-muted">ডেলিভার হয়েছে</div>
    </div></div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo (int)$stats['today_orders']; ?></div>
      <div class="text-muted">আজকের অর্ডার</div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body">
      <div class="h5 mb-0"><?php echo money_bd($stats['revenue_total']); ?></div>
      <div class="text-muted">মোট আয়</div>
    </div></div>
  </div>
</div>

<hr>

<div class="row g-3">
  <!-- সাম্প্রতিক অর্ডার (আজকের) -->
  <div class="col-12 col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="m-0">সাম্প্রতিক অর্ডার</h5>
          <a href="orders.php?from=<?php echo today(); ?>&to=<?php echo today(); ?>" class="btn btn-sm btn-outline-secondary">
            সব দেখুন
          </a>
        </div>

        <?php if(!$recent): ?>
          <div class="text-center text-muted py-5">
            <div style="font-size:40px;line-height:1">🛒</div>
            এখানে কোন অর্ডার নেই
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>গ্রাহক</th>
                  <th>মোবাইল</th>
                  <th class="text-end">মোট</th>
                  <th>স্ট্যাটাস</th>
                  <th>সময়</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($recent as $o): ?>
                <tr>
                  <td>#<?php echo h($o['order_code']); ?></td>
                  <td><?php echo h($o['customer_name']); ?></td>
                  <td><?php echo h($o['mobile']); ?></td>
                  <td class="text-end"><?php echo money_bd($o['total']); ?></td>
                  <td><span class="badge badge-pill <?php
                      switch($o['status']){
                        case 'pending':    echo 'bg-warning text-dark'; break;
                        case 'processing': echo 'bg-info text-dark'; break;
                        case 'shipped':    echo 'bg-primary'; break;
                        case 'delivered':  echo 'bg-success'; break;
                        default:           echo 'bg-secondary';
                      } ?>"><?php echo h($o['status']); ?></span>
                  </td>
                  <td><?php echo h(substr($o['created_at'],11,5)); ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="order_view.php?id=<?php echo (int)$o['id']; ?>">View</a>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="order_print.php?id=<?php echo (int)$o['id']; ?>">Print</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- দ্রুত অ্যাকশন -->
  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="m-0 mb-3">দ্রুত অ্যাকশন</h5>
        <div class="list-group">
          <a class="list-group-item list-group-item-action d-flex align-items-center" href="product_form.php">
            <span class="me-2">➕</span> নতুন পণ্য যোগ করুন
          </a>
          <a class="list-group-item list-group-item-action d-flex align-items-center" href="products.php">
            <span class="me-2">🛍️</span> পণ্য দেখুন
          </a>
          <a class="list-group-item list-group-item-action d-flex align-items-center" href="orders.php?from=<?php echo today(); ?>&to=<?php echo today(); ?>">
            <span class="me-2">🧾</span> আজকের অর্ডার
          </a>
          <a class="list-group-item list-group-item-action d-flex align-items-center" href="settings.php">
            <span class="me-2">⚙️</span> সেটিংস আপডেট
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__.'/footer.php'; ?>
