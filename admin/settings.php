<?php
// admin/settings.php
require_once __DIR__.'/header.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pdo = get_pdo();

/* CSRF */
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* settings লোড */
$settings = $pdo->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
function s($k,$d=''){ global $settings; return isset($settings[$k]) ? $settings[$k] : $d; }
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>

<style>
  /* মোবাইল-ফার্স্ট UX টিউন */
  .setting-card{max-width:1100px;margin:0 auto}
  .form-section-title{font-weight:700;margin:8px 0 2px}
  /* মোবাইলেই স্টিকি সেভ বার */
  @media (max-width: 992px){
    .mobile-savebar{
      position: sticky; bottom: -1px; left: 0; right: 0;
      background: #0f172a; padding: 10px; z-index: 5;
      display:flex; gap:10px; justify-content: flex-end; align-items:center;
      border-top: 1px solid rgba(255,255,255,.1);
    }
    .mobile-savebar .btn{ flex: 0 0 auto; }
    .mobile-savebar .hint{ color:#cbd5e1; font-size:12px; margin-right:auto; }
  }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="m-0">স্টোর সেটিংস</h5>
  <!-- ডেস্কটপ টপ-বার সেভ -->
  <button class="btn btn-primary d-none d-lg-inline-flex" form="settingsForm">Save</button>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    ✅ সেটিংস সফলভাবে সেভ হয়েছে।
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="card setting-card">
  <div class="card-body">
    <form id="settingsForm" method="post" action="settings_save.php" class="row g-3">
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">

      <div class="col-12"><div class="form-section-title">স্টোর তথ্য</div></div>

      <div class="col-12 col-lg-6">
        <label class="form-label">স্টোর নাম (store_name)</label>
        <input class="form-control" name="store_name" autocomplete="organization" value="<?=h(s('store_name'))?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label">ফোন (store_phone)</label>
        <input class="form-control" name="store_phone" autocomplete="tel" inputmode="tel" value="<?=h(s('store_phone'))?>">
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <label class="form-label">ইমেইল (store_email)</label>
        <input type="email" class="form-control" name="store_email" autocomplete="email" value="<?=h(s('store_email'))?>">
      </div>

      <div class="col-12">
        <label class="form-label">স্টোর সম্পর্কে (store_about)</label>
        <textarea class="form-control" name="store_about" rows="3" placeholder="স্টোরের সংক্ষিপ্ত পরিচিতি..."><?=h(s('store_about'))?></textarea>
      </div>

      <div class="col-12"><hr class="my-2"></div>
      <div class="col-12"><div class="form-section-title">হোম কনটেন্ট</div></div>

      <div class="col-12 col-lg-6">
        <label class="form-label">হোম পেজ শিরোনাম (home_heading)</label>
        <input class="form-control" name="home_heading" value="<?=h(s('home_heading'))?>">
      </div>

      <div class="col-12 col-lg-6">
        <label class="form-label">হোম নোটিস (home_notice)</label>
        <input class="form-control" name="home_notice" value="<?=h(s('home_notice'))?>">
      </div>

      <div class="col-12">
        <label class="form-label">COD নোট (cod_note)</label>
        <textarea class="form-control" name="cod_note" rows="2" placeholder="সারা দেশে ক্যাশ অন ডেলিভারি..."><?=h(s('cod_note'))?></textarea>
      </div>

      <div class="col-12"><hr class="my-2"></div>
      <div class="col-12"><div class="form-section-title">ডেলিভারি চার্জ</div></div>

      <div class="col-6 col-lg-3">
        <label class="form-label">ঢাকা (delivery_dhaka)</label>
        <input type="number" inputmode="numeric" class="form-control" name="delivery_dhaka" value="<?=h(s('delivery_dhaka','60'))?>">
      </div>

      <div class="col-6 col-lg-3">
        <label class="form-label">জাতীয় (delivery_nationwide)</label>
        <input type="number" inputmode="numeric" class="form-control" name="delivery_nationwide" value="<?=h(s('delivery_nationwide','120'))?>">
      </div>

      <div class="col-12 col-lg-6 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="maintenance_enabled"
                 name="maintenance_enabled" value="1" <?=((int)s('maintenance_enabled',0)?'checked':'')?>>
          <label class="form-check-label" for="maintenance_enabled">মেইনটেন্যান্স মোড চালু</label>
        </div>
      </div>

      <!-- ডেস্কটপে নিচেও Save -->
      <div class="col-12 d-none d-lg-block">
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary">Save</button>
        </div>
      </div>
    </form>
  </div>

  <!-- মোবাইল Sticky Save Bar -->
  <div class="mobile-savebar d-lg-none">
    <span class="hint">পরিবর্তন করলে সেভ করতে ভুলবেন না</span>
    <button class="btn btn-light" type="button" onclick="document.getElementById('settingsForm').reset()">Reset</button>
    <button class="btn btn-primary" type="submit" form="settingsForm">Save</button>
  </div>
</div>

<?php require __DIR__.'/footer.php'; ?>
