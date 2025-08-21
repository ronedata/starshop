<?php require_once __DIR__.'/config.php'; header('Content-Type: application/json');
$pid = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
if(!$pid){ echo json_encode(['ok'=>false,'message'=>'Invalid product']); exit; }
$pdo = get_pdo();
$stm=$pdo->prepare("SELECT id, name, price, image FROM products WHERE id=? AND active=1");
$stm->execute([$pid]); $p=$stm->fetch();
if(!$p){ echo json_encode(['ok'=>false,'message'=>'Product unavailable']); exit; }
if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
echo json_encode(['ok'=>true,'message'=>'কার্টে যোগ হয়েছে']);
