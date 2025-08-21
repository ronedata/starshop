<?php
// admin/stock_report.php
require_once __DIR__.'/header.php';
$pdo = get_pdo();

/* -------- Inputs -------- */
$max_stock = isset($_GET['max_stock']) && $_GET['max_stock'] !== '' ? (int)$_GET['max_stock'] : 0;
$q         = trim($_GET['q'] ?? '');          // ঐচ্ছিক: পণ্যের নাম সার্চ
$cat       = (int)($_GET['cat'] ?? 0);        // ঐচ্ছিক: ক্যাটেগরি ফিল্টার
$status    = $_GET['status'] ?? 'all';        // ঐচ্ছিক: active/inactive

/* -------- Category list for filter -------- */
$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();

/* -------- Build where clause -------- */
$where = "1=1";
$params = [];

$where .= " AND p.stock <= ?";
$params[] = $max_stock;

if ($q !== '') {
  $where .= " AND p.name LIKE ?";
  $params[] = "%$q%";
}
if ($cat > 0) {
  $where .= " AND p.category_id = ?";
  $params[] = $cat;
}
if ($status === 'active') {
  $where .= " AND p.active = 1";
} elseif ($status === 'inactive') {
  $where .= " AND p.active = 0";
}

/* -------- Query -------- */
$sql = "SELECT p.*, c.name AS cat_name,
        (SELECT image FROM product_images pi 
           WHERE pi.product_id = p.id 
           ORDER BY sort_order, id LIMIT 1) AS main_image
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $where
        ORDER BY p.stock ASC, p.id DESC";

$stm = $pdo->prepare($sql);
$stm->execute($params);
$rows = $stm->fetchAll();

/* -------- Helpers -------- */
function img_or_default($url){
  return $url ? $url : (UPLOAD_URL.'/default.jpg');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">Stock Out Report</h5>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="products.php">পণ্য তালিকা</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-6 col-md-2">
        <label class="form-label">Max Stock (≤)</label>
        <input type="number" min="0" name="max_stock" class="form-control"
               value="<?php echo h($max_stock); ?>" required>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">নাম (সার্চ)</label>
        <input type="text" name="q" class="form-control" value="<?php echo h($q); ?>" placeholder="পণ্যের নাম...">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label">ক্যাটেগরি</label>
        <select name="cat" class="form-select">
          <option value="0">সব ক্যাটেগরি</option>
          <?php foreach($cats as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php echo $cat===(int)$c['id']?'selected':''; ?>>
              <?php echo h($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">স্ট্যাটাস</label>
        <select name="status" class="form-select">
          <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
          <option value="active" <?php echo $status==='active'?'selected':''; ?>>Active</option>
          <option value="inactive" <?php echo $status==='inactive'?'selected':''; ?>>Inactive</option>
        </select>
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-primary">ফিল্টার</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>ছবি</th>
            <th>নাম</th>
            <th>ক্যাটেগরি</th>
            <th class="text-end">দাম</th>
            <th class="text-end">তুলনামূলক</th>
            <th class="text-center">স্টক</th>
            <th>স্ট্যাটাস</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted">শর্ত মিলে এমন পণ্য পাওয়া যায়নি</td></tr>
          <?php endif; ?>
          <?php foreach($rows as $r): 
            $img = img_or_default($r['main_image']); ?>
            <tr>
              <td><?php echo (int)$r['id']; ?></td>
              <td><img src="<?php echo h($img); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb"></td>
              <td>
                <div class="fw-semibold"><?php echo h($r['name']); ?></div>
                <?php if (!empty($r['tags'])): ?>
                  <div class="small text-muted">ট্যাগ: <?php echo h($r['tags']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo h($r['cat_name']); ?></td>
              <td class="text-end"><?php echo money_bd($r['price']); ?></td>
              <td class="text-end">
                <?php echo $r['compare_at_price'] !== null && $r['compare_at_price']!==''
                  ? money_bd($r['compare_at_price'])
                  : '<span class="text-muted">—</span>'; ?>
              </td>
              <td class="text-center"><?php echo (int)$r['stock']; ?></td>
              <td>
                <span class="badge <?php echo ((int)$r['active']===1 ? 'bg-success' : 'bg-secondary'); ?>">
                  <?php echo ((int)$r['active']===1 ? 'Active' : 'Inactive'); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__.'/footer.php'; ?>
