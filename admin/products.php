<?php
require_once __DIR__.'/header.php';
$pdo = get_pdo();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ---------------- CSRF ---------------- */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ---------------- Helpers (Uploads + Safe unlink) ---------------- */
function uploads_dir_abs(): string {
  // 1) UPLOAD_DIR (prefer)
  if (defined('UPLOAD_DIR') && UPLOAD_DIR) {
    $d = realpath(UPLOAD_DIR);
    if ($d !== false) return $d;
    return UPLOAD_DIR;
  }
  // 2) __DIR__/uploads fallback
  $fallback = __DIR__ . '/uploads';
  $rp = realpath($fallback);
  return $rp !== false ? $rp : $fallback;
}

function is_path_in_dir(string $path, string $baseDir): bool {
  $rp = realpath($path);
  $rb = realpath($baseDir);
  if ($rp === false || $rb === false) return false;
  return strpos($rp, $rb) === 0;
}

function to_abs_upload_path(string $image): ?string {
  if ($image === '' || $image === null) return null;

  // full URL হলে path অংশ
  if (preg_match('~^https?://~i', $image)) {
    $parts = parse_url($image);
    $image = $parts['path'] ?? '';
  }

  // UPLOAD_URL prefix থাকলে কেটে দিন
  if (defined('UPLOAD_URL') && UPLOAD_URL && strpos($image, UPLOAD_URL) === 0) {
    $image = substr($image, strlen(UPLOAD_URL));
  }

  // leading slash বাদ
  $image = ltrim((string)$image, '/');

  // যদি ইতিমধ্যে 'uploads/...' দিয়ে শুরু, তাহলে সেটার পরের অংশ নিন
  if (stripos($image, 'uploads/') === 0) {
    $rel = substr($image, strlen('uploads/'));
  } else {
    $rel = $image; // কখনো কখনো শুধু filename থাকে
  }

  $abs = rtrim(uploads_dir_abs(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rel;
  return $abs;
}

function try_unlink_image(string $absPath, array &$deletedLog): void {
  $u = uploads_dir_abs();

  // নিরাপত্তা ও বেসিক চেক
  if (!file_exists($absPath) || !is_file($absPath)) return;
  if (!is_path_in_dir($absPath, $u)) return;

  $base = basename($absPath);
  if (strtolower($base) === 'default.jpg') return; // ডিফল্ট ইমেজ ডিলিট নয়

  // আসল ফাইল
  @unlink($absPath);
  $deletedLog[] = $absPath;

  // সম্ভাব্য ভেরিয়ান্ট (_sm/_md/_lg বা -sm/-md/-lg)
  $dir  = dirname($absPath);
  $name = pathinfo($base, PATHINFO_FILENAME);
  $ext  = pathinfo($base, PATHINFO_EXTENSION);
  foreach (['_sm','_md','_lg','-sm','-md','-lg'] as $suf) {
    $cand = $dir . DIRECTORY_SEPARATOR . $name . $suf . ($ext ? '.'.$ext : '');
    if (file_exists($cand) && is_file($cand)) {
      @unlink($cand);
      $deletedLog[] = $cand;
    }
  }
}

function flash_set(bool $ok, string $msg): void {
  $_SESSION['flash'] = ['ok'=>$ok, 'message'=>$msg];
}
function flash_show(): void {
  if (empty($_SESSION['flash'])) return;
  $f   = $_SESSION['flash']; unset($_SESSION['flash']);
  $cls = !empty($f['ok']) ? 'success' : 'danger';
  $msg = htmlspecialchars((string)$f['message'], ENT_QUOTES, 'UTF-8');
  echo '<div class="alert alert-'.$cls.' alert-dismissible fade show" role="alert" style="position:sticky;top:10px;z-index:1;">'.
       $msg.
       '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'.
       '</div>';
}

/* ---------------- Handle Toggle (GET) ---------------- */
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

/* ---------------- Handle Delete (POST) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  // CSRF
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
    flash_set(false, 'Invalid CSRF token.');
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  $delId = (int)$_POST['delete_id'];
  if ($delId <= 0) {
    flash_set(false, 'Invalid product id.');
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  try {
    $pdo->beginTransaction();

    // গ্যালারি ইমেজ (product_images.image) গুলো সংগ্রহ
    $imgs = [];
    try {
      $stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id=?");
      $stmt->execute([$delId]);
      $imgs = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
      // টেবিল না থাকলে/ত্রুটিতে ইমেজ স্কিপ
      $imgs = [];
    }

    // গ্যালারি রেকর্ড ডিলিট
    try {
      $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$delId]);
    } catch (Throwable $e) {
      // ignore
    }

    // প্রোডাক্ট ডিলিট
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$delId]);

    $pdo->commit();

    // ফাইল ডিলিট (DB commit-এর পর; ফাইল-সিস্টেমে ত্রুটি হলেও DB ঠিক থাকবে)
    $deletedFiles = [];
    foreach ($imgs as $im) {
      $abs = to_abs_upload_path((string)$im);
      if ($abs) try_unlink_image($abs, $deletedFiles);
    }

    $note = 'পণ্য ডিলিট সম্পন্ন';
    if (count($deletedFiles)) $note .= ' (ইমেজসহ)';
    flash_set(true, $note);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set(false, 'ডিলিট ব্যর্থ: সার্ভার ত্রুটি');
  }

  // বর্তমান কুয়েরি-প্যারাম রেখে রিডাইরেক্ট
  $qs = $_GET ? '?'.http_build_query($_GET) : '';
  header('Location: products.php'.$qs);
  exit;
}

/* ---------------- Fetch Data ---------------- */
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

<?php flash_show(); ?>

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
    <?php foreach($rows as $r): 
      $img = $r['image'] ?: (defined('UPLOAD_URL') ? (UPLOAD_URL.'/default.jpg') : 'uploads/default.jpg'); ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><img class="thumb" src="<?php echo h($img); ?>" style="width:54px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;"></td>
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

          <!-- ✅ Delete: POST + CSRF + confirm -->
          <form method="post" action="products.php<?php
              // বর্তমান ফিল্টার ধরে রাখতে GET কপি করি
              echo $_GET ? ('?'.http_build_query($_GET)) : '';
            ?>"
            style="display:inline"
            onsubmit="return confirm('এই পণ্যটি ডিলিট করবেন? (ইমেজসহ মুছে যাবে)');">
            <input type="hidden" name="delete_id" value="<?php echo (int)$r['id']; ?>">
            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div></div>

<?php require __DIR__.'/footer.php'; ?>
