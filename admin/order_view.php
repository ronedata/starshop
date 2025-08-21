<?php require_once __DIR__.'/header.php';
$pdo=get_pdo();
$id=(int)($_GET['id'] ?? 0);
$stm=$pdo->prepare("SELECT * FROM orders WHERE id=?"); $stm->execute([$id]); $o=$stm->fetch();
if(!$o){ echo '<div class="alert alert-warning">Order not found</div>'; require __DIR__.'/footer.php'; exit; }
$it=$pdo->prepare("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE order_id=?"); $it->execute([$id]); $items=$it->fetchAll();
?>
<div class="card"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center">
    <h5 class="m-0">Order #<?php echo h($o['order_code']); ?></h5>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="order_print.php?id=<?php echo (int)$o['id']; ?>">Print</a>
  </div>
  <hr>
  <div class="row g-3">
    <div class="col-md-6">
      <h6>Customer</h6>
      <div><?php echo h($o['customer_name']); ?></div>
      <div><?php echo h($o['mobile']); ?></div>
      <div><?php echo nl2br(h($o['address'])); ?></div>
    </div>
    <div class="col-md-6">
      <h6>Order Info</h6>
      <div>Status: <span class="badge bg-secondary"><?php echo h($o['status']); ?></span></div>
      <div>Shipping: <?php echo h($o['shipping_area']); ?> (<?php echo money_bd($o['shipping_fee']); ?>)</div>
      <div>Subtotal: <?php echo money_bd($o['subtotal']); ?></div>
      <div>Total: <strong><?php echo money_bd($o['total']); ?></strong></div>
      <div>Date: <?php echo h($o['created_at']); ?></div>
    </div>
  </div>
  <hr>
  <h6>Items</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Line</th></tr></thead>
      <tbody>
        <?php foreach($items as $r): ?>
        <tr>
          <td><?php echo h($r['name']); ?></td>
          <td><?php echo (int)$r['qty']; ?></td>
          <td><?php echo money_bd($r['price']); ?></td>
          <td><?php echo money_bd($r['price']*$r['qty']); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div></div>
<?php require __DIR__.'/footer.php'; ?>
