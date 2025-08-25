<?php
require_once __DIR__.'/config.php';
$pdo = get_pdo();

// হেডার
include __DIR__.'/partials_header.php';

// Font Awesome (CDN) ইনজেক্ট: একবারই লোড হবে
?>
<script>
(function(){
  if(!document.querySelector('link[href*="font-awesome"][href*="cdnjs"]')){
    var l=document.createElement('link');
    l.rel='stylesheet';
    l.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css';
    document.head.appendChild(l);
  }
})();
</script>
<?php

// সেশন কার্ট → আইটেম/সাবটোটাল তৈরি
$cart = $_SESSION['cart'] ?? [];
$items = []; 
$subtotal = 0;

if ($cart) {
  $ids = implode(',', array_map('intval', array_keys($cart)));
  if ($ids !== '') {
    $rows = $pdo->query("SELECT id, name, price, image FROM products WHERE id IN ($ids) AND active=1")->fetchAll();
    foreach ($rows as $r) {
      $q = max(1, (int)($cart[$r['id']] ?? 1));
      $line = $q * (float)$r['price'];
      $subtotal += $line;
      $items[] = ['p' => $r, 'qty' => $q, 'line' => $line];
    }
  }
}
?>

<style>
  :root{
    --brand:#0ea5e9; --brand-600:#0284c7;
    --text:#0f172a; --muted:#64748b; --border:#e5e7eb; --bg:#f6f8fb; --white:#fff;
    --radius:14px; --shadow:0 10px 24px rgba(2,8,23,.08);
  }
  #cart{ max-width:1100px; margin:18px auto 28px; padding:0 14px; }
  #cart h2{ margin:4px 0 12px; }

  /* Table (desktop) */
  #cart .table{
    width:100%; border-collapse:separate; border-spacing:0;
    background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden;
  }
  #cart .table th, #cart .table td{ padding:12px; vertical-align:middle; }
  #cart .table thead th{
    font-weight:700; text-align:left; background:#f8fafc; color:#0f172a; border-bottom:1px solid var(--border);
  }
  #cart .table tbody tr:not(:last-child) td{ border-bottom:1px solid var(--border); }
  #cart .right{text-align:right;}
  #cart .pimg{ width:54px; height:54px; object-fit:cover; border:1px solid var(--border); border-radius:10px; background:#fff; margin-right:10px; }
  #cart .pname{ font-weight:600; color:#0f172a; }

  /* Qty stepper */
  #cart .qtyBox{
    display:grid; grid-template-columns:40px 60px 40px; align-items:center; gap:0;
    border:1px solid var(--border); border-radius:10px; overflow:hidden; background:#fff;
  }
  #cart .qtyBox input{
    height:40px; text-align:center; border:none; border-inline:1px solid var(--border); font-size:16px; width:100%;
  }
  #cart .qtyBox button{
    height:40px; border:none; background:#f8fafc; font-size:18px; cursor:pointer;
  }
  #cart .qtyBox button:hover{ background:#f1f5f9; }

  /* Buttons */
  #cart .btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px;
    min-height:44px; padding:0 14px; border-radius:10px; font-weight:700; text-decoration:none; cursor:pointer; }
  #cart .btn.secondary{ background:#fff; border:1px solid var(--border); color:#0f172a; }
  #cart .btn.secondary:hover{ box-shadow:var(--shadow); }
  #cart .btn.primary{ background:var(--brand); color:#fff; border:none; }
  #cart .btn.primary:hover{ background:var(--brand-600); }

  #cart .bar{ display:flex; justify-content:space-between; align-items:center; gap:10px; margin-top:12px; }
  #cart .subtotal{ font-weight:800; }

  /* Remove link with icon */
  #cart .remove{
    color:#ef4444; text-decoration:none; font-weight:600;
    display:inline-flex; align-items:center; gap:6px;
  }
  #cart .remove:hover{ text-decoration:underline; }
  #cart .remove i{ width:1em; }

  /* Empty state */
  #cart .empty{
    background:#fff; border:1px dashed var(--border); padding:18px; border-radius:12px;
  }

  /* ---------- Mobile: transform table to cards ---------- */
  @media (max-width: 640px){
    #cart .table{ border:none; background:transparent; }
    #cart .table thead{ display:none; }
    #cart .table tbody{ display:block; }
    #cart .table tr{
      display:block; background:#fff; border:1px solid var(--border); border-radius:12px; padding:12px; margin-bottom:12px;
    }
    #cart .table td{
      display:flex; align-items:center; justify-content:space-between;
      padding:8px 0; border:none !important;
    }
    #cart .table td::before{
      content: attr(data-label);
      font-weight:600; color:#475569; margin-right:12px; text-align:left;
    }
	#cart .table td{ gap:12px; } /* একটু স্পেস */
	#cart .qtyBox{
	  width: 144px;           /* 40 + 64 + 40 = 144 */
	  min-width: 144px;
	  flex: 0 0 144px;        /* shrink 0, grow 0, fixed basis */
	}
	#cart .qtyBox{
	  grid-template-columns: 40px 64px 40px;
	}
    /* First row: product block with image + name, no label */
    #cart .table td.cell-prod{ display:grid; grid-template-columns:62px 1fr; gap:10px; align-items:center; }
    #cart .table td.cell-prod::before{ content:''; display:none; }
    #cart .pimg{ width:62px; height:62px; margin:0; }
    #cart .right{ text-align:right; }
    /* Footer bar stack */
    #cart .bar{ flex-direction:column; align-items:flex-start; }
    #cart .bar .btn{ width:100%; }
    #cart .checkout{ position:sticky; bottom:10px; left:0; right:0; }
  }
