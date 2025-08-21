<?php require_once __DIR__.'/header.php';
$fields = [
 'store_name' => setting('store_name','Star Shop'),
 'store_phone' => setting('store_phone',''),
 'store_email' => setting('store_email',''),
 'delivery_dhaka' => setting('delivery_dhaka','60'),
 'delivery_nationwide' => setting('delivery_nationwide','120'),
 'home_logo' => setting('home_logo',''),
 'home_heading' => setting('home_heading',''),
 'home_notice' => setting('home_notice',''),
 'maintenance_enabled' => setting('maintenance_enabled','0'),
];
?>
<div class="card"><div class="card-body">
  <h5 class="card-title mb-3">সেটিংস</h5>
  <form method="post" action="settings_save.php" enctype="multipart/form-data">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">স্টোর নাম</label><input name="store_name" class="form-control" value="<?php echo h($fields['store_name']); ?>"></div>
      <div class="col-md-4"><label class="form-label">ফোন</label><input name="store_phone" class="form-control" value="<?php echo h($fields['store_phone']); ?>"></div>
      <div class="col-md-4"><label class="form-label">ইমেইল</label><input name="store_email" type="email" class="form-control" value="<?php echo h($fields['store_email']); ?>"></div>
      <div class="col-md-3"><label class="form-label">Delivery (Dhaka)</label><input name="delivery_dhaka" type="number" class="form-control" value="<?php echo h($fields['delivery_dhaka']); ?>"></div>
      <div class="col-md-3"><label class="form-label">Delivery (Nationwide)</label><input name="delivery_nationwide" type="number" class="form-control" value="<?php echo h($fields['delivery_nationwide']); ?>"></div>
      <div class="col-md-6"><label class="form-label">Homepage Logo</label><input type="file" name="home_logo" class="form-control"><?php if ($fields['home_logo']): ?><div class="mt-2"><img src="<?php echo h($fields['home_logo']); ?>" style="height:50px"></div><?php endif; ?></div>
      <div class="col-md-6"><label class="form-label">Homepage Heading</label><input name="home_heading" class="form-control" value="<?php echo h($fields['home_heading']); ?>"></div>
      <div class="col-md-6"><label class="form-label">Homepage Notice</label><textarea name="home_notice" rows="3" class="form-control"><?php echo h($fields['home_notice']); ?></textarea></div>
      <div class="col-md-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="maintenance_enabled" id="mchk" <?php echo $fields['maintenance_enabled']=='1'?'checked':''; ?>><label for="mchk" class="form-check-label">Maintenance Mode</label></div></div>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Save</button></div>
  </form>
</div></div>
<?php require __DIR__.'/footer.php'; ?>
