<?php
// admin/product_form.php — Drag&Drop images + single video + YouTube + confirm modal for video remove
require_once __DIR__.'/../config.php';
require_once __DIR__.'/auth.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pdo = get_pdo();

/* ------------ Local helpers (no conflict with config) ------------ */
function pf_uploads_dir_abs(): string {
  if (defined('UPLOAD_DIR') && UPLOAD_DIR) { $rp = realpath(UPLOAD_DIR); return $rp!==false?$rp:UPLOAD_DIR; }
  $fallback = __DIR__.'/../uploads'; $rp = realpath($fallback); return $rp!==false?$rp:$fallback;
}
function pf_is_default(string $pathOrUrl): bool { return strtolower(basename($pathOrUrl)) === 'default.jpg'; }
function pf_path_in_dir(string $path, string $baseDir): bool {
  $rp = realpath($path); $rb = realpath($baseDir);
  if ($rp === false || $rb === false) return false;
  return strpos($rp, $rb) === 0;
}
function pf_is_windows_abs(string $p): bool { return (bool)preg_match('~^[A-Za-z]:\\\\~', $p); }
function pf_to_abs(string $p): ?string {
  $p = trim($p); if ($p==='') return null;
  if (pf_is_windows_abs($p) || (substr($p,0,1)==='/' && file_exists($p))) return $p;
  if (preg_match('~^https?://~i', $p)) { $parts = parse_url($p); $p = $parts['path'] ?? ''; }
  if (defined('BASE_URL') && BASE_URL && strpos($p, BASE_URL)===0)   $p = substr($p, strlen(BASE_URL));
  if (defined('UPLOAD_URL') && UPLOAD_URL && strpos($p, UPLOAD_URL)===0) $p = substr($p, strlen(UPLOAD_URL));
  $p = ltrim($p, '/'); if (stripos($p, 'uploads/')===0) $p = substr($p, strlen('uploads/'));
  return rtrim(pf_uploads_dir_abs(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
}
function pf_unlink_with_variants(string $abs, array &$log): void {
  $uploads = pf_uploads_dir_abs();
  if (!file_exists($abs) || !is_file($abs)) return;
  if (!pf_path_in_dir($abs, $uploads)) return;
  if (pf_is_default($abs)) return;
  @unlink($abs); $log[]=$abs;
  $dir=dirname($abs); $b=basename($abs); $n=pathinfo($b,PATHINFO_FILENAME); $e=pathinfo($b,PATHINFO_EXTENSION);
  foreach (['_sm','_md','_lg','-sm','-md','-lg'] as $suf) {
    $cand = $dir.DIRECTORY_SEPARATOR.$n.$suf.($e?'.'.$e:'');
    if (file_exists($cand) && is_file($cand)) { @unlink($cand); $log[]=$cand; }
  }
}
/* ---- YouTube helpers ---- */
function pf_youtube_id_from_url(string $url): ?string {
  $url = trim($url); if ($url==='') return null;
  $res = [
    '~youtu\.be/([A-Za-z0-9_\-]{6,})~i',
    '~youtube\.com/(?:watch\?v=|embed/|v/|shorts/)([A-Za-z0-9_\-]{6,})~i',
    '~[?&]v=([A-Za-z0-9_\-]{6,})~'
  ];
  foreach($res as $re){ if (preg_match($re,$url,$m)) return $m[1]; }
  return null;
}
function pf_youtube_canonical(string $id): string { return 'https://www.youtube.com/watch?v='.$id; }

/* ---- Tag helpers (for Free Delivery + Hot Item) ---- */
function pf_tags_to_array_preserve(string $tags): array {
  $parts = preg_split('/[\n\r;,|#]+/u', $tags);
  $out = [];
  foreach ($parts as $t) { $t = trim($t); if ($t!=='') $out[]=$t; }
  return $out;
}
function pf_array_casein_remove(array $arr, array $removeList): array {
  $out = [];
  foreach ($arr as $v) {
    $keep = true;
    foreach ($removeList as $r) {
      if (mb_strtolower(trim($v)) === mb_strtolower(trim($r))) { $keep = false; break; }
    }
    if ($keep) $out[] = $v;
  }
  return $out;
}
function pf_array_casein_has(array $arr, array $needles): bool {
  foreach ($arr as $v) {
    $lv = mb_strtolower(trim($v));
    foreach ($needles as $n) if ($lv === mb_strtolower(trim($n))) return true;
  }
  return false;
}

/* ------------ CSRF & constants ------------ */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
if (!is_dir(pf_uploads_dir_abs())) { @mkdir(pf_uploads_dir_abs(), 0777, true); }
define('DEFAULT_IMG_URL', (defined('UPLOAD_URL')?rtrim(UPLOAD_URL,'/'):'').'/default.jpg');

/* ------------ ID ------------ */
$id = (int)($_GET['id'] ?? 0);

/* ========================
   Delete a single image (DB + file) — (GET keep as-is)
   ======================== */
if (isset($_GET['delimg']) && $id>0){
  $imgId=(int)$_GET['delimg'];
  $stm=$pdo->prepare("SELECT image FROM product_images WHERE id=? AND product_id=?");
  $stm->execute([$imgId,$id]); $imgUrl=$stm->fetchColumn();
  if ($imgUrl){
    $pdo->prepare("DELETE FROM product_images WHERE id=? AND product_id=?")->execute([$imgId,$id]);
    if (!pf_is_default($imgUrl)) { $abs=pf_to_abs($imgUrl); if($abs){ $tmp=[]; pf_unlink_with_variants($abs,$tmp); } }
    // main fix
    $m=$pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY sort_order,id LIMIT 1");
    $m->execute([$id]); $main=$m->fetchColumn();
    if (!$main){
      $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,0)")
          ->execute([$id, DEFAULT_IMG_URL]);
      $main=DEFAULT_IMG_URL;
    }
    $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$main,$id]);
  }
  header('Location: product_form.php?id='.$id.'&updated=1'); exit;
}

