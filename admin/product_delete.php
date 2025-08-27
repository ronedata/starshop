<?php
// admin/product_delete.php — delete product + ALL images + ONLY file-type videos
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* ----------------- Respond helper ----------------- */
function respond($ok, $message, $code = 200, $redirect = 'products.php') {
  http_response_code($code);
  $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok, 'message'=>$message]);
    exit;
  } else {
    $_SESSION['flash'] = ['ok'=>$ok, 'message'=>$message];
    header('Location: '.$redirect);
    exit;
  }
}

/* ----------------- Guards ----------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed', 405);
if (isset($_POST['csrf'], $_SESSION['csrf']) && $_POST['csrf'] !== $_SESSION['csrf']) respond(false, 'CSRF token mismatch', 400);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) respond(false, 'Invalid product id', 400);

/* ----------------- File helpers ----------------- */
function uploads_abs(): string {
  if (defined('UPLOAD_DIR') && UPLOAD_DIR) { $rp = realpath(UPLOAD_DIR); return $rp !== false ? $rp : UPLOAD_DIR; }
  $fallback = __DIR__.'/../uploads'; $rp = realpath($fallback); return $rp !== false ? $rp : $fallback;
}
function path_in_dir(string $path, string $base): bool {
  $rp = realpath($path); $rb = realpath($base);
  if ($rp === false || $rb === false) return false;
  return strpos($rp, $rb) === 0;
}
function is_default_image(string $p): bool {
  return strtolower(basename($p)) === 'default.jpg';
}
function is_windows_abs(string $p): bool { return (bool)preg_match('~^[A-Za-z]:\\\\~', $p); }

function to_abs_from_db(?string $p): ?string {
  if (!$p) return null;

  // absolute file path (Linux/Windows)
  if (is_windows_abs($p) || (substr($p,0,1)==='/' && file_exists($p))) return $p;

  // URL → take path part
  if (preg_match('~^https?://~i', $p)) {
    $parts = parse_url($p);
    $p = $parts['path'] ?? '';
  }
  // strip BASE_URL / UPLOAD_URL prefix
  if (defined('BASE_URL') && BASE_URL && strpos($p, BASE_URL) === 0) $p = substr($p, strlen(BASE_URL));
  if (defined('UPLOAD_URL') && UPLOAD_URL && strpos($p, UPLOAD_URL) === 0) $p = substr($p, strlen(UPLOAD_URL));

  // make relative to uploads
  $p = ltrim($p, '/');
  if (stripos($p, 'uploads/') === 0) $p = substr($p, strlen('uploads/'));

  $abs = rtrim(uploads_abs(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
  return $abs;
}
function unlink_with_variants(string $abs, array &$deletedLog): void {
  $base = uploads_abs();
  if (!file_exists($abs) || !is_file($abs)) return;
  if (!path_in_dir($abs, $base)) return;

  // default.jpg → never delete (image only)
  if (is_default_image($abs)) return;

  @unlink($abs);
  $deletedLog[] = $abs;

  // image resize variants (no effect for videos, harmless if not present)
  $dir = dirname($abs);
  $b   = basename($abs);
  $n   = pathinfo($b, PATHINFO_FILENAME);
  $e   = pathinfo($b, PATHINFO_EXTENSION);
  foreach (['_sm','_md','_lg','-sm','-md','-lg'] as $suf) {
    $cand = $dir . DIRECTORY_SEPARATOR . $n . $suf . ($e ? '.'.$e : '');
    if (file_exists($cand) && is_file($cand)) {
      @unlink($cand);
      $deletedLog[] = $cand;
    }
  }
}

/* ----------------- Main ----------------- */
try {
  // 1) প্রোডাক্ট আছে কিনা + main image path
  $stm = $pdo->prepare("SELECT image FROM products WHERE id=?");
  $stm->execute([$id]);
  $prod = $stm->fetch(PDO::FETCH_ASSOC);
  if (!$prod) respond(false, 'Product not found', 404);

  $mainImage = $prod['image'] ?? null;

  // 2) গ্যালারি ইমেজ (সব)
  $gallery = [];
  try {
    $g = $pdo->prepare("SELECT image FROM product_images WHERE product_id=?");
    $g->execute([$id]);
    $gallery = $g->fetchAll(PDO::FETCH_COLUMN) ?: [];
  } catch (Throwable $e) {
    $gallery = [];
  }

  // 3) ভিডিও URL/পাথ (শুধু type='file')
  $videoFiles = [];
  try {
    $v = $pdo->prepare("SELECT url FROM product_videos WHERE product_id=? AND type='file'");
    $v->execute([$id]);
    while ($row = $v->fetch(PDO::FETCH_ASSOC)) {
      $u = trim((string)($row['url'] ?? ''));
      if ($u==='') $u = trim((string)($row['path'] ?? ''));
      if ($u==='') $u = trim((string)($row['video'] ?? ''));
      if ($u!=='') $videoFiles[] = $u;
    }
  } catch (Throwable $e) {
    $videoFiles = [];
  }

  // 4) DB Delete (transactional)
  $pdo->beginTransaction();
  try { $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]); } catch (Throwable $e) {}
  try { $pdo->prepare("DELETE FROM product_videos WHERE product_id=?")->execute([$id]); } catch (Throwable $e) {}
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $pdo->commit();

  // 5) Files unlink (commit-এর পরে) — dedupe
  $deleted = [];
  $absSet  = [];

  // main image
  if ($mainImage && !is_default_image($mainImage)) {
    $abs = to_abs_from_db($mainImage);
    if ($abs) $absSet[realpath($abs) ?: $abs] = $abs;
  }
  // gallery images
  foreach ($gallery as $gi) {
    if (!$gi || is_default_image($gi)) continue;
    $abs = to_abs_from_db($gi);
    if ($abs) $absSet[realpath($abs) ?: $abs] = $abs;
  }
  // ONLY file-type videos
  foreach ($videoFiles as $vf) {
    $abs = to_abs_from_db($vf);
    if ($abs) $absSet[realpath($abs) ?: $abs] = $abs;
  }

  foreach ($absSet as $abs) {
    unlink_with_variants($abs, $deleted);
  }

  $msg = 'পণ্য ডিলিট সম্পন্ন';
  if (!empty($deleted)) $msg .= ' (ইমেজ/ভিডিও ফাইলসহ)';
  respond(true, $msg);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  respond(false, 'Server error', 500);
}
