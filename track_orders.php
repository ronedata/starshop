<?php
// track_orders.php
require_once __DIR__ . '/config.php';
$pdo = get_pdo();

/* ---------- Helpers: বাংলা ডিজিট/টাকা ---------- */
function bn_digits($s){
  return strtr((string)$s, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
}
function bn_money_fallback($n){
  $n = round((float)$n);
  $s = number_format($n, 0, '.', ',');
  return '৳' . bn_digits($s);
}
function moneyBD($n){
  return function_exists('money_bd') ? money_bd($n) : bn_money_fallback($n);
}
function dateBD($ts){
  // d M, Y g:i A → শুধুই ডিজিটগুলো বাংলা হবে
  return bn_digits(date('d M, Y g:i A', is_numeric($ts)? (int)$ts : strtotime($ts)));
}
function statusBadgeInfo($status){
  $s = strtolower(trim((string)$status));
  // আপনার সিস্টেমের status মান অনুযায়ী প্রয়োজন হলে ম্যাপিং বদলান
  $map = [
    'pending'    => ['পেন্ডিং','badge gray'],
    'processing' => ['প্রসেসিং','badge blue'],
    'packed'     => ['প্যাকড','badge blue'],
    'shipped'    => ['শিপড','badge indigo'],
    'delivered'  => ['ডেলিভারড','badge green'],
    'cancelled'  => ['ক্যানসেল্ড','badge red'],
    'canceled'   => ['ক্যানসেল্ড','badge red'],
    'returned'   => ['রিটার্নড','badge orange'],
  ];
  return $map[$s] ?? [$status ?: 'স্ট্যাটাস নেই', 'badge gray'];
}

/* ---------- ইনপুট ---------- */
$mobileParam = trim($_GET['m'] ?? '');
$orders = [];

if ($mobileParam !== '') {
  // 11-ডিজিটে নরমালাইজ (BD): 8801xxxx → 01xxxx
  $digits = preg_replace('/\D+/', '', $mobileParam);
  if (strpos($digits,'8801')===0 && strlen($digits)>=13) $digits = '0'.substr($digits,3);
  if (strpos($digits,'881')===0  && strlen($digits)>=12) $digits = '0'.substr($digits,2);
  if (strlen($digits)===10 && $digits[0]!=='0') $digits = '0'.$digits;

  // সাধারণভাবে DB-তে যেভাবে থাকে তার কয়েকটি ক্যান্ডিডেট ফর্ম
  $cands = [$digits, '+88'.$digits, '88'.$digits];
  // ফাঁকা বা খুব ছোট হলে না চালানোই ভালো
  $cands = array_values(array_filter($cands, fn($v)=>strlen($v)>=6));

  if ($cands){
    $in = implode(',', array_fill(0, count($cands), '?'));
    $sql = "SELECT * FROM orders WHERE mobile IN ($in) ORDER BY created_at DESC";
    $stm = $pdo->prepare($sql);
    $stm->execute($cands);
    $orders = $stm->fetchAll();
  }
}

include __DIR__ . '/partials_header.php';
?>

<style>
  :root{
    --brand:#0ea5e9; --brand-600:#0284c7;
    --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --bg:#f6f8fb; --white:#fff;
    --radius:14px; --shadow:0 10px 24px rgba(2,8,23,.08);
  }
  #trk{ max-width:1100px; margin:18px auto 28px; padding:0 14px; }

  /* Search Card */
  #trk .card{
    background:#fff; border:1px solid var(--border); border-radius:var(--radius);
    box-shadow:0 2px 6px rgba(2,8,23,.02); padding:14px;
  }
  #trk .search{
    display:grid; grid-template-columns:1fr auto; gap:10px; align-items:center;
  }
  #trk .search input{
    height:46px; border:1px solid var(--border); border-radius:12px; padding:10px 12px; font-size:16px; outline:none;
  }
  #trk .search input:focus{ border-color:#cfe3ff; box-shadow:0 0 0 4px #e6f0ff; }
  #trk .btn{ min-height:46px; padding:0 16px; border:none; border-radius:12px; background:var(--brand); color:#fff; font-weight:800; cursor:pointer; }
  #trk .btn:hover{ background:var(--brand-600); }

  /* Badges */
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border:1px solid var(--border); border-radius:999px; font-weight:700; background:#fff; }
  .badge.gray{ color:#334155; }
  .badge.blue{ color:#1e40af; background:#eff6ff; border-color:#dbeafe; }
  .badge.indigo{ color:#3730a3; background:#eef2ff; border-color:#e0e7ff; }
  .badge.green{ color:#065f46; background:#ecfdf5; border-color:#d1fae5; }
  .badge.red{ color:#b91c1c; background:#fee2e2; border-color:#fecaca; }
  .badge.orange{ color:#9a3412; background:#fff7ed; border-color:#fed7aa; }

  /* Result: desktop table */
  #trk .table{
    width:100%; border-collapse:separate; border-spacing:0; overflow:hidden;
    background:#fff; border:1px solid var(--border); border-radius:12px; margin-top:14px;
  }
  #trk .table th, #trk .table td{ padding:12px; vertical-align:middle; }
  #trk .table thead th{ background:#f8fafc; text-align:left; border-bottom:1px solid var(--border); }
  #trk .table tbody tr:not(:last-child) td{ border-bottom:1px solid var(--border); }
  #trk .right{text-align:right;}
  #trk .link{ color:#0f172a; font-weight:800; text-decoration:none; }
  #trk .link:hover{ text-decoration:underline; }

  /* Mobile: card list */
  @media (max-width: 700px){
    #trk .table{ display:none; }
    #trk .list{ display:grid; gap:12px; margin-top:14px; }
    #trk .item{
      background:#fff; border:1px solid var(--border); border-radius:12px; padding:12px;
    }
    #trk .row{ display:flex; justify-content:space-between; gap:10px; margin:6px 0; }
    #trk .lbl{ color:#64748b; font-weight:600; }
    #trk .val{ font-weight:700; }
    #trk .head{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
  }
  @media (min-width: 701px){
    #trk .list{ display:none; }
  }
</style>

<div id="trk">
  <h2>অর্ডার ট্র্যাক করুন</h2>

  <!-- Search -->
  <div class="card">
    <form class="search" method="get" action="track_orders.php" autocomplete="off" id="trackForm">
      <input
        type="tel"
        name="m"
        value="<?php echo htmlspecialchars($mobileParam, ENT_QUOTES); ?>"
        placeholder="মোবাইল নম্বর দিন (01XXXXXXXXX)"
        pattern="^(\+?88)?0?1[0-9]{9}$"
        required
      >
      <button class="btn" type="submit">খুঁজুন</button>
    </form>
    <p class="small" style="color:#64748b;margin:8px 2px 0">
      মোবাইল নম্বর দিয়ে আপনার সব অর্ডার দেখুন (সর্বশেষটি আগে)।
    </p>
  </div>

  <?php if ($mobileParam !== ''): ?>
    <?php if (!$orders): ?>
      <p style="margin-top:12px">এই নম্বরে কোনো অর্ডার পাওয়া যায়নি। নম্বরটি ঠিক আছে কি না যাচাই করুন।</p>
    <?php else: ?>
      <!-- Desktop: table -->
      <table class="table">
        <thead>
          <tr>
            <th>তারিখ</th>
            <th>অর্ডার আইডি</th>
            <th>নাম</th>
            <th>স্ট্যাটাস</th>
            <th class="right">মোট</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $r): 
          $total = $r['grand_total'] ?? $r['total'] ?? $r['total_amount'] ?? $r['payable'] ?? $r['amount'] ?? 0;
          [$stText,$stClass] = statusBadgeInfo($r['status'] ?? '');
          ?>
          <tr>
            <td><?php echo dateBD($r['created_at'] ?? ''); ?></td>
            <td><a class="link" href="success.php?id=<?php echo (int)$r['id']; ?>">#<?php echo htmlspecialchars($r['order_code'] ?? $r['id']); ?></a></td>
            <td><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></td>
            <td><span class="<?php echo $stClass; ?>"><?php echo $stText; ?></span></td>
            <td class="right"><?php echo moneyBD($total); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Mobile: card list -->
      <div class="list">
        <?php foreach ($orders as $r): 
          $total = $r['grand_total'] ?? $r['total'] ?? $r['total_amount'] ?? $r['payable'] ?? $r['amount'] ?? 0;
          [$stText,$stClass] = statusBadgeInfo($r['status'] ?? '');
          ?>
          <div class="item">
            <div class="head">
              <a class="link" href="success.php?id=<?php echo (int)$r['id']; ?>">#<?php echo htmlspecialchars($r['order_code'] ?? $r['id']); ?></a>
              <span class="<?php echo $stClass; ?>"><?php echo $stText; ?></span>
            </div>
            <div class="row"><div class="lbl">তারিখ</div><div class="val"><?php echo dateBD($r['created_at'] ?? ''); ?></div></div>
            <div class="row"><div class="lbl">নাম</div><div class="val"><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></div></div>
            <div class="row"><div class="lbl">মোট</div><div class="val"><?php echo moneyBD($total); ?></div></div>
            <div class="row"><div class="lbl">ডিটেইল</div><div class="val"><a class="link" href="success.php?id=<?php echo (int)$r['id']; ?>">দেখুন →</a></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
// লাস্ট সার্চ করা মোবাইল লোকালস্টোরে রেখে দেই (UX)
(function(){
  const f = document.getElementById('trackForm');
  const i = f?.querySelector('input[name="m"]');
  if(!i) return;
  try{
    if(!i.value && localStorage.getItem('lastMobile')) i.value = localStorage.getItem('lastMobile');
    f.addEventListener('submit', ()=> localStorage.setItem('lastMobile', i.value || ''));
  }catch(e){}
})();
</script>

<?php include __DIR__ . '/partials_footer.php'; ?>
