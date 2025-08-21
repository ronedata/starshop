<?php
require_once __DIR__.'/config.php';
$pdo = get_pdo();

$qParam   = trim($_GET['q']   ?? '');
$catParam = $_GET['cat'] ?? '';
$offset   = max(0, (int)($_GET['offset'] ?? 0));
$limit    = max(1, (int)($_GET['limit']  ?? 4)); // default 4

$where  = ["p.active = 1"];
$params = [];

if ($qParam !== '') {
  $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
  $params[] = '%'.$qParam.'%';
  $params[] = '%'.$qParam.'%';
}
if ($catParam !== '' && (int)$catParam > 0) {
  $where[]  = "p.category_id = ?";
  $params[] = (int)$catParam;
}

$whereSql = implode(' AND ', $where);

// LIMIT/OFFSET bind (INTEGER)
$sql = "SELECT p.*, c.name AS cat_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset";

$stm = $pdo->prepare($sql);
$i = 1;
foreach($params as $v){
  $stm->bindValue($i++, $v, PDO::PARAM_STR); // text params
}
$stm->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stm->bindValue(':offset', $offset, PDO::PARAM_INT);
$stm->execute();
$rows = $stm->fetchAll();

// যদি কিছু না থাকে, খালি রেসপন্স
if (!$rows) { echo ''; exit; }

// HTML কার্ড রেন্ডার
ob_start();
foreach($rows as $p): ?>
  <div class="card">
    <img src="<?php echo h($p['image'] ?: (BASE_URL.'/assets/placeholder.jpg')); ?>" alt="<?php echo h($p['name']); ?>">
    <div class="body">
      <?php if(!empty($p['cat_name'])): ?><div class="badge"><?php echo h($p['cat_name']); ?></div><?php endif; ?>
      <h3><?php echo h($p['name']); ?></h3>
      <div>
        <span class="price"><?php echo money_bd($p['price']); ?></span>
        <?php if(!empty($p['compare_at_price'])): ?>
          <span class="old"><?php echo money_bd($p['compare_at_price']); ?></span>
        <?php endif; ?>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?php echo BASE_URL; ?>/product.php?id=<?php echo (int)$p['id']; ?>">ডিটেইলস</a>
        <button class="btn" onclick="addToCart(<?php echo (int)$p['id']; ?>)">কার্টে যোগ করুন</button>
      </div>
    </div>
  </div>
<?php endforeach;
echo ob_get_clean();