</style>

<div id="cart">
  <h2>কার্ট</h2>

  <?php if (!$items): ?>
    <div class="empty">
      <p style="margin:0 0 10px;">কার্ট খালি।</p>
      <a class="btn secondary" href="index.php">শপিং শুরু করুন</a>
    </div>

  <?php else: ?>
    <!-- Qty আপডেটের জন্য ফর্ম -->
    <form method="post" action="cart_update.php" id="cartForm" novalidate>
		<input type="hidden" name="next" value="checkout.php">
      <table class="table">
        <thead>
          <tr>
            <th>পণ্য</th>
            <th>দাম</th>
            <th>পরিমাণ</th>
            <th class="right">লাইন টোটাল</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <!-- Product -->
            <td class="cell-prod" data-label="পণ্য">
              <img class="pimg" src="<?php echo h($it['p']['image'] ?: 'uploads/default.jpg'); ?>" alt="">
              <div class="pname"><?php echo h($it['p']['name']); ?></div>
            </td>

            <!-- Unit price -->
            <td class="priceCell" data-price="<?php echo (float)$it['p']['price']; ?>" data-label="দাম">
              <?php echo money_bd($it['p']['price']); ?>
            </td>

            <!-- Qty stepper -->
			<td data-label="পরিমাণ" class="cell-qty">
			  <div class="qtyBox" data-id="<?php echo (int)$it['p']['id']; ?>">
				<button type="button" class="btnDec" aria-label="কমান">−</button>
				<input type="number" class="qtyInput" name="qty[<?php echo (int)$it['p']['id']; ?>]" value="<?php echo (int)$it['qty']; ?>" min="1" inputmode="numeric" />
				<button type="button" class="btnInc" aria-label="বাড়ান">+</button>
			  </div>
			</td>

            <!-- Line total -->
            <td class="right lineTotalCell" data-label="লাইন টোটাল">
              <?php echo money_bd($it['line']); ?>
            </td>

            <!-- Remove -->
            <td data-label=" ">
              <a class="remove" href="cart_remove.php?id=<?php echo (int)$it['p']['id']; ?>" aria-label="রিমুভ">
                <i class="fa-solid fa-trash-can"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="bar">
        <button class="btn secondary" type="submit">কার্ট আপডেট করুন</button>
        <div class="subtotal">
          সাবটোটাল:
          <span id="cartSubtotal" data-subtotal="<?php echo (float)$subtotal; ?>">
            <?php echo money_bd($subtotal); ?>
          </span>
        </div>
      </div>
    </form>

    <p class="right checkout" style="margin-top:12px">
      <p class="right checkout" style="margin-top:12px">
		<button class="btn primary" type="submit" form="cartForm">চেকআউট</button>
	  </p>
    </p>

    <!-- Qty পরিবর্তনে লাইভ ক্যালকুলেশন -->
    <script>
    (function(){
      const table = document.querySelector('#cart .table');
      const subtotalEl = document.getElementById('cartSubtotal');

      function fmt(n){
        n = Math.round(Number(n)||0);
        return '৳' + n.toLocaleString('bn-BD');
      }

      function recalcAll(){
        let subtotal = 0;
        document.querySelectorAll('#cart tbody tr').forEach(row=>{
          const price = parseFloat(row.querySelector('.priceCell')?.dataset.price || 0);
          const qtyEl = row.querySelector('.qtyInput');
          const qty   = Math.max(1, parseInt(qtyEl?.value)||1);
          const line  = price * qty;
          const lineCell = row.querySelector('.lineTotalCell');
          if (lineCell) lineCell.textContent = fmt(line);
          subtotal += line;
        });
        if (subtotalEl) subtotalEl.textContent = fmt(subtotal);
      }

      // ইনপুট টাইপিং
      document.querySelectorAll('.qtyInput').forEach(inp=>{
        inp.addEventListener('input', ()=>{
          if ((inp.value||'').trim()==='') return;
          if (parseInt(inp.value) < 1) inp.value = 1;
          recalcAll();
        });
        inp.addEventListener('change', ()=>{
          if (parseInt(inp.value) < 1 || isNaN(parseInt(inp.value))) inp.value = 1;
          recalcAll();
        });
      });

      // + / - বাটন (ডেলিগেশন)
      table?.addEventListener('click', function(e){
        const dec = e.target.closest('.btnDec');
        const inc = e.target.closest('.btnInc');
        if (!dec && !inc) return;
        const wrap = e.target.closest('.qtyBox');
        const inp = wrap?.querySelector('.qtyInput');
        if (!inp) return;
        let v = parseInt(inp.value)||1;
        v += inc ? 1 : -1;
        if (v < 1) v = 1;
        inp.value = v;
        recalcAll();
      });

      // init
      recalcAll();
    })();
    </script>
  <?php endif; ?>
</div>

<?php
// ফুটার
include __DIR__.'/partials_footer.php';
