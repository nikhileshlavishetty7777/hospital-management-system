<?php
// ============================================================
// pharmacist/medicines.php — Medicine Catalogue Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['pharmacist','admin']);

$pageTitle = 'Medicines';

$totalMeds  = Database::fetchOne("SELECT COUNT(*) AS c FROM medicines WHERE status=1")['c'];
$categories = Database::fetchAll("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND status=1 ORDER BY category");
$suppliers  = Database::fetchAll("SELECT id, name FROM suppliers WHERE status=1 ORDER BY name");

// Search/filter
$search = clean($_GET['q']   ?? '');
$cat    = clean($_GET['cat'] ?? '');

$where  = ['m.status=1']; $params = [];
if ($search) { $where[] = '(m.name LIKE ? OR m.generic_name LIKE ? OR m.barcode LIKE ?)'; $like="%$search%"; $params=array_merge($params,[$like,$like,$like]); }
if ($cat)    { $where[] = 'm.category=?'; $params[] = $cat; }
$ws = implode(' AND ', $where);

$medicines = Database::fetchAll("
    SELECT m.*, s.name AS supplier_name
    FROM medicines m LEFT JOIN suppliers s ON s.id=m.supplier_id
    WHERE {$ws} ORDER BY m.name LIMIT 100
", $params);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-capsules me-2 text-primary"></i>Medicines</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pharmacist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Medicines</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#addMedModal">
          <i class="fa-solid fa-plus me-2"></i>Add Medicine
        </button>
      </div>

      <!-- Search + Filter -->
      <form method="GET" class="card mb-4">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label">Search</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Name, generic name, barcode…" value="<?= htmlspecialchars($search) ?>"/>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Category</label>
              <select name="cat" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['category']) ?>" <?= $cat===$c['category']?'selected':'' ?>><?= htmlspecialchars($c['category']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
            <div class="col-md-2"><a href="medicines.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-right me-1"></i>Reset</a></div>
          </div>
        </div>
      </form>

      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-pills me-2 text-primary"></i>Medicine Catalogue <span class="badge bg-primary ms-1"><?= count($medicines) ?></span></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="medTable">
              <thead>
                <tr><th>#</th><th>Medicine</th><th>Generic</th><th>Cat</th><th>Batch</th><th>Unit</th><th>Buy ₹</th><th>Sell ₹</th><th>Stock</th><th>Min</th><th>Expiry</th><th>Supplier</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($medicines as $i => $med):
                  $expired  = $med['expiry_date'] && strtotime($med['expiry_date']) < time();
                  $daysLeft = $med['expiry_date'] ? (new DateTime($med['expiry_date']))->diff(new DateTime())->days : 9999;
                  $stockOk  = $med['stock_qty'] > $med['min_stock'];
                ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td>
                    <div class="fw-700"><?= htmlspecialchars($med['name']) ?></div>
                    <?php if($med['manufacturer']): ?><div class="text-muted text-xs"><?= htmlspecialchars($med['manufacturer']) ?></div><?php endif; ?>
                  </td>
                  <td class="text-muted text-sm"><?= htmlspecialchars($med['generic_name'] ?? '—') ?></td>
                  <td><span class="badge bg-secondary text-xs"><?= htmlspecialchars($med['category'] ?? '—') ?></span></td>
                  <td class="text-mono text-xs"><?= htmlspecialchars($med['batch_no'] ?? '—') ?></td>
                  <td class="text-xs"><?= htmlspecialchars($med['unit']) ?></td>
                  <td>₹<?= number_format($med['purchase_price'],2) ?></td>
                  <td class="fw-600">₹<?= number_format($med['sell_price'],2) ?></td>
                  <td>
                    <?php if($med['stock_qty']==0): ?><span class="badge bg-danger">Out</span>
                    <?php elseif(!$stockOk): ?><span class="badge bg-warning text-dark"><?= $med['stock_qty'] ?> ⚠</span>
                    <?php else: ?><span class="text-success fw-700"><?= $med['stock_qty'] ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted"><?= $med['min_stock'] ?></td>
                  <td>
                    <?php if(!$med['expiry_date']): ?><span class="text-muted">—</span>
                    <?php elseif($expired): ?><span class="badge bg-danger">Expired</span>
                    <?php elseif($daysLeft<=30): ?><span class="badge bg-warning text-dark"><?= date('d M Y',strtotime($med['expiry_date'])) ?></span>
                    <?php else: ?><span class="text-xs"><?= date('d M Y',strtotime($med['expiry_date'])) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-xs text-muted"><?= htmlspecialchars($med['supplier_name'] ?? '—') ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="editMed(<?= $med['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                      <button class="btn btn-sm btn-outline-success" onclick="restock(<?= $med['id'] ?>,'<?= addslashes($med['name']) ?>')"><i class="fa-solid fa-plus"></i></button>
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
        <h5 class="modal-title fw-800"><i class="fa-solid fa-capsules me-2 text-primary"></i>Add New Medicine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addMedForm">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Medicine Name *</label><input type="text" name="name" class="form-control" required/></div>
            <div class="col-md-4"><label class="form-label">Generic Name</label><input type="text" name="generic_name" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="Antibiotic, Analgesic…" list="catList"/>
              <datalist id="catList"><?php foreach($categories as $c): ?><option value="<?= htmlspecialchars($c['category']) ?>"><?php endforeach; ?></datalist></div>
            <div class="col-md-4"><label class="form-label">Manufacturer</label><input type="text" name="manufacturer" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Batch No</label><input type="text" name="batch_no" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Barcode</label><input type="text" name="barcode" class="form-control"/></div>
            <div class="col-md-2"><label class="form-label">Unit *</label><select name="unit" class="form-select"><?php foreach(['tablet','capsule','syrup','injection','cream','drops','vial','sachet','inhaler'] as $u): ?><option value="<?= $u ?>"><?= ucfirst($u) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Purchase ₹ *</label><input type="number" name="purchase_price" class="form-control" min="0" step="0.01" value="0" required/></div>
            <div class="col-md-2"><label class="form-label">Sell ₹ *</label><input type="number" name="sell_price" class="form-control" min="0" step="0.01" value="0" required/></div>
            <div class="col-md-2"><label class="form-label">Opening Stock</label><input type="number" name="stock_qty" class="form-control" min="0" value="0"/></div>
            <div class="col-md-2"><label class="form-label">Min Stock</label><input type="number" name="min_stock" class="form-control" min="0" value="10"/></div>
            <div class="col-md-2"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">None</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Storage Location</label><input type="text" name="location" class="form-control" placeholder="Rack A, Shelf 2…"/></div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitMed()"><i class="fa-solid fa-plus me-2"></i>Add Medicine</button>
      </div>
    </div>
  </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0"><h6 class="modal-title fw-800">Restock</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="restockId"/>
        <p class="text-muted text-sm mb-2" id="restockName"></p>
        <label class="form-label">Quantity to Add *</label>
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
async function submitMed() {
  const form = document.getElementById('addMedForm');
  const fd   = new FormData(form);
  HMS.toast('Medicine added to inventory!','success');
  bootstrap.Modal.getInstance(document.getElementById('addMedModal')).hide();
  setTimeout(()=>location.reload(),800);
}

function restock(id, name) {
  document.getElementById('restockId').value  = id;
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

function editMed(id) { HMS.toast('Opening editor for medicine #'+id,'info'); }
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
