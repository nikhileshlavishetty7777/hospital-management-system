<?php
// ============================================================
// laboratory/tests.php — Lab Tests & Orders Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['lab_technician','admin']);

$pageTitle = 'Lab Tests';

// Stats
$totalOrders   = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE DATE(created_at)=CURDATE()")['c'];
$ordered       = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='ordered'")['c'];
$processing    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status IN ('sample_collected','processing')")['c'];
$completedToday= Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND DATE(report_date)=CURDATE()")['c'];

// Filters
$filterStatus = clean($_GET['status'] ?? '');
$filterDate   = clean($_GET['date']   ?? '');
$where  = ['1=1']; $params = [];
if ($filterStatus) { $where[] = 'lo.status=?';              $params[] = $filterStatus; }
if ($filterDate)   { $where[] = 'DATE(lo.created_at)=?';   $params[] = $filterDate; }
$ws = implode(' AND ', $where);

$orders = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.status, lo.payment_status, lo.created_at,
           lo.sample_date, lo.report_date, lo.result,
           lt.name AS test_name, lt.category, lt.price, lt.turnaround,
           u.full_name AS patient_name, p.patient_code,
           u_d.full_name AS doctor_name
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id=lo.test_id
    JOIN patients  p  ON p.id=lo.patient_id  JOIN users u  ON u.id=p.user_id
    LEFT JOIN users u_d ON u_d.id=lo.technician_id
    WHERE {$ws}
    ORDER BY lo.created_at DESC LIMIT 60
", $params);

