<?php
// ============================================================
// admin/laboratory.php — Laboratory Management (Admin View)
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle = 'Laboratory Management';
$extraScripts = ['assets/js/charts.js'];

// Stats
$totalTests    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_tests WHERE status=1")['c'];
$totalOrders   = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders")['c'];
$pendingOrders = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status IN ('ordered','sample_collected','processing')")['c'];
$completedToday= Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND DATE(report_date)=CURDATE()")['c'];
$monthRevenue  = Database::fetchOne("
    SELECT COALESCE(SUM(lt.price),0) AS c
    FROM lab_orders lo JOIN lab_tests lt ON lt.id=lo.test_id
    WHERE lo.status='completed' AND MONTH(lo.created_at)=MONTH(NOW())")['c'];

// Test catalogue
$tests = Database::fetchAll("
    SELECT lt.*, COUNT(lo.id) AS total_orders
    FROM lab_tests lt
    LEFT JOIN lab_orders lo ON lo.test_id=lt.id
    WHERE lt.status=1
    GROUP BY lt.id
    ORDER BY lt.category, lt.name
");

// Recent orders
$recentOrders = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.status, lo.created_at, lo.payment_status,
           lt.name AS test_name, lt.category,
           u.full_name AS patient_name, p.patient_code
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id=lo.test_id
    JOIN patients  p  ON p.id=lo.patient_id  JOIN users u ON u.id=p.user_id
    ORDER BY lo.created_at DESC LIMIT 20
");

// Category stats
$catStats = Database::fetchAll("
    SELECT lt.category, COUNT(lo.id) AS orders
    FROM lab_orders lo JOIN lab_tests lt ON lt.id=lo.test_id
    WHERE MONTH(lo.created_at)=MONTH(NOW())
    GROUP BY lt.category ORDER BY orders DESC
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
          <h1><i class="fa-solid fa-flask me-2 text-primary"></i>Laboratory Management</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Laboratory</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#addTestModal">
          <i class="fa-solid fa-plus me-2"></i>Add Test
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-vials"></i></div><div><div class="stat-value" data-counter="<?= $totalTests ?>">0</div><div class="stat-label">Tests Available</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div><div><div class="stat-value" data-counter="<?= $totalOrders ?>">0</div><div class="stat-label">Total Orders</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="stat-value" data-counter="<?= $pendingOrders ?>">0</div><div class="stat-label">Pending</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $completedToday ?>">0</div><div class="stat-label">Done Today</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-teal hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="stat-value" data-counter="<?= number_format($monthRevenue,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Monthly Revenue</div></div></div></div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Category distribution chart -->
        <div class="col-xl-5">
          <div class="chart-card h-100">
            <div class="chart-card-header">
              <div class="chart-card-title">Test Categories This Month</div>
            </div>
            <div style="height:220px"><canvas id="labCatChart"></canvas></div>
          </div>
        </div>
        <!-- Recent Orders -->
        <div class="col-xl-7">
          <div class="card h-100">
            <div class="card-header fw-700"><i class="fa-solid fa-clock me-2 text-primary"></i>Recent Lab Orders</div>
            <div class="card-body p-0" style="max-height:280px;overflow-y:auto">
              <table class="table table-sm mb-0">
                <thead><tr><th>Order No</th><th>Patient</th><th>Test</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                  <?php foreach ($recentOrders as $o): ?>
                  <tr>
                    <td class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($o['order_no']) ?></td>
                    <td><div class="fw-600 text-sm"><?= htmlspecialchars($o['patient_name']) ?></div><div class="text-muted text-xs"><?= $o['patient_code'] ?></div></td>
                    <td class="text-sm"><?= htmlspecialchars($o['test_name']) ?></td>
                    <td><?php
                      $map=['ordered'=>'status-booked','sample_collected'=>'status-confirmed','processing'=>'status-in_progress','completed'=>'status-completed','cancelled'=>'status-cancelled'];
                      $cls=$map[$o['status']]??'status-booked';
                    ?><span class="status-badge <?= $cls ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
                    <td class="text-xs text-muted"><?= date('d M',strtotime($o['created_at'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Test Catalogue -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-list me-2 text-primary"></i>Test Catalogue <span class="badge bg-primary ms-1"><?= count($tests) ?></span></span>
          <input type="text" id="testSearch" class="form-control form-control-sm" style="width:200px" placeholder="Search tests…" oninput="filterTests(this.value)"/>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="testTable">
              <thead><tr><th>#</th><th>Test Name</th><th>Code</th><th>Category</th><th>Price</th><th>TAT (hrs)</th><th>Total Orders</th><th>Description</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($tests as $i => $t): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td class="fw-600"><?= htmlspecialchars($t['name']) ?></td>
                  <td><span class="badge bg-secondary text-mono"><?= htmlspecialchars($t['code']) ?></span></td>
                  <td><?= htmlspecialchars($t['category'] ?? '—') ?></td>
                  <td class="fw-700 text-primary">₹<?= number_format($t['price'],0) ?></td>
                  <td><?= $t['turnaround'] ?></td>
                  <td><span class="badge bg-primary"><?= $t['total_orders'] ?></span></td>
                  <td class="text-muted text-xs"><?= htmlspecialchars(substr($t['description'] ?? '—', 0, 40)) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="editTest(<?= $t['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                      <button class="btn btn-sm btn-outline-danger" onclick="toggleTest(<?= $t['id'] ?>, <?= $t['status'] ?>)"><i class="fa-solid fa-<?= $t['status']?'toggle-on':'toggle-off' ?>"></i></button>
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

<!-- Add Test Modal -->
<div class="modal fade" id="addTestModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-flask me-2 text-primary"></i>Add Lab Test</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8"><label class="form-label">Test Name *</label><input type="text" id="tName" class="form-control" required placeholder="e.g. Complete Blood Count"/></div>
          <div class="col-md-4"><label class="form-label">Test Code *</label><input type="text" id="tCode" class="form-control" required placeholder="e.g. CBC" style="text-transform:uppercase"/></div>
          <div class="col-md-4"><label class="form-label">Category</label><input type="text" id="tCat" class="form-control" placeholder="Haematology, Biochemistry…"/></div>
          <div class="col-md-4"><label class="form-label">Price (₹) *</label><input type="number" id="tPrice" class="form-control" min="0" step="0.01"/></div>
          <div class="col-md-4"><label class="form-label">Turnaround (hours)</label><input type="number" id="tTat" class="form-control" value="24" min="1"/></div>
          <div class="col-12"><label class="form-label">Description</label><textarea id="tDesc" class="form-control" rows="2" placeholder="Brief description of this test…"></textarea></div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitTest()"><i class="fa-solid fa-plus me-2"></i>Add Test</button>
      </div>
    </div>
  </div>
</div>

<?php
$catLabels = json_encode(array_column($catStats,'category'));
$catValues = json_encode(array_column($catStats,'orders'));
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSCharts.departmentPieChart('labCatChart', {$catLabels}, {$catValues});
});

function filterTests(q) {
  const rows = document.querySelectorAll('#testTable tbody tr');
  q = q.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

async function submitTest() {
  const name  = document.getElementById('tName').value.trim();
  const code  = document.getElementById('tCode').value.trim().toUpperCase();
  const price = document.getElementById('tPrice').value;
  if (!name || !code || !price) { HMS.toast('Name, code and price are required.','warning'); return; }
  HMS.toast('Test "'+name+'" added to catalogue!','success');
  bootstrap.Modal.getInstance(document.getElementById('addTestModal')).hide();
}

function editTest(id)   { HMS.toast('Editing test #'+id,'info'); }
function toggleTest(id) { HMS.toast('Test status toggled.','info'); setTimeout(()=>location.reload(),700); }
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
