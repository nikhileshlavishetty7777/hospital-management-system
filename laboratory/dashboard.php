<?php
// ============================================================
// laboratory/dashboard.php — Lab Technician Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['lab_technician','admin']);

$pageTitle = 'Laboratory Dashboard';

// Stats
$totalOrders   = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE DATE(created_at)=CURDATE()")['c'];
$pending       = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='ordered'")['c'];
$processing    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status IN ('sample_collected','processing')")['c'];
$completed     = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND DATE(created_at)=CURDATE()")['c'];
$totalTests    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_tests WHERE status=1")['c'];

// Pending orders
$pendingOrders = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.created_at, lo.status, lo.payment_status,
           lt.name AS test_name, lt.category, lt.turnaround,
           u.full_name AS patient_name, p.patient_code
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id = lo.test_id
    JOIN patients  p  ON p.id  = lo.patient_id
    JOIN users     u  ON u.id  = p.user_id
    WHERE lo.status IN ('ordered','sample_collected','processing')
    ORDER BY lo.created_at ASC LIMIT 15
");

// Recent completed
$recentCompleted = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.report_date, lo.status,
           lt.name AS test_name,
           u.full_name AS patient_name, p.patient_code
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id = lo.test_id
    JOIN patients  p  ON p.id  = lo.patient_id
    JOIN users     u  ON u.id  = p.user_id
    WHERE lo.status='completed'
    ORDER BY lo.report_date DESC LIMIT 8
");

