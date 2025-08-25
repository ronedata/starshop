<?php
// product_delete.php (FINAL)
//require_once __DIR__.'./config.php';
require_once __DIR__ . '/../config.php';

$pdo = get_pdo();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ------------ CSRF (optional but recommended) ------------ */
if (isset($_POST['csrf']) && isset($_SESSION['csrf']) && $_POST['csrf'] !== $_SESSION['csrf']) {
  respond(false, 'CSRF token mismatch');
}

/* ------------ Method guard ------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(false, 'Method not allowed', 405);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  respond(false, 'Invalid product id', 400);
}

/* ------------ Respond helper ------------ */
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

/* ------------ Uploads helpers ------------ */
function uploads_dir_abs(): string {
  if (defined('UPLOAD_DIR') && UPLOAD_DIR) {
    $rp = realpath(UPLOAD_DIR);
    return $rp !== false ? $rp : UPLOAD_DIR;
  }
  $fallback = __DIR__ . '/uploads';
  $rp = realpath($fallback);
  return $rp !== false ? $rp : $fallback;
}

function path_in_dir(string $path, string $baseDir): bool {
  $rp = realpath($path);
  $rb = realpath($baseDir);
  if ($rp === false || $rb === false) return false;
  return strpos($rp, $rb) === 0;
}

function is_default_image_basename(string $pathOrUrl): bool {
  // শুধু ফাইলনেম দেখে; case-insensitive
  $base = strtolower(basename($pathOrUrl));
  return $base === 'default.jpg';
}

function to_abs_upload_path(?string $image): ?string {
  if (!$image) return null;

  // URL হলে path অংশ
  if (preg_match('~^https?://~i', $image)) {
    $parts = parse_url($image);
    $image = $parts['path'] ?? '';
  }

  // BASE_URL/UPLOAD_URL প্রিফিক্স কেটে দিন
  if (defined('BASE_URL') && BASE_URL && strpos($image, BASE_URL) === 0) {
    $image = substr($image, strlen(BASE_URL));
  }
  if (defined('UPLOAD_URL') && UPLOAD_URL && strpos($image, UPLOAD_URL) === 0) {
    $image = substr($image, strlen(UPLOAD_URL));
  }

  // লিডিং স্ল্যাশ সরান
  $image = ltrim($image, '/');

  // 'uploads/...' থাকলে তার পরের অংশ নিন
  if (stripos($image, 'uploads/') === 0) {
    $rel = substr($image, strlen('uploads/'));
  } else {
    $rel = $image; // কখনো শুধু filename থাকে
  }

  return rtrim(uploads_dir_abs(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $rel;
}

function unlink_with_variants_if_safe(string $absPath, array &$deletedLog): void {
  $uploads = uploads_dir_abs();

  // সেফটি চেক
  if (!file_exists($absPath) || !is_file($absPath)) return;
  if (!path_in_dir($absPath, $uploads)) return;

  // default.jpg হলে কখনোই ডিলিট নয়
  if (is_default_image_basename($absPath)) return;

  // আসল ফাইল
  @unlink($absPath);
  $deletedLog[] = $absPath;

  // সম্ভাব্য ভেরিয়্যান্ট (_sm/_md/_lg বা -sm/-md/-lg)
  $base = basename($absPath);
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

/* ------------ Optional: same file used by other products? ------------ */
function file_used_by_other_products(PDO $pdo, string $absPath, int $currentId): bool {
  // যদি main products.image কলামে একই path/filename থাকে, তখন ফাইল ডিলিট না করি
  // (gallery শেয়ার্ড ধরা হচ্ছে না; চাইলে আলাদা লজিক যোগ করা যায়)
  $uploads = uploads_dir_abs();
  $rp = realpath($absPath);
  if ($rp === false) return false;

  // uploads relative path তৈরি
  $rel = ltrim(str_replace($uploads, '', $rp), DIRECTORY_SEPARATOR);
  $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);

  $cand1 = 'uploads/'.$rel;   // 'uploads/filename.jpg'
  $cand2 = '/uploads/'.$rel;  // '/uploads/filename.jpg'
  $cand3 = basename($rp);     // শুধু filename

  $sql = "SELECT COUNT(*) c FROM products
          WHERE id <> ? AND (image = ? OR image = ? OR image = ?)";
  $stm = $pdo->prepare($sql);
  $stm->execute([$currentId, $cand1, $cand2, $cand3]);
  return ((int)$stm->fetchColumn()) > 0;
}

/* ------------ Main ------------ */
try {
  // প্রোডাক্ট রেকর্ড
  $stm = $pdo->prepare("SELECT id, image FROM products WHERE id=?");
  $stm->execute([$id]);
  $prod = $stm->fetch(PDO::FETCH_ASSOC);
  if (!$prod) respond(false, 'Product not found', 404);

  $mainImage = $prod['image'] ?? null;

  // গ্যালারি ইমেজগুলো (column নাম path বা image—দুটোই হ্যান্ডেল)
  $gallery = [];
  try {
    $g = $pdo->prepare("SELECT COALESCE(path, image) AS img FROM product_images WHERE product_id=?");
    $g->execute([$id]);
    $gallery = $g->fetchAll(PDO::FETCH_COLUMN) ?: [];
  } catch (Throwable $e) {
    // টেবিল না থাকলে স্কিপ
    $gallery = [];
  }

  // ------- DB ট্রানজ্যাকশন: আগে DB delete, তারপর ফাইল unlink (atomic DB) -------
  $pdo->beginTransaction();

  // গ্যালারি রেকর্ড ডিলিট (থাকলে)
  try {
    $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
  } catch (Throwable $e) {
    // ignore if table missing
  }

  // প্রোডাক্ট ডিলিট
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);

  $pdo->commit();

  // ------- ফাইল ডিলিট (DB commit-এর পর) -------
  $deleted = [];

  // main image: default.jpg হলে কখনোই মুছবো না; নইলে অন্য প্রোডাক্টে ইউজ হচ্ছে কিনা দেখি
  if ($mainImage && !is_default_image_basename($mainImage)) {
    $abs = to_abs_upload_path($mainImage);
    if ($abs && file_exists($abs) && is_file($abs)) {
      if (!file_used_by_other_products($pdo, $abs, $id)) {
        unlink_with_variants_if_safe($abs, $deleted);
      }
    }
  }

  // gallery images: default.jpg হলে স্কিপ; নয়তো unlink
  if ($gallery) {
    foreach ($gallery as $gimg) {
      if (!$gimg || is_default_image_basename($gimg)) continue;
      $gabs = to_abs_upload_path($gimg);
      if ($gabs) unlink_with_variants_if_safe($gabs, $deleted);
    }
  }

  $msg = 'পণ্য ডিলিট সম্পন্ন';
  if (!empty($deleted)) $msg .= ' (ইমেজসহ)';
  respond(true, $msg);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  respond(false, 'Server error', 500);
}
