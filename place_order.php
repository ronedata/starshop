<?php require_once __DIR__.'/config.php'; $pdo=get_pdo();
$cart=$_SESSION['cart'] ?? [];
if(!$cart){ header('Location: cart.php'); exit; }

$shipping_area = $_POST['shipping_area'] === 'nationwide' ? 'nationwide' : 'dhaka';
$ship_dhaka = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_dhaka'")->fetch()['value'] ?? 60);
$ship_nat = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_nationwide'")->fetch()['value'] ?? 120);
$shipping_fee = $shipping_area==='dhaka' ? $ship_dhaka : $ship_nat;

$name=trim($_POST['customer_name'] ?? '');
$mobile=trim($_POST['mobile'] ?? '');
$address=trim($_POST['address'] ?? '');
$note=trim($_POST['note'] ?? '');
if(!$name || !$mobile || !$address){ header('Location: checkout.php'); exit; }

// Build items
$ids = implode(',', array_map('intval', array_keys($cart)));
$rows = $pdo->query("SELECT id,name,price FROM products WHERE id IN ($ids) AND active=1")->fetchAll();
$subtotal=0; $items=[];
foreach($rows as $r){ $q=(int)$cart[$r['id']]; $line=$q*$r['price']; $subtotal+=$line; $items[]=['id'=>$r['id'],'price'=>$r['price'],'qty'=>$q]; }
$total = $subtotal + $shipping_fee;

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO orders (customer_name,mobile,address,shipping_area,shipping_fee,subtotal,total,payment_method,note) VALUES (?,?,?,?,?,?,?,?,?)")
    ->execute([$name,$mobile,$address,$shipping_area,$shipping_fee,$subtotal,$total,'COD',$note]);
$order_id = $pdo->lastInsertId();
$code = 'ORD'.str_pad($order_id,7,'0',STR_PAD_LEFT);
$pdo->prepare("UPDATE orders SET order_code=? WHERE id=?")->execute([$code,$order_id]);
$stm = $pdo->prepare("INSERT INTO order_items (order_id,product_id,qty,price) VALUES (?,?,?,?)");
foreach($items as $it){ $stm->execute([$order_id,$it['id'],$it['qty'],$it['price']]); }
$pdo->commit();

unset($_SESSION['cart']);
header('Location: success.php?id='.$order_id);
