<?php
// admin/settings_save.php
// লক্ষ্য: কোনো HTML আউটপুট নয়। header.php ইনক্লুড করবেন না।
require_once __DIR__ . '/auth.php';       // নিশ্চিত করুন: এখানে echo/HTML নেই
require_once __DIR__ . '/../config.php';  // DB, helpers
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* Guard: POST only */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

/* CSRF */
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
  $_SESSION['flash'] = ['ok'=>false, 'message'=>'Invalid CSRF token'];
  header('Location: settings.php');  // কোনো আউটপুট ছাড়াই রিডাইরেক্ট
  exit;
}

/* Allowed keys (whitelist) */
$allowed = [
  'store_name', 'store_phone', 'store_email', 'store_about',
  'home_heading', 'home_notice',
  'cod_note',
  'delivery_dhaka', 'delivery_nationwide',
  'maintenance_enabled'
];

/* Normalize values */
$data = [];
foreach ($allowed as $k) {
  if ($k === 'maintenance_enabled') {
    $data[$k] = isset($_POST[$k]) ? '1' : '0';
  } elseif (in_array($k, ['delivery_dhaka','delivery_nationwide'], true)) {
    $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '0';
    $data[$k] = (string)(is_numeric($v) ? (int)$v : 0);
  } else {
    $data[$k] = isset($_POST[$k]) ? (string)$_POST[$k] : '';
  }
}

/* Save (UPSERT) */
try {
  $pdo->beginTransaction();
  $sql = "INSERT INTO settings(`key`,`value`) VALUES(?,?)
          ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)";
  $stm = $pdo->prepare($sql);
  foreach ($data as $k => $v) { $stm->execute([$k, $v]); }
  $pdo->commit();

  // optional flash
  $_SESSION['flash'] = ['ok'=>true, 'message'=>'সেটিংস সফলভাবে সেভ হয়েছে'];
  header('Location: settings.php?saved=1');
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash'] = ['ok'=>false, 'message'=>'সেভ ব্যর্থ হয়েছে'];
  header('Location: settings.php?error=1');
  exit;
}