/* ========================
   Remove Video (POST + CSRF) with confirm modal
   ======================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rm_video_file'])){
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $_SESSION['flash']=['ok'=>false,'msg'=>'CSRF token mismatch']; header('Location: products.php'); exit; }
  $pid=(int)($_POST['id'] ?? 0);
  if ($pid>0){
    try{
      $row=$pdo->prepare("SELECT id,url FROM product_videos WHERE product_id=? AND type='file' LIMIT 1");
      $row->execute([$pid]); $v=$row->fetch(PDO::FETCH_ASSOC);
      if ($v){
        $pdo->prepare("DELETE FROM product_videos WHERE id=?")->execute([$v['id']]);
        $abs=pf_to_abs($v['url'] ?? ''); if($abs){ $lg=[]; pf_unlink_with_variants($abs,$lg); }
      }
    }catch(Throwable $e){}
  }
  header('Location: product_form.php?id='.$pid.'&updated=1'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rm_video_yt'])){
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) { $_SESSION['flash']=['ok'=>false,'msg'=>'CSRF token mismatch']; header('Location: products.php'); exit; }
  $pid=(int)($_POST['id'] ?? 0);
  if ($pid>0){
    try{ $pdo->prepare("DELETE FROM product_videos WHERE product_id=? AND type='youtube'")->execute([$pid]); }catch(Throwable $e){}
  }
  header('Location: product_form.php?id='.$pid.'&updated=1'); exit;
}

/* ========================
   Save (Add / Update) BEFORE output
   ======================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_product'])){
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']){
    $_SESSION['flash']=['ok'=>false,'msg'=>'সিকিউরিটি টোকেন মিলেনি']; header('Location: products.php'); exit;
  }
  $pid    = (int)($_POST['id'] ?? 0);
  $name   = trim($_POST['name'] ?? '');
  $cat    = (int)($_POST['category_id'] ?? 0);
  $price  = (float)($_POST['price'] ?? 0);
  $compare= ($_POST['compare_at_price']!=='') ? (float)$_POST['compare_at_price'] : null;
  $stock  = (int)($_POST['stock'] ?? 0);
  $tagsIn = trim($_POST['tags'] ?? '');
  $specs  = trim($_POST['specifications'] ?? '');
  $desc   = trim($_POST['description'] ?? '');
  $active = isset($_POST['active']) ? 1 : 0;
  $youtube_url = trim($_POST['youtube_url'] ?? '');

  // NEW: flags from checkboxes
  $flag_free = isset($_POST['flag_free']) ? 1 : 0;
  $flag_hot  = isset($_POST['flag_hot'])  ? 1 : 0;

  // Normalize tags wrt flags
  $tagArr = pf_tags_to_array_preserve($tagsIn);
  // remove synonyms if unchecked (or to re-add clean)
  $tagArr = pf_array_casein_remove($tagArr, ['free','free shipping','free delivery']);
  $tagArr = pf_array_casein_remove($tagArr, ['hot','hot item']);

  if ($flag_free) { if (!pf_array_casein_has($tagArr, ['Free Shipping'])) $tagArr[]='Free Shipping'; }
  if ($flag_hot)  { if (!pf_array_casein_has($tagArr, ['Hot'])) $tagArr[]='Hot'; }

  $tags = implode(', ', array_values(array_unique($tagArr)));

  try{
    if ($pid>0){
      $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, price=?, compare_at_price=?, stock=?, tags=?, specifications=?, active=? WHERE id=?")
          ->execute([$cat,$name,$desc,$price,$compare,$stock,$tags,$specs,$active,$pid]);
      $newId=$pid; $flash='পণ্য আপডেট হয়েছে';
    }else{
      $pdo->prepare("INSERT INTO products(category_id,name,description,price,compare_at_price,stock,tags,specifications,active) VALUES(?,?,?,?,?,?,?,?,?)")
          ->execute([$cat,$name,$desc,$price,$compare,$stock,$tags,$specs,$active]);
      $newId=(int)$pdo->lastInsertId(); $flash='নতুন পণ্য সংরক্ষণ হয়েছে';
    }

    /* ---- Images: multiple (images[]) ---- */
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])){
      $f=$_FILES['images']; $total=count($f['name']);
      for($i=0;$i<$total;$i++){
        if (empty($f['name'][$i]) || $f['error'][$i]!==UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($f['tmp_name'][$i])) continue;
        $ext=strtolower(pathinfo($f['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])) continue;
        $fname='product_'.$newId.'_'.time().'_img'.$i.'.'.$ext;
        $dest=rtrim(pf_uploads_dir_abs(),'/').'/'.$fname;
        if (move_uploaded_file($f['tmp_name'][$i], $dest)){
          $url=(defined('UPLOAD_URL')?rtrim(UPLOAD_URL,'/'):'').'/'.$fname;
          $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,?)")->execute([$newId,$url,$i]);
        }
      }
    }

    /* ---- Video: single file ---- */
    if (!empty($_FILES['video']['name']) && $_FILES['video']['error']===UPLOAD_ERR_OK && is_uploaded_file($_FILES['video']['tmp_name'])){
      $ext=strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
      if (in_array($ext,['mp4','webm','ogg','ogv','mov','m4v'])){
        // delete old file if exists
        try{
          $old=$pdo->prepare("SELECT id,url FROM product_videos WHERE product_id=? AND type='file' LIMIT 1");
          $old->execute([$newId]); $ov=$old->fetch(PDO::FETCH_ASSOC);
          if ($ov){ $abs=pf_to_abs($ov['url']??''); if($abs){ $lg=[]; pf_unlink_with_variants($abs,$lg); } $pdo->prepare("DELETE FROM product_videos WHERE id=?")->execute([$ov['id']]); }
        }catch(Throwable $e){}
        // save new
        $fname='product_'.$newId.'_'.time().'_video.'.$ext;
        $dest=rtrim(pf_uploads_dir_abs(),'/').'/'.$fname;
        if (move_uploaded_file($_FILES['video']['tmp_name'], $dest)){
          $url=(defined('UPLOAD_URL')?rtrim(UPLOAD_URL,'/'):'').'/'.$fname;
          $pdo->prepare("INSERT INTO product_videos(product_id,type,url,sort_order) VALUES(?,'file',?,0)")->execute([$newId,$url]);
        }
      }
    }

    /* ---- YouTube: single ---- */
    try{
      if ($youtube_url===''){
        $pdo->prepare("DELETE FROM product_videos WHERE product_id=? AND type='youtube'")->execute([$newId]);
      }else{
        $vid=pf_youtube_id_from_url($youtube_url);
        if ($vid){
          $canon=pf_youtube_canonical($vid);
          $pdo->prepare("DELETE FROM product_videos WHERE product_id=? AND type='youtube'")->execute([$newId]);
          $pdo->prepare("INSERT INTO product_videos(product_id,type,url,sort_order) VALUES(?,'youtube',?,0)")->execute([$newId,$canon]);
        }
      }
    }catch(Throwable $e){}

    /* ---- Ensure main image ---- */
    $m=$pdo->prepare("SELECT image FROM product_images WHERE product_id=? ORDER BY sort_order,id LIMIT 1");
    $m->execute([$newId]); $main=$m->fetchColumn();
    if (!$main){
      $pdo->prepare("INSERT INTO product_images(product_id,image,sort_order) VALUES(?,?,0)")->execute([$newId, DEFAULT_IMG_URL]);
      $main=DEFAULT_IMG_URL;
    }
    $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$main,$newId]);

    $_SESSION['flash']=['ok'=>true,'msg'=>$flash];
    header('Location: products.php'); exit;

  }catch(Throwable $e){
    $_SESSION['flash']=['ok'=>false,'msg'=>'সংরক্ষণ ব্যর্থ হয়েছে'];
    header('Location: products.php'); exit;
  }
}

