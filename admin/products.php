<?php require_once __DIR__.'/header.php';
$pdo = get_pdo();

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$status = $_GET['status'] ?? 'all';

$where = "1=1";
$params = [];
if ($q !== '') { $where .= " AND p.name LIKE ?"; $params[] = "%$q%"; }
if ($cat > 0) { $where .= " AND p.category_id=?"; $params[] = $cat; }
if ($status === 'active') { $where .= " AND p.active=1"; }
elseif ($status === 'inactive') { $where .= " AND p.active=0"; }

if (isset($_GET['toggle'])) {
  $id = (int)$_GET['toggle'];
  $pdo->prepare("UPDATE products SET active = 1 - active WHERE id=?")->execute([$id]);
  $ret = $_GET; unset($ret['toggle']); $qs = http_build_query($ret);
  header("Location: products.php?$qs"); exit;
}

$cats = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();

$sql = "SELECT p.*, c.name AS cat_name,
        (SELECT image FROM product_images pi WHERE pi.product_id=p.id ORDER BY sort_order, id LIMIT 1) AS image
        FROM products p LEFT JOIN categories c ON c.id=p.category_id
        WHERE $where ORDER BY p.id DESC";
$stm = $pdo->prepare($sql); $stm->execute($params); $rows = $stm->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">পণ্য ব্যবস্থাপনা</h5>
  <a class="btn btn-primary" href="product_form.php">নতুন পণ্য</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-4"><input class="form-control" name="q" placeholder="পণ্য খুঁজুন..." value="<?php echo h($q); ?>"></div>
  <div class="col-6 col-md-3">
    <select class="form-select" name="cat">
      <option value="0">সব ক্যাটেগরি</option>
      <?php foreach($cats as $c): ?>
        <option value="<?php echo (int)$c['id']; ?>" <?php echo $cat===(int)$c['id']?'selected':''; ?>><?php echo h($c['name']); ?></option>
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
  <div class="col-12 col-md-2 d-grid"><button class="btn btn-outline-secondary">ফিল্টার</button></div>
</form>

<div class="card"><div class="card-body p-0">
  <div class="table-responsive">
  <table class="table table-striped align-middle mb-0">
    <thead class="table-light">
      <tr><th>ID</th><th>ছবি</th><th>নাম</th><th>ক্যাটেগরি</th><th>দাম</th><th>স্টক</th><th>স্ট্যাটাস</th><th class="text-end">অ্যাকশন</th></tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r): $img = $r['image'] ?: (UPLOAD_URL.'/default.jpg'); ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><img class="thumb" src="<?php echo h($img); ?>"></td>
        <td><?php echo h($r['name']); ?></td>
        <td><?php echo h($r['cat_name']); ?></td>
        <td><?php echo money_bd($r['price']); ?></td>
        <td><?php echo (int)$r['stock']; ?></td>
        <td>
          <a class="badge <?php echo $r['active']?'bg-success':'bg-secondary'; ?>" href="?<?php
            $qs = $_GET; $qs['toggle'] = (int)$r['id']; echo http_build_query($qs);
          ?>"><?php echo $r['active']?'Active':'Inactive'; ?></a>
        </td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-primary" href="product_form.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
          <a class="btn btn-sm btn-outline-danger" href="product_form.php?del=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete product?')">Delete</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div></div>
<?php require __DIR__.'/footer.php'; ?>
