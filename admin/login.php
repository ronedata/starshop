<?php
require_once __DIR__.'/../config.php';
if (!empty($_SESSION['admin_id'])) { header('Location: '.(BASE_URL.'/admin/index.php')); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  if ($username && $password) {
    $pdo = get_pdo();
    $stm = $pdo->prepare("SELECT * FROM admin_users WHERE username=? LIMIT 1");
    $stm->execute([$username]);
    $u = $stm->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
      $_SESSION['admin_id'] = (int)$u['id'];
      $_SESSION['admin_username'] = $u['username'];
      $next = $_GET['next'] ?? (BASE_URL.'/admin/index.php');
      header('Location: '.$next); exit;
    } else { $error = 'ইউজারনেম/পাসওয়ার্ড মিলছে না'; }
  } else { $error = 'সব ফিল্ড পূরণ করুন'; }
}
?>
<!doctype html><html lang="bn"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login - <?php echo APP_NAME; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3 text-center">Admin Login</h4>
          <?php if($error): ?><div class="alert alert-danger py-2"><?php echo h($error); ?></div><?php endif; ?>
          <form method="post">
            <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-primary w-100">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