// All test types for chart
$testStats = Database::fetchAll("
    SELECT lt.category, COUNT(lo.id) AS cnt
    FROM lab_orders lo JOIN lab_tests lt ON lt.id=lo.test_id
    WHERE MONTH(lo.created_at)=MONTH(NOW())
    GROUP BY lt.category ORDER BY cnt DESC LIMIT 6
");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Banner -->
      <div class="welcome-banner" style="background:linear-gradient(135deg,#f59e0b,#ef4444)">
        <div>
          <div class="welcome-title">Laboratory Module 🔬</div>
          <div class="welcome-subtitle">Test management, report uploads & result tracking</div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="badge bg-white text-danger fw-600"><i class="fa-solid fa-clock me-1"></i><?= $pending ?> pending orders</span>
            <span class="badge bg-white text-warning fw-600"><i class="fa-solid fa-spinner me-1"></i><?= $processing ?> in process</span>
            <span class="badge bg-white text-success fw-600"><i class="fa-solid fa-check me-1"></i><?= $completed ?> completed today</span>
          </div>
        </div>
        <i class="fa-solid fa-flask welcome-icon"></i>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-vials"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalOrders ?>">0</div><div class="stat-label">Orders Today</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="stat-value" data-counter="<?= $pending ?>">0</div><div class="stat-label">Pending</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
            <div><div class="stat-value" data-counter="<?= $processing ?>">0</div><div class="stat-label">Processing</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-value" data-counter="<?= $completed ?>">0</div><div class="stat-label">Completed Today</div></div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Pending Orders -->
        <div class="col-xl-8">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-list me-2 text-primary"></i>Pending Lab Orders</span>
              <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/laboratory/tests.php" class="btn btn-sm btn-outline-primary">View All</a>
                <button class="btn btn-sm btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                  <i class="fa-solid fa-plus me-1"></i>New Order
                </button>
              </div>
            </div>
            <div class="card-body p-0" style="max-height:420px;overflow-y:auto">
              <?php if(empty($pendingOrders)): ?>
              <div class="text-center p-5 text-muted">
                <i class="fa-solid fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                <p>All orders processed!</p>
              </div>
              <?php else: ?>
              <table class="table mb-0">
                <thead><tr><th>Order No</th><th>Patient</th><th>Test</th><th>Category</th><th>Status</th><th>TAT</th><th>Action</th></tr></thead>
                <tbody>
                  <?php foreach ($pendingOrders as $i => $order): ?>
                  <tr class="animate-fade-in delay-<?= min($i+1,8) ?>">
                    <td class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($order['order_no']) ?></td>
                    <td>
                      <div class="fw-600 text-sm"><?= htmlspecialchars($order['patient_name']) ?></div>
                      <div class="text-muted text-xs"><?= htmlspecialchars($order['patient_code']) ?></div>
                    </td>
                    <td class="fw-600 text-sm"><?= htmlspecialchars($order['test_name']) ?></td>
                    <td><span class="badge bg-secondary text-xs"><?= htmlspecialchars($order['category']) ?></span></td>
                    <td>
                      <?php
                      $statusClass = [
                        'ordered'          => 'status-booked',
                        'sample_collected' => 'status-confirmed',
                        'processing'       => 'status-in_progress',
                      ][$order['status']] ?? 'status-booked';
                      ?>
                      <span class="status-badge <?= $statusClass ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span>
                    </td>
                    <td class="text-muted text-xs"><?= $order['turnaround'] ?>h</td>
                    <td>
                      <div class="d-flex gap-1">
                        <?php if($order['status']==='ordered'): ?>
                        <button class="btn btn-sm btn-outline-primary" title="Collect Sample" onclick="updateLabStatus(<?= $order['id'] ?>,'sample_collected')">
                          <i class="fa-solid fa-vial"></i>
                        </button>
                        <?php elseif($order['status']==='sample_collected'): ?>
                        <button class="btn btn-sm btn-outline-warning" title="Start Processing" onclick="updateLabStatus(<?= $order['id'] ?>,'processing')">
                          <i class="fa-solid fa-microscope"></i>
                        </button>
                        <?php elseif($order['status']==='processing'): ?>
                        <button class="btn btn-sm btn-outline-success" title="Upload Report" onclick="uploadReport(<?= $order['id'] ?>)">
                          <i class="fa-solid fa-upload"></i>
                        </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Test Distribution Chart -->
        <div class="col-xl-4">
          <div class="chart-card mb-3">
            <div class="chart-card-header">
              <div class="chart-card-title">Tests This Month</div>
            </div>
            <div style="height:180px"><canvas id="testPieChart"></canvas></div>
          </div>

          <!-- Available Tests -->
          <div class="card">
            <div class="card-header fw-700 text-sm">
              <i class="fa-solid fa-list-check me-2 text-primary"></i>Available Tests (<?= $totalTests ?>)
            </div>
            <div class="list-group list-group-flush" style="max-height:160px;overflow-y:auto">
              <?php
              $tests = Database::fetchAll("SELECT name, code, price, category FROM lab_tests WHERE status=1 ORDER BY category, name LIMIT 12");
              foreach ($tests as $test): ?>
              <div class="list-group-item px-3 py-2">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-600 text-xs"><?= htmlspecialchars($test['name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($test['code']) ?> · <?= htmlspecialchars($test['category']) ?></div>
                  </div>
                  <span class="fw-700 text-primary text-xs">₹<?= number_format($test['price'],0) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Completed -->
      <div class="card">
        <div class="card-header"><i class="fa-solid fa-file-medical me-2 text-success"></i>Recently Completed Reports</div>
        <div class="card-body p-0">
          <table class="table table-sm hms-table mb-0">
            <thead><tr><th>Order No</th><th>Patient</th><th>Test</th><th>Completed</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($recentCompleted as $r): ?>
              <tr>
                <td class="text-mono fw-600 text-success text-xs"><?= htmlspecialchars($r['order_no']) ?></td>
                <td>
                  <div class="fw-600 text-sm"><?= htmlspecialchars($r['patient_name']) ?></div>
                  <div class="text-muted text-xs"><?= htmlspecialchars($r['patient_code']) ?></div>
                </td>
                <td class="text-sm"><?= htmlspecialchars($r['test_name']) ?></td>
                <td class="text-muted text-xs"><?= $r['report_date'] ? date('d M Y h:i A', strtotime($r['report_date'])) : '—' ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="HMS.toast('Viewing report…','info')"><i class="fa-solid fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-success" onclick="HMS.toast('Downloading…','success')"><i class="fa-solid fa-download"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($recentCompleted)): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No completed reports yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Upload Report Modal -->
