<?php require_once __DIR__.'/header.php';
$store_name = trim($_POST['store_name'] ?? '');
$store_phone= trim($_POST['store_phone'] ?? '');
$store_email= trim($_POST['store_email'] ?? '');
$delivery_dhaka = (float)($_POST['delivery_dhaka'] ?? 60);
$delivery_nationwide = (float)($_POST['delivery_nationwide'] ?? 120);
$home_heading = trim($_POST['home_heading'] ?? '');
$home_notice  = trim($_POST['home_notice'] ?? '');
$maintenance_enabled = isset($_POST['maintenance_enabled']) ? '1' : '0';
save_setting('store_name',$store_name);
save_setting('store_phone',$store_phone);
save_setting('store_email',$store_email);
save_setting('delivery_dhaka',$delivery_dhaka);
save_setting('delivery_nationwide',$delivery_nationwide);
save_setting('home_heading',$home_heading);
save_setting('home_notice',$home_notice);
save_setting('maintenance_enabled',$maintenance_enabled);
if (!empty($_FILES['home_logo']['name'])) {
  $f = $_FILES['home_logo'];
  if ($f['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (in_array($ext,['jpg','jpeg','png','webp'])) {
      $fname='logo_'.time().'.'.$ext;
      move_uploaded_file($f['tmp_name'], UPLOAD_DIR.'/'.$fname);
      save_setting('home_logo', UPLOAD_URL.'/'.$fname);
    }
  }
}
header('Location: settings.php'); exit;
