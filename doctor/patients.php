<?php
// ============================================================
// doctor/patients.php — Doctor's Patient List
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'My Patients';
$userId    = Auth::id();
$doctor    = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [$userId]);
if (!$doctor) { flash('danger','Doctor profile not found.'); redirect(APP_URL.'/login.php'); }
$did = $doctor['id'];

// Stats
$totalPat   = Database::fetchOne("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id=?",[$did])['c'];
$todayPat   = Database::fetchOne("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE()",[$did])['c'];
$newThisMonth = Database::fetchOne("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id=? AND MONTH(created_at)=MONTH(NOW())",[$did])['c'];

// Search
$search = clean($_GET['q'] ?? '');
$params = [$did];
$where  = 'a.doctor_id=?';
if ($search) {
    $where   .= ' AND (u.full_name LIKE ? OR p.patient_code LIKE ? OR u.phone LIKE ?)';
    $like     = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$patients = Database::fetchAll("
    SELECT DISTINCT
        p.id, p.patient_code, p.gender, p.blood_group, p.dob, p.allergies,
        u.full_name, u.email, u.phone,
        MAX(a.appointment_date) AS last_visit,
        COUNT(a.id)             AS total_visits,
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id=p.id AND doctor_id=?) AS rx_count
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id
    JOIN users    u ON u.id=p.user_id
    WHERE {$where}
    GROUP BY p.id
    ORDER BY last_visit DESC
", array_merge([$did], $params));

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-hospital-user me-2 text-primary"></i>My Patients</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/doctor/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Patients</li>
          </ol></nav>
        </div>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalPat ?>">0</div><div class="stat-label">Total Patients</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayPat ?>">0</div><div class="stat-label">Today</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div><div class="stat-value" data-counter="<?= $newThisMonth ?>">0</div><div class="stat-label">This Month</div></div>
          </div>
        </div>
      </div>

      <!-- Search -->
      <form method="GET" class="card mb-4">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Search Patients</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Name, patient code or phone…" value="<?= htmlspecialchars($search) ?>"/>
              </div>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Search</button>
            </div>
            <div class="col-md-2">
              <a href="?" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-rotate-right me-1"></i>Reset</a>
            </div>
          </div>
        </div>
      </form>

      <!-- Patients Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Patient List <span class="badge bg-primary ms-1"><?= count($patients) ?></span></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="patientsTable">
              <thead>
                <tr><th>#</th><th>Patient</th><th>Code</th><th>Gender</th><th>Blood</th><th>Phone</th><th>Visits</th><th>Last Visit</th><th>Allergies</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($patients as $i => $p): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="user-avatar-sm avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($p['full_name'],0,2)) ?></div>
                      <div>
                        <div class="fw-600"><?= htmlspecialchars($p['full_name']) ?></div>
                        <div class="text-muted text-xs"><?= htmlspecialchars($p['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="text-mono fw-600 text-primary"><?= htmlspecialchars($p['patient_code']) ?></span></td>
                  <td><?= ucfirst($p['gender']) ?></td>
                  <td><?= $p['blood_group'] ? '<span class="badge bg-danger">'.htmlspecialchars($p['blood_group']).'</span>' : '—' ?></td>
                  <td><?= htmlspecialchars($p['phone']) ?></td>
                  <td><span class="badge bg-primary"><?= $p['total_visits'] ?></span></td>
                  <td class="text-sm"><?= $p['last_visit'] ? date('d M Y', strtotime($p['last_visit'])) : '—' ?></td>
                  <td>
                    <?php if($p['allergies']): ?>
                    <span class="badge bg-warning text-dark text-xs" title="<?= htmlspecialchars($p['allergies']) ?>">
                      <i class="fa-solid fa-triangle-exclamation me-1"></i>Allergies
                    </span>
                    <?php else: ?>
                    <span class="text-muted text-xs">None</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="viewPatient(<?= $p['id'] ?>)" title="View Profile"><i class="fa-solid fa-eye"></i></button>
                      <a href="<?= APP_URL ?>/doctor/prescriptions.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="Prescriptions"><i class="fa-solid fa-prescription"></i></a>
                      <button class="btn btn-sm btn-outline-info" onclick="viewHistory(<?= $p['id'] ?>)" title="History"><i class="fa-solid fa-file-medical"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($patients)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted"><i class="fa-solid fa-search fa-2x mb-2 d-block opacity-25"></i>No patients found<?= $search ? ' for "'.$search.'"' : '' ?>.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Patient Detail Modal -->
<div class="modal fade" id="patientModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-user me-2 text-primary"></i>Patient Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="patientModalBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function viewPatient(id) {
  const modal = new bootstrap.Modal(document.getElementById('patientModal'));
  document.getElementById('patientModalBody').innerHTML = '<div class="text-center py-4"><div class="pulse-ring mx-auto mb-2"></div><p class="text-muted">Loading…</p></div>';
  modal.show();
  const res = await HMSAjax.get(APP_URL + '/api/patients.php?id=' + id);
  if (!res.success) { document.getElementById('patientModalBody').innerHTML = '<p class="text-danger text-center">Failed to load.</p>'; return; }
  const p = res.data;
  const age = p.dob ? Math.floor((new Date()-new Date(p.dob))/31557600000) + ' yrs' : '—';
  document.getElementById('patientModalBody').innerHTML = `
    <div class="row g-3">
      <div class="col-md-4 text-center">
        <div class="user-avatar mx-auto mb-2" style="width:72px;height:72px;font-size:24px;font-weight:800">${p.full_name.substring(0,2).toUpperCase()}</div>
        <h6 class="fw-700">${p.full_name}</h6>
        <span class="badge bg-primary">${p.patient_code}</span>
        ${p.blood_group ? '<br><span class="badge bg-danger mt-1">'+p.blood_group+'</span>' : ''}
      </div>
      <div class="col-md-8">
        <div class="row g-2 text-sm">
          <div class="col-6"><strong>Email:</strong><div class="text-muted">${p.email}</div></div>
          <div class="col-6"><strong>Phone:</strong><div class="text-muted">${p.phone||'—'}</div></div>
          <div class="col-6"><strong>Age:</strong><div class="text-muted">${age}</div></div>
          <div class="col-6"><strong>Gender:</strong><div class="text-muted">${p.gender}</div></div>
          <div class="col-6"><strong>City:</strong><div class="text-muted">${p.city||'—'}</div></div>
          <div class="col-6"><strong>Insurance:</strong><div class="text-muted">${p.insurance_provider||'None'}</div></div>
        </div>
        ${p.allergies ? '<div class="alert alert-warning py-2 mt-2 text-sm"><i class="fa-solid fa-triangle-exclamation me-1"></i><strong>Allergies:</strong> '+p.allergies+'</div>' : ''}
        ${p.chronic_diseases ? '<div class="alert alert-info py-2 text-sm"><i class="fa-solid fa-notes-medical me-1"></i><strong>Chronic:</strong> '+p.chronic_diseases+'</div>' : ''}
      </div>
      <div class="col-12">
        <h6 class="fw-700">Recent Appointments</h6>
        <div class="table-responsive"><table class="table table-sm">
          <thead><tr><th>Appt No</th><th>Date</th><th>Status</th></tr></thead>
          <tbody>${(res.appointments||[]).slice(0,5).map(a=>`<tr><td class="text-mono text-primary fw-600 text-xs">${a.appointment_no}</td><td>${a.appointment_date}</td><td><span class="status-badge status-${a.status}">${a.status}</span></td></tr>`).join('')||'<tr><td colspan="3" class="text-center text-muted">No appointments</td></tr>'}</tbody>
        </table></div>
      </div>
    </div>`;
}

async function viewHistory(id) {
  HMS.toast('Loading medical history…', 'info');
  await viewPatient(id);
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
