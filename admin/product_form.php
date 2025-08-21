<?php
// admin/product_form.php
require_once __DIR__.'/header.php';
$pdo = get_pdo();

define('DEFAULT_IMG_URL', rtrim(UPLOAD_URL,'/').'/default.jpg'); // ডিফল্ট ইমেজ URL
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0777, true); }     // আপলোড ফোল্ডার নিশ্চিত

/* ========================
   Delete Product (optional)
   ======================== */
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  // প্রোডাক্ট ইমেজ মুছুন (DB)
  $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
  // প্রোডাক্ট মুছুন
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  header('Location: products.php'); exit;
}

/* ========================
   Delete a single image
   ======================== */
if (isset($_GET['delimg'])) {
  $imgId = (int)$_GET['delimg'];
  $pid   = (int)($_GET['id'] ?? 0);
  if ($imgId > 0 && $pid > 0) {
    $pdo->prepare("DELETE FROM product_images WHERE id=? AND product_id=?")->execute([$imgId, $pid]);

    // অবশিষ্ট ইমেজ থেকে main ঠিক করা
    $stm = $pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY sort_order, id LIMIT 1");
    $stm->execute([$pid]);
    $main = $stm->fetchColumn();

    if ($main) {
      $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$main, $pid]);
    } else {
      // কোনো ইমেজ নেই → default বসান এবং DB-তেও ঢোকান
      $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([DEFAULT_IMG_URL, $pid]);
      $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,0)")
          ->execute([$pid, DEFAULT_IMG_URL]);
    }
  }
  header('Location: product_form.php?id='.$pid.'&updated=1'); exit;
}

/* ========================
   Load categories, state
   ======================== */
$cats  = $pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();

$id    = (int)($_GET['id'] ?? 0);
$edit  = null;
$images = [];

$msg_success = '';
if (isset($_GET['saved']))   { $msg_success = '✅ পণ্যটি সফলভাবে যুক্ত হয়েছে!'; $id = 0; }
if (isset($_GET['updated'])) { $msg_success = '✅ পণ্যটি সফলভাবে আপডেট হয়েছে!'; }

if ($id > 0) {
  $stm=$pdo->prepare("SELECT * FROM products WHERE id=?");
  $stm->execute([$id]);
  $edit=$stm->fetch();

  $q=$pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id");
  $q->execute([$id]);
  $images = $q->fetchAll();
}

/* ========================
   Save (Add / Update)
   ======================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id     = (int)($_POST['id'] ?? 0);
  $name   = trim($_POST['name'] ?? '');
  $cat    = (int)($_POST['category_id'] ?? 0);
  $price  = (float)($_POST['price'] ?? 0);
  $compare_at_price = ($_POST['compare_at_price'] !== '' ? (float)$_POST['compare_at_price'] : null);
  $stock  = (int)($_POST['stock'] ?? 0);
  $tags   = trim($_POST['tags'] ?? '');
  $specs  = trim($_POST['specifications'] ?? '');
  $desc   = trim($_POST['description'] ?? '');
  $active = isset($_POST['active']) ? 1 : 0;

  if ($id > 0) {
    // UPDATE
    $pdo->prepare("UPDATE products 
        SET category_id=?, name=?, description=?, price=?, compare_at_price=?, stock=?, tags=?, specifications=?, active=? 
        WHERE id=?")
        ->execute([$cat,$name,$desc,$price,$compare_at_price,$stock,$tags,$specs,$active,$id]);
    $newId = $id;
    $redirect = 'product_form.php?id='.$newId.'&updated=1';
  } else {
    // INSERT
    $pdo->prepare("INSERT INTO products(category_id,name,description,price,compare_at_price,stock,tags,specifications,active) 
        VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([$cat,$name,$desc,$price,$compare_at_price,$stock,$tags,$specs,$active]);
    $newId = (int)$pdo->lastInsertId();
    $redirect = 'product_form.php?saved=1'; // Add করার পর ফর্ম রিসেট
  }

  /* ---- Multiple image upload ---- */
  if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $f = $_FILES['images'];
    $total = count($f['name']);
    for ($i=0; $i<$total; $i++) {
      if (empty($f['name'][$i]) || $f['error'][$i] !== UPLOAD_ERR_OK) continue;
      if (!is_uploaded_file($f['tmp_name'][$i])) continue;

      $ext = strtolower(pathinfo($f['name'][$i], PATHINFO_EXTENSION));
      if (!in_array($ext,['jpg','jpeg','png','webp'])) continue;

      $fname  = 'product_'.$newId.'_'.time().'_'.$i.'.'.$ext;
      $target = rtrim(UPLOAD_DIR,'/').'/'.$fname;
      if (move_uploaded_file($f['tmp_name'][$i], $target)) {
        $url = rtrim(UPLOAD_URL,'/').'/'.$fname;
        $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,?)")
            ->execute([$newId, $url, $i]);
      }
    }
  }

  /* ---- Main image fix (always) ---- */
  $stm = $pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY sort_order, id LIMIT 1");
  $stm->execute([$newId]);
  $main = $stm->fetchColumn();

  if (!$main) {
    // product_images-এ কিছু নেই → default বসান
    $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,0)")
        ->execute([$newId, DEFAULT_IMG_URL]);
    $main = DEFAULT_IMG_URL;
  }

  // products.image আপডেট
  $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$main, $newId]);

  header('Location: '.$redirect); exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0"><?php echo $edit ? 'পণ্য এডিট' : 'নতুন পণ্য'; ?></h5>
  <div>
    <a href="products.php" class="btn btn-secondary">পণ্য তালিকা</a>
    <?php if($edit): ?>
      <a href="?del=<?php echo (int)$edit['id']; ?>" class="btn btn-danger ms-2" onclick="return confirm('Delete product?')">ডিলিট</a>
    <?php endif; ?>
  </div>
