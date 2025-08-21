<?php
require_once __DIR__.'/../config.php';
if (empty($_SESSION['admin_id'])) {
  $next = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL.'/admin/index.php'));
  header('Location: '. (BASE_URL.'/admin/login.php?next='.$next));
  exit;
}
?>
