<?php
$pdo = get_pdo();
$store = [
 'name' => $pdo->query("SELECT value FROM settings WHERE `key`='store_name'")->fetch()['value'] ?? 'ABC',
 'phone' => $pdo->query("SELECT value FROM settings WHERE `key`='store_phone'")->fetch()['value'] ?? '',
 'about' => $pdo->query("SELECT value FROM settings WHERE `key`='store_about'")->fetch()['value'] ?? '',
 'delivery_dhaka' => $pdo->query("SELECT value FROM settings WHERE `key`='delivery_dhaka'")->fetch()['value'] ?? '60',
 'delivery_nationwide' => $pdo->query("SELECT value FROM settings WHERE `key`='delivery_nationwide'")->fetch()['value'] ?? '120',
 'cod_note' => $pdo->query("SELECT value FROM settings WHERE `key`='cod_note'")->fetch()['value'] ?? '',
];
?>
  </div>
</main>
<footer class="footer">
  <div class="container">
    <div class="cols">
      <div>
        <h3><?php echo h($store['name']); ?></h3>
        <p><?php echo h($store['about']); ?></p>
		<a href="<?php echo BASE_URL; ?>/track_orders.php"
         style="font-size:12px;color:#999;text-decoration:none;">
         Track Orders
		</a>
      </div>
      <div>
        <h4>যোগাযোগ</h4>
        <p>ফোন: <?php echo h($store['phone']); ?></p>
        <p>ইমেইল: <?php echo h($pdo->query("SELECT value FROM settings WHERE `key`='store_email'")->fetch()['value'] ?? ''); ?></p>
        <p class="small">সময়: সকাল ৮টা - রাত ১০টা</p>
      </div>
      <div>
        <h4>ডেলিভারি তথ্য</h4>
        <p>ঢাকায় ডেলিভারি: <?php echo money_bd($store['delivery_dhaka']); ?></p>
        <p>সারা দেশে: <?php echo money_bd($store['delivery_nationwide']); ?></p>
        <p class="small"><?php echo h($store['cod_note']); ?></p>
      </div>
    </div>
  <div class="container" style="text-align:center;padding:12px 0;color:#666;font-size:13px">
    <p style="margin:0; font-size:13px; color:#666;">
      &copy; <?php echo date('Y'); ?> <?php echo h($store['name']); ?>. সর্বস্বত্ব সংরক্ষিত | 
      <a href="<?php echo BASE_URL; ?>/admin/login.php"
         style="font-size:12px;color:#999;text-decoration:none;">
         অ্যাডমিন লগইন
      </a>
    </p>
  </div>
  </div>
</footer>
</body>
</html>
