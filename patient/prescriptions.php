<?php
// ============================================================
// patient/prescriptions.php — Patient Prescription History
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('patient');

$pageTitle = 'My Prescriptions';
$patient   = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
if (!$patient) redirect(APP_URL.'/login.php');
$pid = $patient['id'];

$active    = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE patient_id=? AND status='active'",[$pid])['c'];
$total     = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE patient_id=?",[$pid])['c'];

$prescriptions = Database::fetchAll("
    SELECT pr.id, pr.prescription_no, pr.diagnosis, pr.notes,
           pr.follow_up_date, pr.status, pr.created_at,
           u.full_name AS doctor_name, d.specialization, dep.name AS dept_name
    FROM prescriptions pr
    JOIN doctors     d   ON d.id=pr.doctor_id   JOIN users u  ON u.id=d.user_id
    JOIN departments dep ON dep.id=d.department_id
    WHERE pr.patient_id=?
    ORDER BY pr.created_at DESC
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
          <h1><i class="fa-solid fa-prescription me-2 text-primary"></i>My Prescriptions</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/patient/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Prescriptions</li>
          </ol></nav>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-prescription"></i></div><div><div class="stat-value" data-counter="<?= $total ?>">0</div><div class="stat-label">Total Prescriptions</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $active ?>">0</div><div class="stat-label">Active</div></div></div></div>
        <div class="col-12 col-md-4">
          <div class="card border-0 h-100" style="background:linear-gradient(135deg,rgba(14,165,233,.08),rgba(99,102,241,.08))">
            <div class="card-body d-flex align-items-center gap-3">
              <i class="fa-solid fa-pills fa-2x text-primary"></i>
              <div class="text-sm">Always take medicines as prescribed. Contact your doctor if you experience side effects.</div>
            </div>
          </div>
        </div>
      </div>

      <?php if(empty($prescriptions)): ?>
      <div class="card"><div class="card-body text-center py-5 text-muted"><i class="fa-solid fa-file-prescription fa-3x mb-3 d-block opacity-25"></i><p>No prescriptions found.</p></div></div>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach ($prescriptions as $i => $rx): ?>
        <div class="col-12 animate-fade-in-up delay-<?= min($i+1,8) ?>">
          <div class="card hover-lift">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-md-3">
                  <div class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($rx['prescription_no']) ?></div>
                  <div class="fw-700 mt-1"><?= htmlspecialchars($rx['doctor_name']) ?></div>
                  <div class="text-muted text-xs"><?= htmlspecialchars($rx['specialization']) ?></div>
                  <div class="text-muted text-xs"><?= htmlspecialchars($rx['dept_name']) ?></div>
                </div>
                <div class="col-md-4">
                  <?php if($rx['diagnosis']): ?>
                  <div class="text-xs text-muted mb-1">DIAGNOSIS</div>
                  <div class="text-sm"><?= htmlspecialchars($rx['diagnosis']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="col-md-2 text-center">
                  <div class="text-xs text-muted mb-1">DATE</div>
                  <div class="fw-600"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
                  <?php if($rx['follow_up_date']): ?>
                  <div class="text-xs text-warning mt-1">
                    <i class="fa-solid fa-calendar me-1"></i>Follow-up: <?= date('d M Y', strtotime($rx['follow_up_date'])) ?>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="col-md-2 text-center">
                  <span class="status-badge status-<?= $rx['status']==='active'?'completed':($rx['status']==='completed'?'in_progress':'cancelled') ?>">
                    <?= ucfirst($rx['status']) ?>
                  </span>
                </div>
                <div class="col-md-1 text-end">
                  <button class="btn btn-sm btn-outline-primary" onclick="viewRx(<?= $rx['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                </div>
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

<!-- View Rx Modal -->
<div class="modal fade" id="rxModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-prescription me-2 text-primary"></i>Prescription Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="rxModalBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" onclick="HMS.toast('Print coming soon','info')"><i class="fa-solid fa-print me-2"></i>Print</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function viewRx(id) {
  const modal = new bootstrap.Modal(document.getElementById('rxModal'));
  document.getElementById('rxModalBody').innerHTML = '<div class="text-center py-4"><div class="pulse-ring mx-auto mb-2"></div></div>';
  modal.show();

  // In full implementation: fetch prescription items from API
  document.getElementById('rxModalBody').innerHTML = `
    <div class="alert alert-info py-2 text-sm">
      <i class="fa-solid fa-circle-info me-2"></i>Prescription #${id} — Full details including medicines, dosage and instructions.
    </div>
    <div class="text-muted text-center py-3">
      <i class="fa-solid fa-prescription fa-3x mb-3 d-block opacity-25"></i>
      <p>Contact your doctor or the reception desk for a printed copy of this prescription.</p>
    </div>`;
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
