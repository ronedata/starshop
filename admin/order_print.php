<?php
require_once __DIR__.'/../config.php';
$pdo=get_pdo();
$id=(int)($_GET['id'] ?? 0);
$stm=$pdo->prepare("SELECT * FROM orders WHERE id=?"); $stm->execute([$id]); $o=$stm->fetch();
if(!$o){ echo "Order not found"; exit; }
$it=$pdo->prepare("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE order_id=?");
$it->execute([$id]); $items=$it->fetchAll();
?>
<!doctype html><html lang="bn"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Print - #<?php echo h($o['order_code']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>@media print {.no-print{display:none!important}} body{padding:20px}</style>
</head><body>
<div class="d-flex justify-content-between align-items-center no-print">
  <h5 class="m-0">Print Order</h5><button class="btn btn-sm btn-primary" onclick="window.print()">Print</button>
</div><hr class="no-print">
<h4 class="mb-1">#<?php echo h($o['order_code']); ?></h4>
<div class="text-muted mb-3"><?php echo h($o['created_at']); ?></div>
<table class="table table-bordered">
  <thead><tr><th>পণ্য</th><th class="text-center">পরিমাণ</th><th class="text-end">দাম</th><th class="text-end">লাইন</th></tr></thead>
  <tbody>
    <?php foreach($items as $r): ?>
    <tr>
      <td><?php echo h($r['name']); ?></td>
      <td class="text-center"><?php echo (int)$r['qty']; ?></td>
      <td class="text-end"><?php echo money_bd($r['price']); ?></td>
      <td class="text-end"><?php echo money_bd($r['price']*$r['qty']); ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><th colspan="3" class="text-end">সাবটোটাল</th><th class="text-end"><?php echo money_bd($o['subtotal']); ?></th></tr>
    <tr><th colspan="3" class="text-end">ডেলিভারি</th><th class="text-end"><?php echo money_bd($o['shipping_fee']); ?></th></tr>
    <tr><th colspan="3" class="text-end">মোট</th><th class="text-end"><?php echo money_bd($o['total']); ?></th></tr>
  </tfoot>
</table>
</body></html>