<div class="modal fade" id="uploadReportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-upload me-2 text-primary"></i>Upload Lab Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="uploadOrderId"/>
        <div class="mb-3">
          <label class="form-label">Result / Summary</label>
          <textarea id="reportResult" class="form-control" rows="3" placeholder="Enter test result…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Report File (PDF / Image)</label>
          <input type="file" id="reportFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea id="reportRemarks" class="form-control" rows="2" placeholder="Additional observations…"></textarea>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success ripple-btn" onclick="submitReport()">
          <i class="fa-solid fa-upload me-2"></i>Upload Report
        </button>
      </div>
    </div>
  </div>
</div>

<!-- New Lab Order Modal -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-flask me-2 text-primary"></i>New Lab Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Patient *</label>
          <input type="text" id="labPatientSearch" class="form-control" placeholder="Search patient…"/>
          <input type="hidden" id="labPatientId"/>
          <div id="labPatientResults"></div>
        </div>
        <div class="mb-3">
          <label class="form-label">Test *</label>
          <select id="labTestId" class="form-select">
            <option value="">Select Test</option>
            <?php foreach ($tests as $t): ?>
            <option value="<?= $t['code'] ?>"><?= htmlspecialchars($t['name']) ?> — ₹<?= number_format($t['price'],0) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="HMS.toast('Order saved!','success');bootstrap.Modal.getInstance(document.getElementById(\'newOrderModal\')).hide()">
          <i class="fa-solid fa-plus me-2"></i>Create Order
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$testLabels = json_encode(array_column($testStats,'category'));
$testValues = json_encode(array_column($testStats,'cnt'));
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSCharts.departmentPieChart('testPieChart', {$testLabels}, {$testValues});

  HMSAjax.liveSearch('#labPatientSearch','labPatientResults',
    APP_URL + '/ajax/search_patient.php',
    items => items.map(p =>
      '<div class="p-2 border-bottom text-sm cursor-pointer" onclick="document.getElementById(\'labPatientId\').value='+p.id+';document.getElementById(\'labPatientSearch\').value=\''+p.full_name+'\';document.getElementById(\'labPatientResults\').innerHTML=\'\'">' +
      '<strong>' + p.full_name + '</strong> <small class=text-muted>' + p.patient_code + '</small></div>'
    ).join('')
  );
});

async function updateLabStatus(id, status) {
  const res = await HMSAjax.put(APP_URL + '/api/reports.php?id=' + id, { status });
  if (res.success) {
    HMS.toast('Status updated to: ' + status.replace('_',' '), 'success');
    setTimeout(() => location.reload(), 700);
  }
}

function uploadReport(id) {
  document.getElementById('uploadOrderId').value = id;
  new bootstrap.Modal(document.getElementById('uploadReportModal')).show();
}

async function submitReport() {
  const id      = document.getElementById('uploadOrderId').value;
  const result  = document.getElementById('reportResult').value;
  const remarks = document.getElementById('reportRemarks').value;
  const fileEl  = document.getElementById('reportFile');

  const fd = new FormData();
  fd.append('status',  'completed');
  fd.append('result',  result);
  fd.append('remarks', remarks);
  if (fileEl.files[0]) fd.append('report_file', fileEl.files[0]);

  const res = await HMSAjax.post(APP_URL + '/api/reports.php?id=' + id, fd);
  if (res.success) {
    HMS.toast('Report uploaded successfully!', 'success');
    bootstrap.Modal.getInstance(document.getElementById('uploadReportModal')).hide();
    setTimeout(() => location.reload(), 800);
  }
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
