<?php
// ============================================================
// laboratory/reports.php — Lab Reports Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['lab_technician','admin']);

$pageTitle = 'Lab Reports';

// Stats
$totalReports   = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed'")['c'];
$todayReports   = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND DATE(report_date)=CURDATE()")['c'];
$withFile       = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status='completed' AND report_file IS NOT NULL")['c'];

// Filters
$search = clean($_GET['q'] ?? '');
$filterCat = clean($_GET['cat'] ?? '');
$filterFrom = clean($_GET['from'] ?? '');
$filterTo   = clean($_GET['to']   ?? '');

$where  = ["lo.status='completed'"]; $params = [];
if ($search) {
    $like = "%{$search}%";
    $where[] = '(u.full_name LIKE ? OR p.patient_code LIKE ? OR lo.order_no LIKE ? OR lt.name LIKE ?)';
    $params  = array_merge($params,[$like,$like,$like,$like]);
}
if ($filterCat)  { $where[] = 'lt.category=?'; $params[] = $filterCat; }
if ($filterFrom) { $where[] = 'DATE(lo.report_date)>=?'; $params[] = $filterFrom; }
if ($filterTo)   { $where[] = 'DATE(lo.report_date)<=?'; $params[] = $filterTo; }

$ws = implode(' AND ', $where);

$reports = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.report_date, lo.result, lo.remarks, lo.report_file,
           lt.name AS test_name, lt.category, lt.price,
           u.full_name AS patient_name, p.patient_code, p.id AS patient_id,
           u_t.full_name AS tech_name
    FROM lab_orders lo
    JOIN lab_tests lt  ON lt.id = lo.test_id
    JOIN patients  p   ON p.id  = lo.patient_id  JOIN users u  ON u.id=p.user_id
    LEFT JOIN users u_t ON u_t.id = lo.technician_id
    WHERE {$ws}
    ORDER BY lo.report_date DESC LIMIT 80
", $params);

$categories = Database::fetchAll("SELECT DISTINCT category FROM lab_tests WHERE category IS NOT NULL ORDER BY category");

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-file-medical me-2 text-primary"></i>Lab Reports</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/laboratory/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Reports</li>
          </ol></nav>
        </div>
        <button class="btn btn-outline-secondary ripple-btn" onclick="HMS.toast('Exporting reports…','info')">
          <i class="fa-solid fa-file-export me-2"></i>Export
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-medical"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalReports ?>">0</div><div class="stat-label">Total Reports</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayReports ?>">0</div><div class="stat-label">Today's Reports</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-pdf"></i></div>
            <div><div class="stat-value" data-counter="<?= $withFile ?>">0</div><div class="stat-label">With Files</div></div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <form method="GET" class="card mb-4">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Search</label>
              <input type="text" name="q" class="form-control" placeholder="Patient, order no, test…" value="<?= htmlspecialchars($search) ?>"/>
            </div>
            <div class="col-md-2">
              <label class="form-label">Category</label>
              <select name="cat" class="form-select">
                <option value="">All</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c['category']) ?>" <?= $filterCat===$c['category']?'selected':'' ?>><?= htmlspecialchars($c['category']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">From Date</label>
              <input type="date" name="from" class="form-control" value="<?= $filterFrom ?>"/>
            </div>
            <div class="col-md-2">
              <label class="form-label">To Date</label>
              <input type="date" name="to" class="form-control" value="<?= $filterTo ?>"/>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button></div>
            <div class="col-md-1"><a href="reports.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-right"></i></a></div>
          </div>
        </div>
      </form>

      <!-- Reports Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Completed Reports <span class="badge bg-primary ms-1"><?= count($reports) ?></span></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="reportsTable">
              <thead>
                <tr><th>#</th><th>Order No</th><th>Patient</th><th>Test</th><th>Category</th><th>Report Date</th><th>Technician</th><th>File</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($reports as $i => $r): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-success text-xs"><?= htmlspecialchars($r['order_no']) ?></span></td>
                  <td>
                    <div class="fw-600"><?= htmlspecialchars($r['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($r['patient_code']) ?></div>
                  </td>
                  <td class="fw-600 text-sm"><?= htmlspecialchars($r['test_name']) ?></td>
                  <td><span class="badge bg-secondary text-xs"><?= htmlspecialchars($r['category']) ?></span></td>
                  <td class="text-sm"><?= $r['report_date'] ? date('d M Y', strtotime($r['report_date'])) : '—' ?><br><small class="text-muted"><?= $r['report_date'] ? date('h:i A', strtotime($r['report_date'])) : '' ?></small></td>
                  <td class="text-muted text-xs"><?= htmlspecialchars($r['tech_name'] ?? '—') ?></td>
                  <td>
                    <?php if($r['report_file']): ?>
                      <span class="badge bg-success"><i class="fa-solid fa-file-pdf me-1"></i>Available</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Text only</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?= $r['id'] ?>)" title="View"><i class="fa-solid fa-eye"></i></button>
                      <?php if($r['report_file']): ?>
                      <a href="<?= APP_URL.'/assets/uploads/'.htmlspecialchars($r['report_file']) ?>" class="btn btn-sm btn-outline-success" target="_blank" title="Download"><i class="fa-solid fa-download"></i></a>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-outline-info" onclick="printReport(<?= $r['id'] ?>)" title="Print"><i class="fa-solid fa-print"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($reports)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No reports found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- View Report Modal -->
<div class="modal fade" id="viewReportModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-file-medical me-2 text-primary"></i>Report Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewReportBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="downloadReportBtn" style="display:none"><i class="fa-solid fa-download me-2"></i>Download</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function viewReport(id) {
  const modal = new bootstrap.Modal(document.getElementById('viewReportModal'));
  document.getElementById('viewReportBody').innerHTML = '<div class="text-center py-4"><div class="pulse-ring mx-auto mb-2"></div><p class="text-muted">Loading…</p></div>';
  modal.show();

  const res = await HMSAjax.get(APP_URL+'/api/reports.php?id='+id);
  if (!res.success) {
    document.getElementById('viewReportBody').innerHTML = '<p class="text-center text-danger">Failed to load.</p>';
    return;
  }
  const r = res.data;
  document.getElementById('viewReportBody').innerHTML = `
    <div class="row g-3">
      <div class="col-md-6"><strong>Order No:</strong><div class="text-mono text-primary fw-600">${r.order_no||'—'}</div></div>
      <div class="col-md-6"><strong>Test:</strong><div class="fw-600">${r.test_name||'—'}</div></div>
      <div class="col-md-6"><strong>Patient:</strong><div>${r.patient_name||'—'} <small class="text-muted">(${r.patient_code||''})</small></div></div>
      <div class="col-md-6"><strong>Report Date:</strong><div>${r.report_date ? new Date(r.report_date).toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'}) : '—'}</div></div>
      <div class="col-12"><strong>Result:</strong>
        <div class="card mt-1" style="background:var(--bg)">
          <div class="card-body py-2 text-sm">${(r.result||'No result recorded').replace(/\n/g,'<br>')}</div>
        </div>
      </div>
      ${r.remarks ? '<div class="col-12"><strong>Remarks:</strong><div class="text-muted text-sm">'+r.remarks+'</div></div>' : ''}
      ${r.report_file ? '<div class="col-12"><a href="'+APP_URL+'/assets/uploads/'+r.report_file+'" class="btn btn-success" target="_blank"><i class="fa-solid fa-download me-2"></i>Download Report File</a></div>' : ''}
    </div>`;
}

function printReport(id) {
  HMS.toast('Opening print view for report '+id,'info');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
