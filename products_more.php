<?php
// products_more.php
require_once __DIR__ . '/config.php';

$pdo = get_pdo();

// -------- Query params --------
$q      = trim($_GET['q'] ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = (int)($_GET['limit'] ?? 8);
if ($limit < 1)  $limit = 4;
if ($limit > 50) $limit = 50;

// -------- Build WHERE --------
$where  = ["p.active = 1"];
$params = [];

if ($q !== '') {
  $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
  $params[] = '%'.$q.'%';
  $params[] = '%'.$q.'%';
}
if ($cat > 0) {
  $where[] = "p.category_id = ?";
  $params[] = $cat;
}

$whereSql = implode(' AND ', $where);

// -------- Fetch rows (no extra markup, only cards) --------
try {
  // LIMIT/OFFSET ইন্টিজার কাস্ট করা হয়েছে, তাই সরাসরি ইনজেক্ট সেফ
  $sql = "
    SELECT p.*, c.name AS cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereSql
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
  ";
  $stm = $pdo->prepare($sql);
  $stm->execute($params);
  $rows = $stm->fetchAll();
} catch (Throwable $e) {
  // কোনো সমস্যা হলে কিছুই রিটার্ন করব না (JS বুঝবে আর কিছু নেই)
  $rows = [];
}

// -------- Output: EXACT same card markup as index.php --------
ob_start();
foreach ($rows as $p):
  $img = $p['image'] ?: (BASE_URL.'/uploads/default.jpg');
  ?>
  <div class="card">
    <a class="cover-link" href="<?=BASE_URL?>/product.php?id=<?=(int)$p['id']?>" aria-label="View details: <?=h($p['name'])?>">
      <img src="<?=h($img)?>" alt="<?=h($p['name'])?>">
    </a>
    <div class="body">
      <?php if(!empty($p['cat_name'])): ?><div class="badge"><?=h($p['cat_name'])?></div><?php endif; ?>
      <a class="cover-link" href="<?=BASE_URL?>/product.php?id=<?=(int)$p['id']?>">
        <h3 class="title"><?=h($p['name'])?></h3>
      </a>
      <div>
        <span class="price"><?=money_bd($p['price'])?></span>
        <?php if(!empty($p['compare_at_price'])): ?><span class="old"><?=money_bd($p['compare_at_price'])?></span><?php endif; ?>
      </div>
      <div class="actions">
        <button class="btn full" onclick="addToCart(<?=(int)$p['id']?>, event)">কার্টে যোগ করুন</button>
      </div>
    </div>
  </div>
<?php
endforeach;

// ফাইনালি প্রিন্ট (খালি হলে JS বুঝবে আর কিছু নেই)
echo trim(ob_get_clean());
