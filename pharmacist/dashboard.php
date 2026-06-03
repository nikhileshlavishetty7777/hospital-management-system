<?php
// ============================================================
// pharmacist/dashboard.php — Pharmacist Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['pharmacist','admin']);

$pageTitle    = 'Pharmacy Dashboard';
$extraScripts = ['assets/js/charts.js'];

// Stats
$totalMeds     = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE status=1")['c'];
$lowStock      = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE stock_qty <= min_stock AND status=1")['c'];
$expiringSoon  = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status=1")['c'];
$todaySales    = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()")['c'];
$monthSales    = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM pharmacy_sales WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];
$totalOrders   = Database::fetchOne("SELECT COUNT(*) AS c FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()")['c'];

// Low stock medicines
$lowStockMeds = Database::fetchAll("
    SELECT m.*, s.name AS supplier_name
    FROM medicines m
    LEFT JOIN suppliers s ON s.id = m.supplier_id
    WHERE m.stock_qty <= m.min_stock AND m.status=1
    ORDER BY m.stock_qty ASC LIMIT 10
");

// Expiring medicines
$expiringMeds = Database::fetchAll("
    SELECT * FROM medicines
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND status=1
    ORDER BY expiry_date ASC LIMIT 10
");

// Recent sales
$recentSales = Database::fetchAll("
    SELECT ps.*, u.full_name AS patient_name, p.patient_code
    FROM pharmacy_sales ps
    LEFT JOIN patients p ON p.id = ps.patient_id
    LEFT JOIN users    u ON u.id = p.user_id
    ORDER BY ps.created_at DESC LIMIT 8
");

// Sales last 7 days for chart
$salesChart = Database::fetchAll("
    SELECT DATE_FORMAT(d.d,'%d %b') AS label, COALESCE(SUM(ps.paid),0) AS val
    FROM (
        SELECT CURDATE()-INTERVAL 6 DAY AS d UNION SELECT CURDATE()-INTERVAL 5 DAY
        UNION SELECT CURDATE()-INTERVAL 4 DAY UNION SELECT CURDATE()-INTERVAL 3 DAY
        UNION SELECT CURDATE()-INTERVAL 2 DAY UNION SELECT CURDATE()-INTERVAL 1 DAY
        UNION SELECT CURDATE()
    ) d
    LEFT JOIN pharmacy_sales ps ON DATE(ps.created_at) = d.d
    GROUP BY d.d ORDER BY d.d
");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Welcome banner -->
      <div class="welcome-banner" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)">
        <div>
          <div class="welcome-title">Pharmacy Dashboard 💊</div>
          <div class="welcome-subtitle">Inventory control, sales tracking & medicine management</div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <?php if($lowStock > 0): ?>
            <span class="badge bg-warning text-dark fw-600"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= $lowStock ?> low stock alerts</span>
            <?php endif; ?>
            <?php if($expiringSoon > 0): ?>
            <span class="badge bg-danger fw-600"><i class="fa-solid fa-clock me-1"></i><?= $expiringSoon ?> expiring soon</span>
            <?php endif; ?>
            <span class="badge bg-white text-success fw-600"><i class="fa-solid fa-cart-shopping me-1"></i><?= $totalOrders ?> orders today</span>
          </div>
        </div>
        <i class="fa-solid fa-pills welcome-icon"></i>
      </div>

      <!-- Stats -->
      <div class="dashboard-grid mb-4">
        <div class="stat-card card-indigo hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-capsules"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $totalMeds ?>">0</div>
            <div class="stat-label">Total Medicines</div>
          </div>
        </div>
        <div class="stat-card card-orange hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $lowStock ?>">0</div>
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-change down"><i class="fa-solid fa-arrow-down"></i>Needs reorder</div>
          </div>
        </div>
        <div class="stat-card card-green hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= number_format($todaySales,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label">Today's Sales</div>
          </div>
        </div>
        <div class="stat-card card-purple hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= number_format($monthSales,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label">Monthly Revenue</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Sales Chart -->
        <div class="col-xl-8">
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">Sales Analytics — Last 7 Days</div>
            </div>
            <div style="height:220px"><canvas id="salesChart"></canvas></div>
          </div>
        </div>

        <!-- Quick Sale -->
        <div class="col-xl-4">
          <div class="card h-100">
            <div class="card-header fw-700">
              <i class="fa-solid fa-cart-plus me-2 text-success"></i>Quick Sale
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Search Medicine</label>
                <input type="text" id="medSearch" class="form-control" placeholder="Type medicine name…"/>
                <div id="medResults" class="mt-1"></div>
              </div>
              <div id="saleItems" class="mb-3"></div>
              <div class="mb-2">
                <label class="form-label">Patient (optional)</label>
                <input type="text" id="salePatient" class="form-control form-control-sm" placeholder="Search patient…"/>
              </div>
              <div class="mb-3">
                <label class="form-label">Payment Method</label>
                <select id="payMethod" class="form-select form-select-sm">
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="upi">UPI</option>
                </select>
              </div>
              <div class="d-flex justify-content-between fw-700 mb-3">
                <span>Total:</span>
                <span id="saleTotal" class="text-success">₹0.00</span>
              </div>
              <button class="btn btn-success w-100 ripple-btn" onclick="completeSale()">
                <i class="fa-solid fa-check me-2"></i>Complete Sale
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Low Stock Alerts -->
        <div class="col-xl-6">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Low Stock Alerts</span>
              <a href="<?= APP_URL ?>/pharmacist/inventory.php" class="btn btn-sm btn-outline-warning">Manage</a>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Medicine</th><th>Current Stock</th><th>Min Stock</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($lowStockMeds as $med): ?>
                  <tr>
                    <td>
                      <div class="fw-600 text-sm"><?= htmlspecialchars($med['name']) ?></div>
                      <div class="text-muted text-xs"><?= htmlspecialchars($med['category'] ?? '—') ?></div>
                    </td>
                    <td class="fw-700 text-danger"><?= $med['stock_qty'] ?></td>
                    <td><?= $med['min_stock'] ?></td>
                    <td>
                      <?php if($med['stock_qty'] == 0): ?>
                        <span class="badge bg-danger">Out of Stock</span>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Low Stock</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($lowStockMeds)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">All stock levels are healthy ✅</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Expiry Alerts -->
        <div class="col-xl-6">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-clock me-2 text-danger"></i>Expiring Soon</span>
              <span class="badge bg-danger"><?= $expiringSoon ?> items</span>
            </div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Days Left</th></tr></thead>
                <tbody>
                  <?php foreach ($expiringMeds as $med):
                    $daysLeft = (new DateTime($med['expiry_date']))->diff(new DateTime())->days;
                    $cls      = $daysLeft <= 30 ? 'danger' : ($daysLeft <= 60 ? 'warning' : 'info');
                  ?>
                  <tr>
                    <td><div class="fw-600 text-sm"><?= htmlspecialchars($med['name']) ?></div></td>
                    <td class="text-mono text-xs"><?= htmlspecialchars($med['batch_no'] ?? '—') ?></td>
                    <td><?= date('d M Y', strtotime($med['expiry_date'])) ?></td>
                    <td><span class="badge bg-<?= $cls ?>"><?= $daysLeft ?> days</span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($expiringMeds)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No medicines expiring within 90 days ✅</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Sales -->
      <div class="card">
        <div class="card-header"><i class="fa-solid fa-receipt me-2 text-primary"></i>Recent Sales</div>
        <div class="card-body p-0">
          <table class="table hms-table mb-0">
            <thead><tr><th>Sale No</th><th>Patient</th><th>Total</th><th>Paid</th><th>Method</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach ($recentSales as $s): ?>
              <tr>
                <td class="text-mono fw-600 text-primary"><?= htmlspecialchars($s['sale_no']) ?></td>
                <td><?= $s['patient_name'] ? htmlspecialchars($s['patient_name']) : '<span class="text-muted">Walk-in</span>' ?></td>
                <td class="fw-600">₹<?= number_format($s['total'],2) ?></td>
                <td class="text-success fw-600">₹<?= number_format($s['paid'],2) ?></td>
                <td><span class="badge bg-secondary"><?= strtoupper($s['payment_method']) ?></span></td>
                <td class="text-muted text-xs"><?= date('h:i A', strtotime($s['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$chartLabels = json_encode(array_column($salesChart,'label'));
$chartValues = json_encode(array_column($salesChart,'val'));
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSCharts.pharmacySalesChart('salesChart', {$chartLabels}, {$chartValues});
});

let saleCart = [];

document.getElementById('medSearch').addEventListener('input', function() {
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('medResults').innerHTML = ''; return; }
  HMSAjax.get(APP_URL + '/ajax/search_patient.php?q=' + encodeURIComponent(q) + '&type=medicine')
    .then(res => {
      // placeholder: real impl would search medicines table
      document.getElementById('medResults').innerHTML = '';
    });
});

function addToCart(id, name, price) {
  const existing = saleCart.find(i => i.id === id);
  if (existing) { existing.qty++; } else { saleCart.push({ id, name, price, qty: 1 }); }
  renderCart();
}

function renderCart() {
  const container = document.getElementById('saleItems');
  let total = 0;
  if (!saleCart.length) { container.innerHTML = '<p class="text-muted text-sm text-center">No items added</p>'; document.getElementById('saleTotal').textContent = '₹0.00'; return; }
  container.innerHTML = saleCart.map((item, i) => {
    const sub = item.price * item.qty;
    total += sub;
    return '<div class="d-flex justify-content-between align-items-center mb-1"><span class="text-sm">' + item.name + '</span><div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-secondary" onclick="changeQty(' + i + ',-1)">−</button><span>' + item.qty + '</span><button class="btn btn-sm btn-outline-secondary" onclick="changeQty(' + i + ',1)">+</button><span class="fw-600">₹' + sub.toFixed(2) + '</span></div></div>';
  }).join('');
  document.getElementById('saleTotal').textContent = '₹' + total.toFixed(2);
}

function changeQty(idx, delta) {
  saleCart[idx].qty = Math.max(1, saleCart[idx].qty + delta);
  renderCart();
}

async function completeSale() {
  if (!saleCart.length) { HMS.toast('Add at least one medicine.','warning'); return; }
  HMS.toast('Sale processing…', 'info');
  saleCart = [];
  renderCart();
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
