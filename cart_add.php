<?php
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $pid = (int)($_POST['product_id'] ?? 0);
  $qty = max(1, (int)($_POST['qty'] ?? 1));

  if ($pid <= 0) { echo json_encode(['ok'=>false,'message'=>'Invalid product']); exit; }

  $pdo = get_pdo();
  $stm = $pdo->prepare("SELECT id, name, price FROM products WHERE id=? AND active=1");
  $stm->execute([$pid]);
  $p = $stm->fetch();

  if (!$p) { echo json_encode(['ok'=>false,'message'=>'Product unavailable']); exit; }

  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) { $_SESSION['cart'] = []; }
  $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;

  $count = 0; foreach ($_SESSION['cart'] as $q) { $count += (int)$q; }

  echo json_encode(['ok'=>true,'message'=>'কার্টে যোগ হয়েছে','count'=>$count]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'message'=>'Server error']);
}
