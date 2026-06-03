<?php
// ============================================================
// pharmacist/inventory.php — Inventory Overview
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['pharmacist','admin']);

$pageTitle = 'Inventory';

$totalMeds   = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE status=1")['c'];
$lowStock    = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE stock_qty<=min_stock AND status=1")['c'];
$outOfStock  = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE stock_qty=0 AND status=1")['c'];
$expiring30  = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status=1")['c'];
$stockValue  = Database::fetchOne("SELECT COALESCE(SUM(stock_qty*purchase_price),0) AS c FROM medicines WHERE status=1")['c'];
$retailValue = Database::fetchOne("SELECT COALESCE(SUM(stock_qty*sell_price),0) AS c FROM medicines WHERE status=1")['c'];

// Low stock list
$lowStockList = Database::fetchAll("
    SELECT m.id, m.name, m.category, m.stock_qty, m.min_stock, m.unit,
           m.sell_price, m.expiry_date, s.name AS supplier_name, s.phone AS supplier_phone
    FROM medicines m LEFT JOIN suppliers s ON s.id=m.supplier_id
    WHERE m.stock_qty<=m.min_stock AND m.status=1
    ORDER BY m.stock_qty ASC LIMIT 20
");

// Expiring medicines
$expiringList = Database::fetchAll("
    SELECT m.id, m.name, m.batch_no, m.stock_qty, m.expiry_date, m.category
    FROM medicines m
    WHERE m.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 90 DAY) AND m.status=1
    ORDER BY m.expiry_date ASC LIMIT 20
");

// Category breakdown
$catBreakdown = Database::fetchAll("
    SELECT category, COUNT(*) AS items, SUM(stock_qty) AS total_units,
           SUM(stock_qty*purchase_price) AS cost_value
    FROM medicines WHERE status=1 AND category IS NOT NULL
    GROUP BY category ORDER BY cost_value DESC
");

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Inventory</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pharmacist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Inventory</li>
          </ol></nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="HMS.toast('Exporting…','info')"><i class="fa-solid fa-file-export me-2"></i>Export</button>
          <a href="<?= APP_URL ?>/pharmacist/medicines.php" class="btn btn-primary ripple-btn"><i class="fa-solid fa-plus me-2"></i>Add Medicine</a>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
          <div class="stat-card card-blue hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-pills"></i></div>
            <div class="stat-value text-center" style="font-size:18px" data-counter="<?= $totalMeds ?>">0</div>
            <div class="stat-label text-center">Total Medicines</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-orange hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-value text-center" style="font-size:18px" data-counter="<?= $lowStock ?>">0</div>
            <div class="stat-label text-center">Low Stock</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-red hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-ban"></i></div>
            <div class="stat-value text-center" style="font-size:18px" data-counter="<?= $outOfStock ?>">0</div>
            <div class="stat-label text-center">Out of Stock</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-pink hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-value text-center" style="font-size:18px" data-counter="<?= $expiring30 ?>">0</div>
            <div class="stat-label text-center">Expiring 30d</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-green hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="stat-value text-center" style="font-size:15px" data-counter="<?= number_format($stockValue,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label text-center">Cost Value</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-purple hover-lift" style="flex-direction:column;gap:6px;padding:14px 12px">
            <div class="stat-icon mx-auto" style="width:38px;height:38px;font-size:15px"><i class="fa-solid fa-tag"></i></div>
            <div class="stat-value text-center" style="font-size:15px" data-counter="<?= number_format($retailValue,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label text-center">Retail Value</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Low Stock Alerts -->
        <div class="col-xl-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Low Stock Alerts</span>
              <span class="badge bg-warning text-dark"><?= $lowStock ?></span>
            </div>
            <div class="card-body p-0" style="max-height:360px;overflow-y:auto">
              <?php if(empty($lowStockList)): ?>
              <div class="text-center py-4 text-muted"><i class="fa-solid fa-check-circle fa-2x text-success mb-2 d-block"></i>All stocks healthy!</div>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <thead><tr><th>Medicine</th><th>Stock</th><th>Min</th><th>Supplier</th><th>Action</th></tr></thead>
                <tbody>
                  <?php foreach ($lowStockList as $m): ?>
                  <tr>
                    <td><div class="fw-600 text-sm"><?= htmlspecialchars($m['name']) ?></div><div class="text-muted text-xs"><?= htmlspecialchars($m['category']??'') ?></div></td>
                    <td><?= $m['stock_qty']==0?'<span class="badge bg-danger">Out</span>':'<span class="badge bg-warning text-dark">'.$m['stock_qty'].'</span>' ?></td>
                    <td class="text-muted"><?= $m['min_stock'] ?></td>
                    <td class="text-xs text-muted"><?= htmlspecialchars($m['supplier_name']??'—') ?></td>
                    <td><button class="btn btn-xs btn-outline-success" onclick="restock(<?= $m['id'] ?>,'<?= addslashes($m['name']) ?>')"><i class="fa-solid fa-plus"></i></button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Expiry Alerts -->
        <div class="col-xl-6">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-clock me-2 text-danger"></i>Expiry Alerts</span>
              <span class="badge bg-danger"><?= $expiring30 ?> within 30 days</span>
            </div>
            <div class="card-body p-0" style="max-height:360px;overflow-y:auto">
              <?php if(empty($expiringList)): ?>
              <div class="text-center py-4 text-muted"><i class="fa-solid fa-check-circle fa-2x text-success mb-2 d-block"></i>No expiring medicines!</div>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Expiry</th><th>Days</th></tr></thead>
                <tbody>
                  <?php foreach ($expiringList as $m):
                    $days = (new DateTime($m['expiry_date']))->diff(new DateTime())->days;
                    $cls  = $days <= 30 ? 'danger' : ($days <= 60 ? 'warning' : 'info');
                  ?>
                  <tr>
                    <td><div class="fw-600 text-sm"><?= htmlspecialchars($m['name']) ?></div></td>
                    <td class="text-mono text-xs"><?= htmlspecialchars($m['batch_no']??'—') ?></td>
                    <td><?= $m['stock_qty'] ?></td>
                    <td class="text-xs"><?= date('d M Y',strtotime($m['expiry_date'])) ?></td>
                    <td><span class="badge bg-<?= $cls ?>"><?= $days ?>d</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="card">
        <div class="card-header fw-700"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Category Breakdown</div>
        <div class="card-body p-0">
          <table class="table hms-table mb-0">
            <thead><tr><th>Category</th><th>Items</th><th>Total Units</th><th>Cost Value</th><th>% of Stock</th></tr></thead>
            <tbody>
              <?php foreach ($catBreakdown as $cat):
                $pct = $stockValue > 0 ? round(($cat['cost_value'] / $stockValue) * 100, 1) : 0;
              ?>
              <tr>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($cat['category']) ?></span></td>
                <td class="fw-600"><?= $cat['items'] ?></td>
                <td><?= number_format($cat['total_units']) ?></td>
                <td class="fw-700">₹<?= number_format($cat['cost_value'],2) ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-1" style="height:6px">
                      <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="text-xs"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0"><h6 class="modal-title fw-800">Restock Medicine</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="restockId"/>
        <p class="text-muted text-sm mb-2" id="restockName"></p>
        <label class="form-label">Quantity to Add</label>
        <input type="number" id="restockQty" class="form-control" min="1" value="100"/>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-success w-100 ripple-btn" onclick="submitRestock()"><i class="fa-solid fa-plus me-2"></i>Add Stock</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

function restock(id, name) {
  document.getElementById('restockId').value = id;
  document.getElementById('restockName').textContent = name;
  new bootstrap.Modal(document.getElementById('restockModal')).show();
}

async function submitRestock() {
  const qty = document.getElementById('restockQty').value;
  if (!qty||qty<1) { HMS.toast('Enter valid quantity.','warning'); return; }
  HMS.toast('Stock updated by +'+qty+' units!','success');
  bootstrap.Modal.getInstance(document.getElementById('restockModal')).hide();
  setTimeout(()=>location.reload(),800);
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