</div>

<?php if($msg_success): ?>
  <div class="alert alert-success py-2"><?php echo h($msg_success); ?></div>
<?php endif; ?>

<div class="card"><div class="card-body">
  <form method="post" enctype="multipart/form-data" id="productForm">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">নাম</label>
        <input name="name" class="form-control" required value="<?php echo h($edit['name'] ?? ''); ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">ক্যাটেগরি</label>
        <select name="category_id" class="form-select">
          <option value="">-- None --</option>
          <?php foreach($cats as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>"
              <?php echo isset($edit['category_id']) && (int)$edit['category_id']===(int)$c['id']?'selected':''; ?>>
              <?php echo h($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">মূল্য</label>
        <input type="number" step="0.01" name="price" class="form-control" required value="<?php echo h($edit['price'] ?? ''); ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">তুলনামূলক দাম (compare_at_price)</label>
        <input type="number" step="0.01" name="compare_at_price" class="form-control" value="<?php echo h($edit['compare_at_price'] ?? ''); ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">স্টক</label>
        <input type="number" name="stock" class="form-control" required value="<?php echo h($edit['stock'] ?? '0'); ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">ট্যাগ (কমা দিয়ে আলাদা করুন)</label>
        <input type="text" name="tags" class="form-control" placeholder="উদাহরণ: new, hot, best" value="<?php echo h($edit['tags'] ?? ''); ?>">
      </div>

      <div class="col-12">
        <label class="form-label">স্পেসিফিকেশন</label>
        <textarea name="specifications" class="form-control" rows="4" placeholder="বিস্তারিত স্পেসিফিকেশন লিখুন..."><?php echo h($edit['specifications'] ?? ''); ?></textarea>
      </div>

      <div class="col-12">
        <label class="form-label">বিবরণ</label>
        <textarea name="description" class="form-control" rows="4"><?php echo h($edit['description'] ?? ''); ?></textarea>
      </div>

      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="active" id="activeChk"
            <?php echo (isset($edit['active']) ? ((int)$edit['active'] ? 'checked' : '') : 'checked'); ?>>
          <label for="activeChk" class="form-check-label">Active</label>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">ছবি (Multiple)</label>
        <input type="file" name="images[]" class="form-control" multiple>
        <?php if($images): ?>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <?php foreach($images as $im): ?>
              <div class="position-relative">
                <img src="<?php echo h($im['image']); ?>" class="thumb"
                     style="height:72px;width:72px;object-fit:cover;border:1px solid #e5e7eb;border-radius:8px">
                <a href="?id=<?php echo (int)($edit['id']); ?>&delimg=<?php echo (int)$im['id']; ?>"
                   class="btn btn-sm btn-danger position-absolute"
                   style="top:-8px;right:-8px;border-radius:50%;line-height:1;padding:2px 7px"
                   onclick="return confirm('এই ছবিটি মুছবেন?')">×</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small mt-1">
            ছবি না থাকলে ডিফল্ট <code>uploads/default.jpg</code> স্বয়ংক্রিয়ভাবে সেট হবে।
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary"><?php echo $edit ? 'Update' : 'Save'; ?></button>
    </div>
  </form>
</div></div>

<?php require __DIR__.'/footer.php'; ?>
