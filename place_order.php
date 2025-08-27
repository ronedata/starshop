<?php
// place_order.php — Free Shipping: কার্টের সব প্রোডাক্টে 'Free' ট্যাগ থাকলেই শিপিং ফি ০
require_once __DIR__.'/config.php';
$pdo = get_pdo();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ---- খালি কার্ট হলে ফেরত ---- */
$cart = $_SESSION['cart'] ?? [];
if (!$cart) { header('Location: cart.php'); exit; }

/* ---- ইনপুট ---- */
$shipping_area = ($_POST['shipping_area'] ?? 'dhaka') === 'nationwide' ? 'nationwide' : 'dhaka';
$name    = trim($_POST['customer_name'] ?? '');
$mobile  = trim($_POST['mobile'] ?? '');
$address = trim($_POST['address'] ?? '');
$note    = trim($_POST['note'] ?? '');

/* ===============================
   BD Mobile Normalize & Validate
   - Accepts: 01XXXXXXXXX | 8801XXXXXXXXX | +8801XXXXXXXXX
   - Returns: ['ok'=>true, 'local'=>'01…', 'e164'=>'+8801…'] অথবা ['ok'=>false,'reason'=>'…']
================================= */
function normalize_bd_mobile($raw){
  $s = preg_replace('/[^\d+]/', '', trim((string)$raw)); // শুধুই ডিজিট ও '+' রাখুন
  if (substr_count($s, '+') > 1) return ['ok'=>false, 'reason'=>'Invalid plus sign'];
  if (strpos($s, '+') === 0) $s = substr($s, 1); // শুরুর '+' বাদ

  // Local: 01XXXXXXXXX
  if (preg_match('/^01[3-9]\d{8}$/', $s)) {
    return ['ok'=>true, 'local'=>$s, 'e164'=>'+880' . substr($s,1)];
  }
  // 880-prefixed: 8801XXXXXXXXX
  if (preg_match('/^8801[3-9]\d{8}$/', $s)) {
    return ['ok'=>true, 'local'=>'0' . substr($s,3), 'e164'=>'+' . $s];
  }
  return ['ok'=>false, 'reason'=>'Invalid BD mobile'];
}

/* ---- ইনপুট ভ্যালিডেশন ---- */
$nv = normalize_bd_mobile($mobile);
if (!$name || !$address || !$nv['ok']) {
  $_SESSION['flash_error'] = !$nv['ok']
    ? 'মোবাইল নম্বর সঠিক নয়। অনুগ্রহ করে 01XXXXXXXXX বা +8801XXXXXXXXX দিন।'
    : 'প্রয়োজনীয় তথ্য পূরণ করুন।';
  header('Location: checkout.php'); exit;
}
$mobile_local = $nv['local'];
$mobile_e164  = $nv['e164']; // ভবিষ্যতে দরকার হলে DB-তে আলাদা কলামে রাখবেন

/* ---- শিপিং সেটিংস ---- */
$ship_dhaka = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_dhaka'")->fetchColumn() ?? 60);
$ship_nat   = (float)($pdo->query("SELECT value FROM settings WHERE `key`='delivery_nationwide'")->fetchColumn() ?? 120);

/* ---- Free tag detector ---- */
function has_free_tag($tags){
  // product.php-র সাথে কম্প্যাটিবল ডিটেকশন
  return (bool)preg_match('/\bfree(ship(ping)?)?\b/i', (string)$tags);
}

/* ---- কার্ট আইডি/রো ---- */
$ids = implode(',', array_map('intval', array_keys($cart)));
$rows = $pdo->query("SELECT id,name,price,tags FROM products WHERE id IN ($ids) AND active=1")->fetchAll();
if (!$rows) { header('Location: cart.php'); exit; }

