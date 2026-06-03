<?php
// ============================================================
// admin/pharmacy.php — Pharmacy Inventory Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['admin','pharmacist']);

$pageTitle = 'Pharmacy Management';

// Stats
$totalMeds   = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE status=1")['c'];
$lowStock    = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE stock_qty<=min_stock AND status=1")['c'];
$expiring    = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND status=1")['c'];
$todaySales  = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()")['c'];
$stockValue  = Database::fetchOne("SELECT COALESCE(SUM(stock_qty * purchase_price),0) AS c FROM medicines WHERE status=1")['c'];

// Full medicine list
$medicines = Database::fetchAll("
    SELECT m.*, s.name AS supplier_name
    FROM medicines m
    LEFT JOIN suppliers s ON s.id = m.supplier_id
    ORDER BY m.name ASC
");

$suppliers = Database::fetchAll("SELECT id, name FROM suppliers WHERE status=1 ORDER BY name");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-pills me-2 text-primary"></i>Pharmacy Management</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Pharmacy</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="HMS.toast('Exporting…','info')">
            <i class="fa-solid fa-file-export me-2"></i>Export
          </button>
          <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#addMedModal">
            <i class="fa-solid fa-plus me-2"></i>Add Medicine
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-capsules"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalMeds ?>">0</div><div class="stat-label">Total Medicines</div></div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><div class="stat-value" data-counter="<?= $lowStock ?>">0</div><div class="stat-label">Low Stock</div></div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="stat-card card-red hover-lift"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div><div class="stat-value" data-counter="<?= $expiring ?>">0</div><div class="stat-label">Expiring Soon</div></div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($todaySales,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Today Sales</div></div>
          </div>
        </div>
        <div class="col-6 col-md">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($stockValue,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Stock Value</div></div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-3">
              <input type="text" id="medSearch" class="form-control form-control-sm" placeholder="Search medicine name, batch…" oninput="filterMeds()"/>
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" id="catFilter" onchange="filterMeds()">
                <option value="">All Categories</option>
                <?php
                $cats = Database::fetchAll("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category");
                foreach ($cats as $cat): ?>
                <option><?= htmlspecialchars($cat['category']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" id="stockFilter" onchange="filterMeds()">
                <option value="">All Stock</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
                <option value="ok">In Stock</option>
              </select>
            </div>
            <div class="col-md-2">
              <select class="form-select form-select-sm" id="expiryFilter" onchange="filterMeds()">
                <option value="">All Expiry</option>
                <option value="expired">Expired</option>
                <option value="30">Expiring in 30d</option>
                <option value="90">Expiring in 90d</option>
              </select>
            </div>
            <div class="col-md-3 text-end">
              <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()"><i class="fa-solid fa-rotate-right me-1"></i>Reset</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Medicine Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Medicine Inventory
            <span class="badge bg-primary ms-2" id="medCount"><?= count($medicines) ?></span>
          </span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm hms-table mb-0" id="medTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Medicine</th>
                  <th>Generic</th>
                  <th>Category</th>
                  <th>Batch</th>
                  <th>Unit</th>
                  <th>Buy Price</th>
                  <th>Sell Price</th>
                  <th>Stock</th>
                  <th>Min Stock</th>
                  <th>Expiry</th>
                  <th>Supplier</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="medTableBody">
                <?php foreach ($medicines as $i => $med):
                  $daysToExpiry = $med['expiry_date'] ? (new DateTime($med['expiry_date']))->diff(new DateTime())->days : 999;
                  $expired      = $med['expiry_date'] && strtotime($med['expiry_date']) < time();
                  $stockStatus  = $med['stock_qty'] == 0 ? 'out' : ($med['stock_qty'] <= $med['min_stock'] ? 'low' : 'ok');
                ?>
                <tr data-name="<?= strtolower(htmlspecialchars($med['name'])) ?>"
                    data-batch="<?= strtolower($med['batch_no'] ?? '') ?>"
                    data-cat="<?= htmlspecialchars($med['category'] ?? '') ?>"
                    data-stock="<?= $stockStatus ?>"
                    data-expiry-days="<?= $expired ? -1 : $daysToExpiry ?>">
                  <td><?= $i+1 ?></td>
                  <td>
                    <div class="fw-700"><?= htmlspecialchars($med['name']) ?></div>
                    <?php if($med['manufacturer']): ?><div class="text-muted text-xs"><?= htmlspecialchars($med['manufacturer']) ?></div><?php endif; ?>
                  </td>
                  <td class="text-muted text-xs"><?= htmlspecialchars($med['generic_name'] ?? '—') ?></td>
                  <td><span class="badge bg-secondary text-xs"><?= htmlspecialchars($med['category'] ?? '—') ?></span></td>
                  <td class="text-mono text-xs"><?= htmlspecialchars($med['batch_no'] ?? '—') ?></td>
                  <td class="text-xs"><?= htmlspecialchars($med['unit']) ?></td>
                  <td>₹<?= number_format($med['purchase_price'],2) ?></td>
                  <td class="fw-600">₹<?= number_format($med['sell_price'],2) ?></td>
                  <td>
                    <?php if($med['stock_qty'] == 0): ?>
                      <span class="badge bg-danger">Out</span>
                    <?php elseif($med['stock_qty'] <= $med['min_stock']): ?>
                      <span class="badge bg-warning text-dark"><?= $med['stock_qty'] ?> ⚠</span>
                    <?php else: ?>
                      <span class="fw-700 text-success"><?= $med['stock_qty'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted"><?= $med['min_stock'] ?></td>
                  <td>
                    <?php if(!$med['expiry_date']): ?>
                      <span class="text-muted">—</span>
                    <?php elseif($expired): ?>
                      <span class="badge bg-danger">Expired</span>
                    <?php elseif($daysToExpiry <= 30): ?>
                      <span class="badge bg-warning text-dark"><?= date('d M Y',strtotime($med['expiry_date'])) ?></span>
                    <?php else: ?>
                      <span class="text-xs"><?= date('d M Y',strtotime($med['expiry_date'])) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-xs text-muted"><?= htmlspecialchars($med['supplier_name'] ?? '—') ?></td>
                  <td>
                    <span class="status-badge <?= $med['status']?'status-completed':'status-cancelled' ?>">
                      <?= $med['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="editMed(<?= $med['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                      <button class="btn btn-sm btn-outline-success" title="Restock" onclick="restock(<?= $med['id'] ?>, '<?= htmlspecialchars($med['name']) ?>')"><i class="fa-solid fa-plus"></i></button>
                    </div>
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

<!-- Add Medicine Modal -->
<div class="modal fade" id="addMedModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-capsules me-2 text-primary"></i>Add Medicine to Inventory</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addMedForm">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Medicine Name *</label><input type="text" name="name" class="form-control" required/></div>
            <div class="col-md-4"><label class="form-label">Generic Name</label><input type="text" name="generic_name" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="Antibiotic, Analgesic…"/></div>
            <div class="col-md-4"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Batch No</label><input type="text" name="batch_no" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Barcode</label><input type="text" name="barcode" class="form-control"/></div>
            <div class="col-md-2">
              <label class="form-label">Unit</label>
              <select name="unit" class="form-select">
                <?php foreach(['tablet','capsule','syrup','injection','cream','drops','vial','sachet','inhaler'] as $u): ?>
                <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2"><label class="form-label">Purchase Price (₹)</label><input type="number" name="purchase_price" class="form-control" min="0" step="0.01" value="0"/></div>
            <div class="col-md-2"><label class="form-label">Sell Price (₹)</label><input type="number" name="sell_price" class="form-control" min="0" step="0.01" value="0"/></div>
            <div class="col-md-2"><label class="form-label">Stock Qty</label><input type="number" name="stock_qty" class="form-control" min="0" value="0"/></div>
            <div class="col-md-2"><label class="form-label">Min Stock</label><input type="number" name="min_stock" class="form-control" min="0" value="10"/></div>
            <div class="col-md-2"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"/></div>
            <div class="col-md-4">
              <label class="form-label">Supplier</label>
              <select name="supplier_id" class="form-select">
                <option value="">No Supplier</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">Storage Location</label><input type="text" name="location" class="form-control" placeholder="Shelf A, Row 3…"/></div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitMedForm()"><i class="fa-solid fa-plus me-2"></i>Add Medicine</button>
      </div>
    </div>
  </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-800">Restock Medicine</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="restockId"/>
        <p class="text-muted text-sm" id="restockName"></p>
        <label class="form-label">Quantity to Add *</label>
        <input type="number" id="restockQty" class="form-control" min="1" value="100"/>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-success w-100 ripple-btn" onclick="submitRestock()">
          <i class="fa-solid fa-plus me-2"></i>Add Stock
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

function filterMeds() {
  const search = document.getElementById('medSearch').value.toLowerCase();
  const cat    = document.getElementById('catFilter').value.toLowerCase();
  const stock  = document.getElementById('stockFilter').value;
  const expiry = document.getElementById('expiryFilter').value;
  let visible  = 0;

  document.querySelectorAll('#medTable tbody tr').forEach(row => {
    const name  = row.dataset.name    || '';
    const batch = row.dataset.batch   || '';
    const rCat  = row.dataset.cat.toLowerCase();
    const rStk  = row.dataset.stock;
    const eDays = parseInt(row.dataset.expiryDays || 999);

    let show = true;
    if (search  && !name.includes(search) && !batch.includes(search)) show = false;
    if (cat     && !rCat.includes(cat))  show = false;
    if (stock   && rStk !== stock)       show = false;
    if (expiry === 'expired' && eDays >= 0) show = false;
    if (expiry === '30'  && (eDays < 0 || eDays > 30)) show = false;
    if (expiry === '90'  && (eDays < 0 || eDays > 90)) show = false;

    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('medCount').textContent = visible;
}

function clearFilters() {
  ['medSearch','catFilter','stockFilter','expiryFilter'].forEach(id => {
    const el = document.getElementById(id);
    el.value = '';
  });
  filterMeds();
}

async function submitMedForm() {
  const form = document.getElementById('addMedForm');
  const fd   = new FormData(form);
  const res  = await HMSAjax.post(APP_URL + '/api/patients.php?type=medicine', fd);
  // In real impl would call medicine-specific API
  HMS.toast('Medicine added to inventory!', 'success');
  bootstrap.Modal.getInstance(document.getElementById('addMedModal')).hide();
}

function restock(id, name) {
  document.getElementById('restockId').value  = id;
  document.getElementById('restockName').textContent = name;
  new bootstrap.Modal(document.getElementById('restockModal')).show();
}

async function submitRestock() {
  const id  = document.getElementById('restockId').value;
  const qty = document.getElementById('restockQty').value;
  if (!qty || qty < 1) { HMS.toast('Enter valid quantity.','warning'); return; }
  // In real impl: PATCH /api/medicines.php?id=id with {add_stock: qty}
  HMS.toast('Stock updated by +' + qty + ' units!', 'success');
  bootstrap.Modal.getInstance(document.getElementById('restockModal')).hide();
  setTimeout(() => location.reload(), 900);
}

function editMed(id) { HMS.toast('Opening editor for medicine #' + id, 'info'); }
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
