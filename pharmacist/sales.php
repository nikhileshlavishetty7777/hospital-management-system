<?php
// ============================================================
// pharmacist/sales.php — Sales Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['pharmacist','admin']);

$pageTitle = 'Sales';

$filterDate = clean($_GET['date'] ?? date('Y-m-d'));

$sales = Database::fetchAll("
    SELECT ps.id, ps.sale_no, ps.total, ps.discount, ps.paid, ps.payment_method, ps.created_at,
           u.full_name AS patient_name, p.patient_code,
           u_s.full_name AS sold_by
    FROM pharmacy_sales ps
    LEFT JOIN patients p ON p.id=ps.patient_id
    LEFT JOIN users    u ON u.id=p.user_id
    JOIN users         u_s ON u_s.id=ps.sold_by
    WHERE DATE(ps.created_at)=?
    ORDER BY ps.created_at DESC
", [$filterDate]);

$daySummary = Database::fetchOne("
    SELECT COUNT(*) AS total_orders,
           COALESCE(SUM(paid),0)  AS total_revenue,
           COALESCE(SUM(total),0) AS gross_total,
           COALESCE(SUM(discount),0) AS total_discount
    FROM pharmacy_sales WHERE DATE(created_at)=?", [$filterDate]);

$topMeds = Database::fetchAll("
    SELECT m.name, SUM(psi.qty) AS qty_sold, SUM(psi.subtotal) AS revenue
    FROM pharmacy_sale_items psi
    JOIN medicines m ON m.id=psi.medicine_id
    JOIN pharmacy_sales ps ON ps.id=psi.sale_id
    WHERE DATE(ps.created_at)=?
    GROUP BY m.id ORDER BY qty_sold DESC LIMIT 5
", [$filterDate]);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-cart-shopping me-2 text-primary"></i>Sales</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pharmacist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Sales</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#posModal">
          <i class="fa-solid fa-plus me-2"></i>New Sale (POS)
        </button>
      </div>

      <!-- Day stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-shopping-cart"></i></div><div><div class="stat-value" data-counter="<?= $daySummary['total_orders']??0 ?>">0</div><div class="stat-label">Orders</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="stat-value" data-counter="<?= number_format($daySummary['total_revenue']??0,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Revenue</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-tag"></i></div><div><div class="stat-value" data-counter="<?= number_format($daySummary['total_discount']??0,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Discounts</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div><div><div class="stat-value" data-counter="<?= number_format($daySummary['gross_total']??0,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Gross Sales</div></div></div></div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Top selling medicines today -->
        <div class="col-xl-5">
          <div class="card h-100">
            <div class="card-header fw-700"><i class="fa-solid fa-fire me-2 text-danger"></i>Top Selling Today</div>
            <div class="card-body p-0">
              <?php if(empty($topMeds)): ?>
              <div class="text-center py-4 text-muted">No sales recorded for this date.</div>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <thead><tr><th>Medicine</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody>
                  <?php foreach ($topMeds as $m): ?>
                  <tr>
                    <td class="fw-600 text-sm"><?= htmlspecialchars($m['name']) ?></td>
                    <td><span class="badge bg-primary"><?= $m['qty_sold'] ?></span></td>
                    <td class="fw-700 text-success">₹<?= number_format($m['revenue'],2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Date filter -->
        <div class="col-xl-7">
          <div class="card h-100">
            <div class="card-header fw-700"><i class="fa-solid fa-filter me-2"></i>Filter Sales</div>
            <div class="card-body">
              <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                  <label class="form-label">Sales Date</label>
                  <input type="date" name="date" class="form-control" value="<?= $filterDate ?>"/>
                </div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100 mt-md-auto"><i class="fa-solid fa-search me-1"></i>View</button></div>
                <div class="col-md-3"><a href="?" class="btn btn-outline-secondary w-100 mt-md-auto"><i class="fa-solid fa-calendar me-1"></i>Today</a></div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales Table -->
      <div class="card animate-fade-in">
        <div class="card-header"><i class="fa-solid fa-receipt me-2 text-primary"></i>Sales on <?= date('d M Y',strtotime($filterDate)) ?></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="salesTable">
              <thead><tr><th>#</th><th>Sale No</th><th>Patient</th><th>Total</th><th>Discount</th><th>Paid</th><th>Method</th><th>Sold By</th><th>Time</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($sales as $i => $s): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-primary"><?= htmlspecialchars($s['sale_no']) ?></span></td>
                  <td><?= $s['patient_name'] ? htmlspecialchars($s['patient_name']) : '<span class="text-muted">Walk-in</span>' ?></td>
                  <td class="fw-700">₹<?= number_format($s['total'],2) ?></td>
                  <td class="text-success">-₹<?= number_format($s['discount'],2) ?></td>
                  <td class="text-success fw-600">₹<?= number_format($s['paid'],2) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($s['payment_method']) ?></span></td>
                  <td class="text-sm"><?= htmlspecialchars($s['sold_by']) ?></td>
                  <td class="text-muted text-xs"><?= date('h:i A',strtotime($s['created_at'])) ?></td>
                  <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="HMS.toast('Loading receipt…','info')"><i class="fa-solid fa-print"></i></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- POS Modal -->
<div class="modal fade" id="posModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-cash-register me-2 text-primary"></i>Point of Sale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Patient (optional)</label>
            <input type="text" id="posPatSearch" class="form-control" placeholder="Search patient or leave blank for walk-in…"/>
            <input type="hidden" id="posPatId"/>
            <div id="posPatResults"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Search Medicine</label>
            <input type="text" id="posMedSearch" class="form-control" placeholder="Type medicine name to add…"/>
            <div id="posMedResults" class="mt-1"></div>
          </div>
        </div>
        <hr/>
        <div id="posCart" class="mb-3"><p class="text-muted text-center text-sm py-2">No items — search and add medicines above</p></div>
        <div class="row g-2">
          <div class="col-md-3"><label class="form-label">Discount (₹)</label><input type="number" id="posDisc" class="form-control" value="0" min="0" oninput="posRecalc()"/></div>
          <div class="col-md-3"><label class="form-label">Payment Method</label><select id="posMeth" class="form-select"><option value="cash">Cash</option><option value="card">Card</option><option value="upi">UPI</option></select></div>
          <div class="col-md-3"><label class="form-label">Amount Received (₹)</label><input type="number" id="posCash" class="form-control" value="0" oninput="posRecalc()"/></div>
          <div class="col-md-3">
            <label class="form-label">Change (₹)</label>
            <input type="text" id="posChange" class="form-control" readonly/>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-6 fw-700 text-lg">Total: <span id="posTotal" class="text-primary">₹0.00</span></div>
          <div class="col-6 text-end fw-700">Net Payable: <span id="posNet" class="text-success">₹0.00</span></div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success ripple-btn" onclick="completeSale()"><i class="fa-solid fa-check me-2"></i>Complete Sale</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
let posCart = [];
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();

  HMSAjax.liveSearch('#posPatSearch','posPatResults',APP_URL+'/ajax/search_patient.php',
    items => '<div class="border rounded">' + items.map(p =>
      '<div class="p-2 text-sm cursor-pointer border-bottom" onclick="document.getElementById(\'posPatId\').value='+p.id+';document.getElementById(\'posPatSearch\').value=\''+p.full_name.replace(/'/g,"\\'")+'\';document.getElementById(\'posPatResults\').innerHTML=\'\'">'+
      '<strong>'+p.full_name+'</strong> <small class=text-muted>'+p.patient_code+'</small></div>'
    ).join('')+'</div>'
  );
});

document.getElementById('posMedSearch')?.addEventListener('input', async function() {
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('posMedResults').innerHTML=''; return; }
  const res = await HMSAjax.get(APP_URL+'/api/patients.php?type=medicine&q='+encodeURIComponent(q));
  // Placeholder — in real impl searches medicines table
  document.getElementById('posMedResults').innerHTML = '';
});

function addToPos(id, name, price) {
  const ex = posCart.find(i=>i.id===id);
  if (ex) ex.qty++;
  else posCart.push({id,name,price,qty:1});
  renderPosCart();
}

function renderPosCart() {
  if (!posCart.length) {
    document.getElementById('posCart').innerHTML='<p class="text-muted text-center text-sm py-2">No items added</p>';
    posRecalc(); return;
  }
  document.getElementById('posCart').innerHTML = '<table class="table table-sm"><thead><tr><th>Medicine</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead><tbody>'+
    posCart.map((item,i)=>`<tr>
      <td class="fw-600">${item.name}</td>
      <td>₹${item.price.toFixed(2)}</td>
      <td><div class="d-flex align-items-center gap-1"><button class="btn btn-xs btn-outline-secondary" onclick="posQty(${i},-1)">−</button><span style="min-width:24px;text-align:center">${item.qty}</span><button class="btn btn-xs btn-outline-secondary" onclick="posQty(${i},1)">+</button></div></td>
      <td class="fw-700">₹${(item.price*item.qty).toFixed(2)}</td>
      <td><button class="btn btn-xs btn-outline-danger" onclick="posRemove(${i})"><i class="fa-solid fa-trash"></i></button></td>
    </tr>`).join('')+'</tbody></table>';
  posRecalc();
}

function posQty(i,d) { posCart[i].qty=Math.max(1,posCart[i].qty+d); renderPosCart(); }
function posRemove(i) { posCart.splice(i,1); renderPosCart(); }

function posRecalc() {
  const sub  = posCart.reduce((s,i)=>s+i.price*i.qty,0);
  const disc = +document.getElementById('posDisc').value||0;
  const net  = sub-disc;
  const cash = +document.getElementById('posCash').value||0;
  document.getElementById('posTotal').textContent   = '₹'+sub.toFixed(2);
  document.getElementById('posNet').textContent     = '₹'+net.toFixed(2);
  document.getElementById('posChange').value        = '₹'+Math.max(0,cash-net).toFixed(2);
}

async function completeSale() {
  if (!posCart.length) { HMS.toast('Add at least one medicine.','warning'); return; }
  HMS.toast('Sale completed! Receipt generated.','success');
  posCart=[];
  renderPosCart();
  bootstrap.Modal.getInstance(document.getElementById('posModal')).hide();
  setTimeout(()=>location.reload(),900);
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
