<?php require_once __DIR__.'/header.php';
$pdo=get_pdo();
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $username=trim($_POST['username'] ?? '');
  $password=$_POST['password'] ?? '';
  if($username && $password){
    $hash=password_hash($password, PASSWORD_BCRYPT);
    try{
      $pdo->prepare("INSERT INTO admin_users(username,password_hash) VALUES(?,?)")->execute([$username,$hash]);
      $msg='নতুন ইউজার যোগ হয়েছে';
    }catch(Throwable $e){ $msg='ইউজারনেমটি নেওয়া আছে'; }
  } else { $msg='সব ফিল্ড দিন'; }
}
if (isset($_GET['del'])){
  $id=(int)$_GET['del'];
  if($id!== (int)($_SESSION['admin_id'] ?? 0)){
    $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$id]);
  }
  header('Location: users.php'); exit;
}
$rows=$pdo->query("SELECT id,username,created_at FROM admin_users ORDER BY id DESC")->fetchAll();
?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card"><div class="card-body">
      <h5 class="card-title mb-3">নতুন অ্যাডমিন ইউজার</h5>
      <?php if($msg): ?><div class="alert alert-info py-2"><?php echo h($msg); ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary">Add User</button>
      </form>
    </div></div>
  </div>
  <div class="col-md-7">
    <div class="card"><div class="card-body">
      <h5 class="card-title mb-3">ইউজার লিস্ট</h5>
      <table class="table table-striped align-middle mb-0">
        <thead><tr><th>ID</th><th>Username</th><th>Created</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo h($r['username']); ?></td>
            <td><?php echo h($r['created_at']); ?></td>
            <td class="text-end">
              <?php if((int)$r['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                <a class="btn btn-sm btn-outline-danger" href="?del=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete user?')">Delete</a>
              <?php else: ?>
                <span class="text-muted small">current</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<?php require __DIR__.'/footer.php'; ?>