/* ------------ Load data for render ------------ */
$cats=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$edit=null; $images=[]; $video_file=null; $video_yt=null;

if ($id>0){
  $stm=$pdo->prepare("SELECT * FROM products WHERE id=?"); $stm->execute([$id]); $edit=$stm->fetch(PDO::FETCH_ASSOC);
  try{ $q=$pdo->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id"); $q->execute([$id]); $images=$q->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){}
  try{
    $vf=$pdo->prepare("SELECT * FROM product_videos WHERE product_id=? AND type='file' LIMIT 1"); $vf->execute([$id]); $video_file=$vf->fetch(PDO::FETCH_ASSOC) ?: null;
    $vy=$pdo->prepare("SELECT * FROM product_videos WHERE product_id=? AND type='youtube' LIMIT 1"); $vy->execute([$id]); $video_yt=$vy->fetch(PDO::FETCH_ASSOC) ?: null;
  }catch(Throwable $e){}
}

/* ---- Derive flags from existing tags for checkbox default ---- */
$tagsExisting = (string)($edit['tags'] ?? '');
$tagArrExist  = pf_tags_to_array_preserve($tagsExisting);
$is_free_checked = pf_array_casein_has($tagArrExist, ['free','free shipping','free delivery']);
$is_hot_checked  = pf_array_casein_has($tagArrExist, ['hot','hot item']);

/* ------------ View ------------ */
require_once __DIR__.'/header.php';
?>
<style>
  .thumb{height:74px;width:74px;object-fit:cover;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
  .btn-pill{border-radius:999px}
  .img-chip{position:relative;display:inline-block}
  .img-chip .btn-del{position:absolute;top:-8px;right:-8px;border-radius:50%;line-height:1;padding:2px 7px}
  .dropzone{border:2px dashed #cbd5e1;border-radius:12px;padding:14px;text-align:center;background:#f8fafc;cursor:pointer}
  .dropzone.drag{background:#eef2ff;border-color:#6366f1}
  .dz-previews{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
  .dz-previews .chip{position:relative;width:70px;height:70px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff}
  .dz-previews img{width:100%;height:100%;object-fit:cover;display:block}
  .dz-previews .x{position:absolute;top:-8px;right:-8px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;line-height:20px;text-align:center;font-weight:700;cursor:pointer}
  @media(max-width:576px){ .btn-toolbar.gap-2>.btn{width:100%} }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0"><?php echo $edit ? 'পণ্য এডিট' : 'নতুন পণ্য'; ?></h5>
  <div class="btn-toolbar gap-2">
    <a href="products.php" class="btn btn-outline-secondary btn-pill">পণ্য তালিকা</a>
    <?php if($edit): ?>
      <button type="button" class="btn btn-danger btn-pill ms-2" data-bs-toggle="modal" data-bs-target="#delModal"
              data-id="<?php echo (int)$edit['id']; ?>" data-name="<?php echo h($edit['name'] ?? ''); ?>">ডিলিট</button>
    <?php endif; ?>
  </div>
</div>

<div class="card"><div class="card-body">
  <form method="post" enctype="multipart/form-data" id="productForm">
    <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">নাম</label>
        <input name="name" class="form-control" required value="<?php echo h($edit['name'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">ক্যাটেগরি</label>
        <select name="category_id" class="form-select">
          <option value="">-- None --</option>
          <?php foreach($cats as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php echo (isset($edit['category_id']) && (int)$edit['category_id']===(int)$c['id'])?'selected':''; ?>>
              <?php echo h($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">মূল্য</label>
        <input type="number" step="0.01" name="price" class="form-control" required value="<?php echo h($edit['price'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">তুলনামূলক দাম</label>
        <input type="number" step="0.01" name="compare_at_price" class="form-control" value="<?php echo h($edit['compare_at_price'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">স্টক</label>
        <input type="number" name="stock" class="form-control" required value="<?php echo h($edit['stock'] ?? '0'); ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">ট্যাগ (কমা দিয়ে আলাদা)</label>
        <input type="text" name="tags" class="form-control" value="<?php echo h($edit['tags'] ?? ''); ?>">
        <div class="form-text">উদাহরণ: <code>new, best, discount</code>. নিচের চেকবক্স টিক দিলে ট্যাগে স্বয়ংক্রিয়ভাবে যোগ/বিয়োগ হবে।</div>
        <div class="mt-2 d-flex flex-wrap gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="flag_free" name="flag_free" <?php echo $is_free_checked?'checked':''; ?>>
            <label class="form-check-label" for="flag_free">Free Delivery</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="flag_hot" name="flag_hot" <?php echo $is_hot_checked?'checked':''; ?>>
            <label class="form-check-label" for="flag_hot">Hot Item</label>
          </div>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">স্পেসিফিকেশন</label>
        <textarea name="specifications" class="form-control" rows="3" placeholder="বিস্তারিত স্পেসিফিকেশন..."><?php echo h($edit['specifications'] ?? ''); ?></textarea>
      </div>
      <div class="col-12">
        <label class="form-label">বিবরণ</label>
        <textarea name="description" class="form-control" rows="4"><?php echo h($edit['description'] ?? ''); ?></textarea>
      </div>

      <!-- Drag & Drop Images -->
      <div class="col-12">
        <label class="form-label">ছবি (Drag & Drop / Multiple)</label>
        <div id="dropzone" class="dropzone" tabindex="0">
          <div class="small text-muted">এখানে ছবি টেনে আনুন বা ক্লিক করে সিলেক্ট করুন (jpg, jpeg, png, webp)</div>
          <div class="dz-previews" id="dzPreviews"></div>
        </div>
        <input type="file" id="imagesInput" name="images[]" class="form-control mt-2" accept=".jpg,.jpeg,.png,.webp" multiple>
        <?php if(!empty($images)): ?>
          <div class="mt-2 d-flex flex-wrap gap-2">
            <?php foreach($images as $im): ?>
              <div class="img-chip">
                <img src="<?php echo h($im['image']); ?>" class="thumb" alt="">
                <a href="?id=<?php echo (int)($edit['id']); ?>&delimg=<?php echo (int)$im['id']; ?>"
                   class="btn btn-sm btn-danger btn-del" title="এই ছবি ডিলিট করুন">×</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small mt-1">ছবি না থাকলে ডিফল্ট <code>uploads/default.jpg</code> সেট হবে।</div>
        <?php endif; ?>
      </div>

      <!-- Single Video Upload -->
      <div class="col-12">
        <label class="form-label">ভিডিও আপলোড (mp4/webm/ogg/mov/m4v, ১টি)</label>
        <input type="file" name="video" class="form-control" accept="video/mp4,video/webm,video/ogg,video/ogv,video/quicktime,video/x-m4v">
        <?php if($video_file): ?>
          <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
            <video controls preload="metadata" src="<?php echo h($video_file['url']); ?>" style="max-width:340px;width:100%"></video>
            <!-- OPEN CONFIRM MODAL (file remove) -->
            <button type="button" class="btn btn-outline-danger btn-pill"
                    data-bs-toggle="modal" data-bs-target="#rmVideoFileModal">
              ভিডিও রিমুভ
            </button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Single YouTube URL -->
      <div class="col-12">
        <label class="form-label">YouTube লিংক (১টি)</label>
        <div class="input-group">
          <input type="url" name="youtube_url" class="form-control"
                 placeholder="https://youtu.be/... বা https://www.youtube.com/watch?v=..."
                 value="<?php echo h($video_yt['url'] ?? ''); ?>">
          <?php if($video_yt): ?>
            <!-- OPEN CONFIRM MODAL (yt remove) -->
            <button type="button" class="btn btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#rmVideoYtModal">
              রিমুভ
            </button>
          <?php endif; ?>
        </div>
        <div class="form-text">খালি রেখে Save করলে YouTube লিংক মুছে যাবে।</div>
      </div>

      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="active" id="activeChk"
            <?php echo (isset($edit['active']) ? ((int)$edit['active'] ? 'checked' : '') : 'checked'); ?>>
          <label for="activeChk" class="form-check-label">Active</label>
        </div>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary btn-pill" name="save_product" value="1"><?php echo $edit ? 'Update' : 'Save'; ?></button>
      <a href="products.php" class="btn btn-outline-secondary btn-pill">ক্যান্সেল</a>
    </div>
  </form>
</div></div>

<!-- Product delete confirm modal -->
<?php if($edit): ?>
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header py-2">
      <h6 class="modal-title">পণ্য ডিলিট</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ করুন"></button>
    </div>
    <div class="modal-body">
      আপনি কি নিশ্চিত?<br>
      <strong id="delName"></strong> ডিলিট হলে সংশ্লিষ্ট ইমেজ/ভিডিও (default.jpg ছাড়া) মুছে যাবে।
    </div>
    <div class="modal-footer py-2">
      <button type="button" class="btn btn-secondary btn-pill" data-bs-dismiss="modal">বাতিল</button>
      <form id="delForm" method="post" action="product_delete.php" class="d-inline">
        <input type="hidden" name="id"   value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
        <button type="submit" class="btn btn-danger btn-pill">ডিলিট</button>
      </form>
    </div>
  </div></div>
</div>
<?php endif; ?>

<!-- Confirm modal: remove uploaded video file -->
<?php if($edit && $video_file): ?>
<div class="modal fade" id="rmVideoFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header py-2">
      <h6 class="modal-title">ভিডিও ফাইল রিমুভ</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ করুন"></button>
    </div>
    <div class="modal-body">
      এই আপলোডেড ভিডিওটি ডিলিট করবেন? (ফাইলসহ স্থায়ীভাবে মুছে যাবে)
    </div>
    <div class="modal-footer py-2">
      <button type="button" class="btn btn-secondary btn-pill" data-bs-dismiss="modal">বাতিল</button>
      <form method="post" action="product_form.php" class="d-inline">
        <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
        <input type="hidden" name="id"   value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <input type="hidden" name="rm_video_file" value="1">
        <button type="submit" class="btn btn-danger btn-pill">হ্যাঁ, ডিলিট করুন</button>
      </form>
    </div>
  </div></div>
</div>
<?php endif; ?>

<!-- Confirm modal: remove YouTube link -->
<?php if($edit && $video_yt): ?>
<div class="modal fade" id="rmVideoYtModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header py-2">
      <h6 class="modal-title">YouTube লিংক রিমুভ</h6>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ করুন"></button>
    </div>
    <div class="modal-body">
      এই YouTube লিংকটি ডিলিট করবেন?
    </div>
    <div class="modal-footer py-2">
      <button type="button" class="btn btn-secondary btn-pill" data-bs-dismiss="modal">বাতিল</button>
      <form method="post" action="product_form.php" class="d-inline">
        <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
        <input type="hidden" name="id"   value="<?php echo (int)($edit['id'] ?? 0); ?>">
        <input type="hidden" name="rm_video_yt" value="1">
        <button type="submit" class="btn btn-danger btn-pill">হ্যাঁ, ডিলিট করুন</button>
      </form>
    </div>
  </div></div>
</div>
<?php endif; ?>

<script>
/* Product delete modal fill */
(function(){
  const delModal=document.getElementById('delModal'); if(!delModal) return;
  delModal.addEventListener('show.bs.modal', function (ev) {
    const btn=ev.relatedTarget; const pid=btn?.getAttribute('data-id')||''; const name=btn?.getAttribute('data-name')||'';
    delModal.querySelector('#delName').textContent = name ? `“${name}”` : `#${pid}`;
    delModal.querySelector('#delForm input[name="id"]').value = pid;
  });
})();

/* Drag & Drop (multiple) with live preview + removable chips */
(function(){
  const dz = document.getElementById('dropzone');
  const input = document.getElementById('imagesInput');
  const previews = document.getElementById('dzPreviews');
  if(!dz || !input || !previews) return;

  let dt = new DataTransfer();
  function syncInput(){ input.files = dt.files; }
  function addFiles(files){
    for (const file of files){
      if (!file || !file.type.match(/^image\/(jpeg|png|webp)$/)) continue;
      const key = file.name+'|'+file.size+'|'+file.lastModified;
      let exists=false;
      for (let i=0;i<dt.files.length;i++){
        const f=dt.files[i];
        const k=f.name+'|'+f.size+'|'+f.lastModified;
        if (k===key){ exists=true; break; }
      }
      if (exists) continue;
      dt.items.add(file);
      const reader = new FileReader();
      reader.onload = e=>{
        const chip = document.createElement('div');
        chip.className='chip'; chip.dataset.key=key;
        chip.innerHTML = '<img src="'+e.target.result+'"><button class="x" type="button" aria-label="Remove">×</button>';
        previews.appendChild(chip);
      };
      reader.readAsDataURL(file);
    }
    syncInput();
  }
  function removeByKey(key){
    const ndt = new DataTransfer();
    for (let i=0;i<dt.files.length;i++){
      const f=dt.files[i];
      const k=f.name+'|'+f.size+'|'+f.lastModified;
      if (k!==key) ndt.items.add(f);
    }
    dt = ndt; syncInput();
  }
  dz.addEventListener('click', ()=> input.click());
  dz.addEventListener('dragover', (e)=>{ e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', ()=> dz.classList.remove('drag'));
  dz.addEventListener('drop', (e)=>{ e.preventDefault(); dz.classList.remove('drag'); if (e.dataTransfer?.files?.length) addFiles(e.dataTransfer.files); });
  input.addEventListener('change', (e)=>{ if (e.target.files?.length) addFiles(e.target.files); });
  previews.addEventListener('click', (e)=>{
    if (!e.target.classList.contains('x')) return;
    const chip = e.target.closest('.chip'); if (!chip) return;
    const key = chip.dataset.key; chip.remove(); removeByKey(key);
  });
})();
</script>

<?php require __DIR__.'/footer.php'; ?>
