<?php
// ============================================================
// patient/reports.php — Patient Lab Reports
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('patient');

$pageTitle = 'My Reports';
$patient   = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
if (!$patient) redirect(APP_URL.'/login.php');
$pid = $patient['id'];

$totalReports    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE patient_id=? AND status='completed'",[$pid])['c'];
$pendingReports  = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE patient_id=? AND status IN ('ordered','sample_collected','processing')",[$pid])['c'];
$withFiles       = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE patient_id=? AND status='completed' AND report_file IS NOT NULL",[$pid])['c'];

$labOrders = Database::fetchAll("
    SELECT lo.id, lo.order_no, lo.status, lo.sample_date, lo.report_date,
           lo.result, lo.remarks, lo.report_file, lo.created_at,
           lt.name AS test_name, lt.category, lt.price, lt.turnaround
    FROM lab_orders lo
    JOIN lab_tests lt ON lt.id=lo.test_id
    WHERE lo.patient_id=?
    ORDER BY lo.created_at DESC
", [$pid]);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-file-waveform me-2 text-primary"></i>My Lab Reports</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/patient/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Reports</li>
          </ol></nav>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-medical"></i></div><div><div class="stat-value" data-counter="<?= $totalReports ?>">0</div><div class="stat-label">Reports Ready</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="stat-value" data-counter="<?= $pendingReports ?>">0</div><div class="stat-label">In Progress</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-download"></i></div><div><div class="stat-value" data-counter="<?= $withFiles ?>">0</div><div class="stat-label">Downloadable</div></div></div></div>
      </div>

      <?php if(empty($labOrders)): ?>
      <div class="card"><div class="card-body text-center py-5 text-muted"><i class="fa-solid fa-flask fa-3x mb-3 d-block opacity-25"></i><p>No lab orders found.</p></div></div>
      <?php else: ?>

      <!-- Pending Reports Banner -->
      <?php if($pendingReports > 0): ?>
      <div class="alert d-flex align-items-center gap-3 mb-4 animate-fade-in" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius-lg)">
        <i class="fa-solid fa-clock fa-2x text-warning"></i>
        <div>
          <div class="fw-700">Reports in Progress</div>
          <div class="text-sm text-muted"><?= $pendingReports ?> lab test(s) are currently being processed. You will be notified when results are ready.</div>
        </div>
      </div>
      <?php endif; ?>

      <div class="row g-3">
        <?php foreach ($labOrders as $i => $order):
          $statusColors = ['ordered'=>'booked','sample_collected'=>'confirmed','processing'=>'in_progress','completed'=>'completed','cancelled'=>'cancelled'];
          $cls = $statusColors[$order['status']] ?? 'booked';
        ?>
        <div class="col-xl-6 animate-fade-in-up delay-<?= min($i+1,8) ?>">
          <div class="card hover-lift" style="border-left:4px solid <?= $order['status']==='completed'?'var(--success)':($order['status']==='cancelled'?'var(--danger)':'var(--warning)') ?>">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <div class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($order['order_no']) ?></div>
                  <div class="fw-700 mt-1"><?= htmlspecialchars($order['test_name']) ?></div>
                  <div class="text-muted text-xs"><?= htmlspecialchars($order['category']) ?></div>
                </div>
                <span class="status-badge status-<?= $cls ?>"><?= ucfirst(str_replace('_',' ',$order['status'])) ?></span>
              </div>

              <!-- Timeline -->
              <div class="d-flex gap-4 text-xs text-muted mb-3">
                <div>
                  <i class="fa-solid fa-calendar me-1"></i>
                  <span>Ordered: <?= date('d M Y', strtotime($order['created_at'])) ?></span>
                </div>
                <?php if($order['sample_date']): ?>
                <div>
                  <i class="fa-solid fa-vial me-1"></i>
                  <span>Sample: <?= date('d M Y', strtotime($order['sample_date'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if($order['report_date']): ?>
                <div>
                  <i class="fa-solid fa-file-medical me-1 text-success"></i>
                  <span class="text-success">Ready: <?= date('d M Y', strtotime($order['report_date'])) ?></span>
                </div>
                <?php endif; ?>
              </div>

              <!-- Progress indicator for pending -->
              <?php if($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
              <div class="mb-3">
                <?php
                $steps = ['ordered','sample_collected','processing','completed'];
                $currentStep = array_search($order['status'], $steps);
                ?>
                <div class="d-flex align-items-center gap-1 text-xs">
                  <?php foreach ($steps as $si => $step): ?>
                  <div class="flex-shrink-0 text-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                         style="width:24px;height:24px;background:<?= $si <= $currentStep?'var(--primary)':'var(--border)' ?>">
                      <i class="fa-solid fa-check" style="color:<?= $si <= $currentStep?'#fff':'var(--text-muted)' ?>;font-size:10px"></i>
                    </div>
                    <div style="color:<?= $si <= $currentStep?'var(--primary)':'var(--text-muted)' ?>;"><?= ucfirst(str_replace('_',"\n",$step)) ?></div>
                  </div>
                  <?php if($si < count($steps)-1): ?>
                  <div class="flex-1" style="height:2px;background:<?= $si < $currentStep?'var(--primary)':'var(--border)' ?>"></div>
                  <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Result summary -->
              <?php if($order['result'] && $order['status']==='completed'): ?>
              <div class="alert py-2 mb-3 text-sm" style="background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.3)">
                <i class="fa-solid fa-clipboard-check me-2 text-success"></i>
                <?= htmlspecialchars(substr($order['result'], 0, 120)) ?><?= strlen($order['result']) > 120 ? '…' : '' ?>
              </div>
              <?php endif; ?>

              <!-- Actions -->
              <div class="d-flex gap-2">
                <?php if($order['status']==='completed'): ?>
                <button class="btn btn-sm btn-outline-primary flex-1" onclick="viewReport(<?= $order['id'] ?>)">
                  <i class="fa-solid fa-eye me-1"></i>View Result
                </button>
                <?php if($order['report_file']): ?>
                <a href="<?= APP_URL.'/assets/uploads/'.htmlspecialchars($order['report_file']) ?>" class="btn btn-sm btn-success flex-1" target="_blank">
                  <i class="fa-solid fa-download me-1"></i>Download
                </a>
                <?php endif; ?>
                <?php else: ?>
                <div class="flex-1 text-muted text-xs d-flex align-items-center">
                  <i class="fa-solid fa-clock me-2 text-warning"></i>
                  Expected in <?= $order['turnaround'] ?>h from sample collection
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<!-- View Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-file-medical me-2 text-success"></i>Lab Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="reportModalBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function viewReport(id) {
  const modal = new bootstrap.Modal(document.getElementById('reportModal'));
  document.getElementById('reportModalBody').innerHTML = '<div class="text-center py-4"><div class="pulse-ring mx-auto mb-2"></div></div>';
  modal.show();

  const res = await HMSAjax.get(APP_URL+'/api/reports.php?id='+id);
  if (!res.success || !res.data) {
    document.getElementById('reportModalBody').innerHTML = '<p class="text-center text-danger">Failed to load.</p>';
    return;
  }
  const r = res.data;
  document.getElementById('reportModalBody').innerHTML = `
    <div class="row g-3">
      <div class="col-md-6"><strong class="text-muted text-xs d-block">ORDER NO</strong><span class="text-mono fw-600 text-primary">${r.order_no}</span></div>
      <div class="col-md-6"><strong class="text-muted text-xs d-block">TEST</strong><span class="fw-600">${r.test_name}</span></div>
      <div class="col-md-6"><strong class="text-muted text-xs d-block">CATEGORY</strong>${r.category}</div>
      <div class="col-md-6"><strong class="text-muted text-xs d-block">REPORT DATE</strong>${r.report_date ? new Date(r.report_date).toLocaleString('en-IN') : '—'}</div>
      <div class="col-12">
        <strong class="text-muted text-xs d-block mb-1">RESULT</strong>
        <div class="card" style="background:var(--bg)">
          <div class="card-body text-sm">${(r.result||'No result').replace(/\n/g,'<br>')}</div>
        </div>
      </div>
      ${r.remarks ? '<div class="col-12"><strong class="text-muted text-xs d-block mb-1">REMARKS</strong><div class="text-sm text-muted">'+r.remarks+'</div></div>' : ''}
      ${r.report_file ? '<div class="col-12"><a href="'+APP_URL+'/assets/uploads/'+r.report_file+'" class="btn btn-success" target="_blank"><i class="fa-solid fa-download me-2"></i>Download Report</a></div>' : ''}
    </div>`;
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