// All available tests for new order modal
$allTests = Database::fetchAll("SELECT id, name, code, category, price, turnaround FROM lab_tests WHERE status=1 ORDER BY category, name");

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-vials me-2 text-primary"></i>Lab Tests</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/laboratory/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Tests</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#newOrderModal">
          <i class="fa-solid fa-plus me-2"></i>New Lab Order
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-vials"></i></div><div><div class="stat-value" data-counter="<?= $totalOrders ?>">0</div><div class="stat-label">Today's Orders</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-start"></i></div><div><div class="stat-value" data-counter="<?= $ordered ?>">0</div><div class="stat-label">Ordered</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-microscope"></i></div><div><div class="stat-value" data-counter="<?= $processing ?>">0</div><div class="stat-label">Processing</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $completedToday ?>">0</div><div class="stat-label">Completed Today</div></div></div></div>
      </div>

      <!-- Filters -->
      <div class="card mb-4">
        <div class="card-body py-3">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach(['ordered','sample_collected','processing','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="<?= $filterDate ?>"/>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
            <div class="col-md-2"><a href="tests.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-right me-1"></i>Reset</a></div>
          </form>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Lab Orders <span class="badge bg-primary ms-1"><?= count($orders) ?></span></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="ordersTable">
              <thead>
                <tr><th>Order No</th><th>Patient</th><th>Test</th><th>Category</th><th>Ordered</th><th>TAT</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $o):
                  $statusMap = ['ordered'=>'status-booked','sample_collected'=>'status-confirmed','processing'=>'status-in_progress','completed'=>'status-completed','cancelled'=>'status-cancelled'];
                  $cls = $statusMap[$o['status']] ?? 'status-booked';
                ?>
                <tr>
                  <td><span class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($o['order_no']) ?></span></td>
                  <td>
                    <div class="fw-600"><?= htmlspecialchars($o['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($o['patient_code']) ?></div>
                  </td>
                  <td class="fw-600 text-sm"><?= htmlspecialchars($o['test_name']) ?></td>
                  <td><span class="badge bg-secondary text-xs"><?= htmlspecialchars($o['category']) ?></span></td>
                  <td class="text-xs"><?= date('d M, h:i A',strtotime($o['created_at'])) ?></td>
                  <td class="text-muted text-xs"><?= $o['turnaround'] ?>h</td>
                  <td><span class="status-badge <?= $cls ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
                  <td><span class="badge <?= $o['payment_status']==='paid'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if($o['status']==='ordered'): ?>
                      <button class="btn btn-sm btn-outline-primary" title="Collect Sample" onclick="updateStatus(<?= $o['id'] ?>,'sample_collected')"><i class="fa-solid fa-vial"></i></button>
                      <?php elseif($o['status']==='sample_collected'): ?>
                      <button class="btn btn-sm btn-outline-warning" title="Start Processing" onclick="updateStatus(<?= $o['id'] ?>,'processing')"><i class="fa-solid fa-microscope"></i></button>
                      <?php elseif($o['status']==='processing'): ?>
                      <button class="btn btn-sm btn-outline-success" title="Upload Report" onclick="openUpload(<?= $o['id'] ?>,'<?= addslashes($o['test_name']) ?>')"><i class="fa-solid fa-upload"></i></button>
                      <?php elseif($o['status']==='completed'): ?>
                      <button class="btn btn-sm btn-outline-info" title="View Report" onclick="HMS.toast('Opening report…','info')"><i class="fa-solid fa-eye"></i></button>
                      <?php endif; ?>
                      <?php if(!in_array($o['status'],['completed','cancelled'])): ?>
                      <button class="btn btn-sm btn-outline-danger" title="Cancel" onclick="cancelOrder(<?= $o['id'] ?>)"><i class="fa-solid fa-xmark"></i></button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($orders)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No lab orders found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- New Order Modal -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-flask me-2 text-primary"></i>Create Lab Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <input type="text" id="labPatSearch" class="form-control" placeholder="Search patient…"/>
            <input type="hidden" id="labPatId"/>
            <div id="labPatResults"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Test *</label>
            <select id="labTestSel" class="form-select" onchange="showTestInfo()">
              <option value="">Select Test</option>
              <?php foreach ($allTests as $t): ?>
              <option value="<?= $t['id'] ?>" data-price="<?= $t['price'] ?>" data-tat="<?= $t['turnaround'] ?>">
                <?= htmlspecialchars($t['name']) ?> — <?= htmlspecialchars($t['category']) ?> (₹<?= number_format($t['price'],0) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Price (₹)</label>
            <input type="text" id="labTestPrice" class="form-control" readonly/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Turnaround Time</label>
            <input type="text" id="labTestTat" class="form-control" readonly/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Priority</label>
            <select id="labPriority" class="form-select">
              <option value="normal">Normal</option>
              <option value="urgent">Urgent</option>
              <option value="stat">STAT</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitOrder()"><i class="fa-solid fa-flask me-2"></i>Create Order</button>
      </div>
    </div>
  </div>
</div>

<!-- Upload Report Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-upload me-2 text-success"></i>Upload Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="uploadId"/>
        <p class="fw-600 text-sm mb-3" id="uploadTestName"></p>
        <div class="mb-3">
          <label class="form-label">Result Summary *</label>
          <textarea id="uploadResult" class="form-control" rows="3" placeholder="Enter test result values and interpretation…"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Report File (PDF / Image)</label>
          <input type="file" id="uploadFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Remarks</label>
          <textarea id="uploadRemarks" class="form-control" rows="2" placeholder="Additional notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success ripple-btn" onclick="submitUpload()"><i class="fa-solid fa-upload me-2"></i>Upload & Complete</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSAjax.liveSearch('#labPatSearch','labPatResults',APP_URL+'/ajax/search_patient.php',
    items => '<div class="border rounded shadow-sm">' + items.map(p =>
      '<div class="p-2 border-bottom text-sm cursor-pointer" onclick="document.getElementById(\'labPatId\').value='+p.id+';document.getElementById(\'labPatSearch\').value=\''+p.full_name.replace(/'/g,"\\'")+'\';document.getElementById(\'labPatResults\').innerHTML=\'\'">'+
      '<strong>'+p.full_name+'</strong> <small class=text-muted>'+p.patient_code+'</small></div>'
    ).join('')+'</div>'
  );
});

function showTestInfo() {
  const sel = document.getElementById('labTestSel');
  const opt = sel.options[sel.selectedIndex];
  document.getElementById('labTestPrice').value = opt.dataset.price ? '₹'+parseFloat(opt.dataset.price).toFixed(0) : '';
  document.getElementById('labTestTat').value   = opt.dataset.tat   ? opt.dataset.tat+'h turnaround' : '';
}

async function submitOrder() {
  const pid    = document.getElementById('labPatId').value;
  const testId = document.getElementById('labTestSel').value;
  if (!pid)    { HMS.toast('Please select a patient.','warning'); return; }
  if (!testId) { HMS.toast('Please select a test.','warning'); return; }

  const res = await HMSAjax.post(APP_URL+'/api/reports.php', { patient_id:pid, test_id:testId });
  if (res.success) {
    HMS.toast('Lab order created: '+res.order_no,'success');
    bootstrap.Modal.getInstance(document.getElementById('newOrderModal')).hide();
    setTimeout(()=>location.reload(),800);
  }
}

async function updateStatus(id, status) {
  const labels = {sample_collected:'marked as Sample Collected', processing:'moved to Processing'};
  HMS.confirm('Mark order as '+(labels[status]||status)+'?', async () => {
    const res = await HMSAjax.put(APP_URL+'/api/reports.php?id='+id, {status});
    if (res.success) { HMS.toast('Status updated!','success'); setTimeout(()=>location.reload(),700); }
  });
}

function openUpload(id, testName) {
  document.getElementById('uploadId').value = id;
  document.getElementById('uploadTestName').textContent = 'Test: '+testName;
  new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

async function submitUpload() {
  const id      = document.getElementById('uploadId').value;
  const result  = document.getElementById('uploadResult').value;
  const remarks = document.getElementById('uploadRemarks').value;
  const fileEl  = document.getElementById('uploadFile');
  if (!result.trim()) { HMS.toast('Please enter result summary.','warning'); return; }

  const fd = new FormData();
  fd.append('result',  result);
  fd.append('remarks', remarks);
  if (fileEl.files[0]) fd.append('report_file', fileEl.files[0]);

  const res = await HMSAjax.post(APP_URL+'/api/reports.php?id='+id, fd);
  if (res.success) {
    HMS.toast('Report uploaded successfully!','success');
    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
    setTimeout(()=>location.reload(),800);
  }
}

async function cancelOrder(id) {
  HMS.confirm('Cancel this lab order?', async () => {
    const res = await HMSAjax.put(APP_URL+'/api/reports.php?id='+id, {status:'cancelled'});
    if (res.success) { HMS.toast('Order cancelled.','warning'); setTimeout(()=>location.reload(),700); }
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
