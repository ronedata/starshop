<?php require_once __DIR__.'/header.php';
$pdo=get_pdo();
$id=(int)($_POST['id'] ?? 0);
$status=$_POST['status'] ?? 'pending';
$allowed=['pending','processing','shipped','delivered','cancelled'];
if($id>0 && in_array($status,$allowed,true)){
  $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$id]);
}
header('Location: orders.php'); exit;