/* ---- সাবটোটাল/আইটেমস ---- */
$items = [];
$subtotal = 0.0;
foreach ($rows as $r) {
  $pid = (int)$r['id'];
  $qty = max(1, (int)($cart[$pid] ?? 0));
  if ($qty < 1) continue;
  $line = $qty * (float)$r['price'];
  $subtotal += $line;
  $items[] = ['id'=>$pid, 'price'=>(float)$r['price'], 'qty'=>$qty, 'tags'=>($r['tags'] ?? '')];
}
if (!$items) { header('Location: cart.php'); exit; }

/* ---- FREE SHIPPING RULE (ALL items must be free) ----
   - একাধিক প্রোডাক্ট থাকলে: সবগুলোর tags-এ Free থাকলেই free shipping
   - যেকোনো একটিতেও না থাকলে: free shipping নয়
   - ক্লায়েন্টের POST free_shipping ফ্ল্যাগকে ট্রাস্ট না করে এখানে নিজে হিসাব করছি
------------------------------------------------------- */
$all_have_free = count($items) > 0;
foreach ($items as $it) {
  if (!has_free_tag($it['tags'])) { $all_have_free = false; break; }
}
$free_shipping = $all_have_free;

/* ---- shipping fee ---- */
if ($free_shipping) {
  $shipping_fee = 0.0;               // ✅ DB-তে ০ ইনসার্ট হবে
  // চাইলে shipping_area 'dhaka' ফিক্সড রাখতে পারেন; এখন ইউজারের সিলেকশনই রেখে দিচ্ছি
} else {
  $shipping_fee = ($shipping_area === 'dhaka') ? $ship_dhaka : $ship_nat;
}

$total = $subtotal + $shipping_fee;

/* ===============================
   12-hour Local DateTime (Asia/Dhaka)
   Example: 2025-Aug-27 09:05 PM
================================= */
$tzDhaka = new DateTimeZone('Asia/Dhaka');
$createdLocal = new DateTime('now', $tzDhaka);
$created_local_str = $createdLocal->format('Y-M-d h:i A'); // <-- 12h ফরম্যাট
// success.php-তে দেখানোর জন্য সেশনে রেখে দিচ্ছি (ইচ্ছা করলে ব্যবহার/মুছে দিন)
$_SESSION['last_order_time_local'] = $created_local_str;

/* ---- DB ট্রানজ্যাকশন ---- */
try {
  $pdo->beginTransaction();

  // নোট: orders টেবিলে created_at (DATETIME / TIMESTAMP) থাকলে এই কুয়েরি ব্যবহার করুন।
  // না থাকলে created_at অংশটি ড্রপ/কমেন্ট করুন।
  $ins = $pdo->prepare("INSERT INTO orders
    (customer_name, mobile, address, shipping_area, shipping_fee, subtotal, total, payment_method, note)
    VALUES (?,?,?,?,?,?,?,?,?)");
  // mobile হিসেবে লোকাল 01… ফরম্যাট সেভ করছি
  $ins->execute([$name, $mobile_local, $address, $shipping_area, $shipping_fee, $subtotal, $total, 'COD', $note]);

  $order_id = (int)$pdo->lastInsertId();
  $code = 'ORD' . str_pad((string)$order_id, 7, '0', STR_PAD_LEFT);
  $pdo->prepare("UPDATE orders SET order_code=? WHERE id=?")->execute([$code, $order_id]);

  $stm = $pdo->prepare("INSERT INTO order_items (order_id, product_id, qty, price) VALUES (?,?,?,?)");
  foreach ($items as $it) {
    $stm->execute([$order_id, $it['id'], $it['qty'], $it['price']]);
  }

  $pdo->commit();

  // অর্ডার সম্পন্ন: কার্ট খালি করে success
  unset($_SESSION['cart']);
  header('Location: success.php?id='.(int)$order_id);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = 'দুঃখিত, অর্ডার সম্পন্ন হয়নি। আবার চেষ্টা করুন।';
  header('Location: checkout.php'); exit;
}
