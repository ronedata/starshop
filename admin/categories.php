<?php require_once __DIR__.'/header.php';
$pdo = get_pdo();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $id   = (int)($_POST['id'] ?? 0);
  if ($name) {
    if ($id > 0) { $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name,$id]); }
    else { $pdo->prepare("INSERT INTO categories(name) VALUES(?)")->execute([$name]); }
  }
  header('Location: categories.php'); exit;
}
if (isset($_GET['del'])) { $id = (int)$_GET['del']; $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); header('Location: categories.php'); exit; }
$rows = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) { $id = (int)$_GET['edit']; $stm = $pdo->prepare("SELECT * FROM categories WHERE id=?"); $stm->execute([$id]); $edit = $stm->fetch(); }
?>
<div class="row g-3">
  <div class="col-12 col-md-5">
    <div class="card"><div class="card-body">
      <h5 class="card-title mb-3"><?php echo $edit ? 'ক্যাটেগরি এডিট' : 'ক্যাটেগরি যুক্ত করুন'; ?></h5>
      <form method="post">
        <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <div class="mb-3"><label class="form-label">নাম</label><input name="name" class="form-control" required value="<?php echo h($edit['name'] ?? ''); ?>"></div>
        <button class="btn btn-primary"><?php echo $edit ? 'Update' : 'Save'; ?></button>
        <?php if($edit): ?><a href="categories.php" class="btn btn-secondary ms-2">ক্যান্সেল</a><?php endif; ?>
      </form>
    </div></div>
  </div>
  <div class="col-12 col-md-7">
    <div class="card"><div class="card-body">
      <h5 class="card-title mb-3">ক্যাটেগরি লিস্ট</h5>
      <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead><tr><th>ID</th><th>Name</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo h($r['name']); ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int)$r['id']; ?>">Edit</a>
              <a class="btn btn-sm btn-outline-danger" href="?del=<?php echo (int)$r['id']; ?>" onclick="return confirm('Delete category?')">Delete</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </div>
</div>
<?php require __DIR__.'/footer.php'; ?>
