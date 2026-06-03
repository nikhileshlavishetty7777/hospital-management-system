<?php
// ============================================================
// doctor/appointments.php — Doctor's Appointments
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'My Appointments';
$doctor    = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
if (!$doctor) redirect(APP_URL.'/login.php');
$did = $doctor['id'];

// Filters
$filterDate   = clean($_GET['date']   ?? date('Y-m-d'));
$filterStatus = clean($_GET['status'] ?? '');

$where  = ['a.doctor_id=?']; $params = [$did];
if ($filterDate)   { $where[] = 'a.appointment_date=?'; $params[] = $filterDate; }
if ($filterStatus) { $where[] = 'a.status=?';           $params[] = $filterStatus; }
$ws = implode(' AND ', $where);

$appointments = Database::fetchAll("
    SELECT a.id, a.appointment_no, a.appointment_date, a.appointment_time,
           a.token_number, a.type, a.status, a.symptoms, a.payment_status,
           u.full_name AS patient_name, p.patient_code, p.id AS patient_id,
           p.gender, p.blood_group, p.dob, p.allergies
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id
    JOIN users    u ON u.id=p.user_id
    WHERE {$ws}
    ORDER BY a.token_number
", $params);

// Today's stats for selected date
$stats = Database::fetchOne("
    SELECT
        COUNT(*) AS total,
        SUM(status='completed')   AS completed,
        SUM(status='in_progress') AS in_progress,
        SUM(status IN ('booked','confirmed','waiting')) AS pending,
        SUM(status='cancelled')   AS cancelled
    FROM appointments WHERE doctor_id=? AND appointment_date=?
", [$did, $filterDate]);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-calendar-check me-2 text-primary"></i>My Appointments</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/doctor/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Appointments</li>
          </ol></nav>
        </div>
      </div>

      <!-- Date stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><div class="stat-value" data-counter="<?= $stats['total']??0 ?>">0</div><div class="stat-label">Total</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="stat-value" data-counter="<?= $stats['pending']??0 ?>">0</div><div class="stat-label">Pending</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div><div><div class="stat-value" data-counter="<?= $stats['in_progress']??0 ?>">0</div><div class="stat-label">In Progress</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $stats['completed']??0 ?>">0</div><div class="stat-label">Completed</div></div></div></div>
        <div class="col-6 col-md"><div class="stat-card card-red hover-lift"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div><div class="stat-value" data-counter="<?= $stats['cancelled']??0 ?>">0</div><div class="stat-label">Cancelled</div></div></div></div>
      </div>

      <!-- Filters -->
      <div class="card mb-4">
        <div class="card-body py-3">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>"/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach(['booked','confirmed','waiting','in_progress','completed','cancelled','no_show'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-1"><i class="fa-solid fa-filter me-1"></i>Filter</button>
              <a href="?" class="btn btn-outline-secondary flex-1"><i class="fa-solid fa-rotate-right me-1"></i>Today</a>
            </div>
          </form>
        </div>
      </div>

      <!-- Queue Cards (today) -->
      <?php if ($filterDate === date('Y-m-d') && !empty($appointments)): ?>
      <div class="card mb-4">
        <div class="card-header fw-700"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Live Queue — <?= date('d M Y') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach (array_slice($appointments, 0, 6) as $i => $apt): ?>
            <div class="col-xl-4 col-md-6 animate-scale-in delay-<?= $i+1 ?>">
              <div class="card hover-lift" style="border-left:4px solid var(--<?= ['primary','success','warning','info','danger','purple'][$i%6] ?>)">
                <div class="card-body py-3 px-4">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge grad-primary fw-800" style="font-size:18px">#<?= $apt['token_number'] ?></span>
                    <span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span>
                  </div>
                  <div class="fw-700"><?= htmlspecialchars($apt['patient_name']) ?></div>
                  <div class="text-muted text-xs mb-1"><?= htmlspecialchars($apt['patient_code']) ?> · <?= date('h:i A',strtotime($apt['appointment_time'])) ?></div>
                  <?php if($apt['allergies']): ?>
                  <div class="badge bg-warning text-dark text-xs mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>Allergy</div>
                  <?php endif; ?>
                  <?php if($apt['symptoms']): ?>
                  <div class="text-xs text-muted fst-italic mb-2"><?= htmlspecialchars(substr($apt['symptoms'],0,50)) ?>…</div>
                  <?php endif; ?>
                  <div class="d-flex gap-1">
                    <?php if(in_array($apt['status'],['booked','confirmed','waiting'])): ?>
                    <button class="btn btn-sm btn-primary flex-1" onclick="startConsult(<?= $apt['id'] ?>)"><i class="fa-solid fa-stethoscope me-1"></i>Start</button>
                    <?php endif; ?>
                    <?php if($apt['status']==='in_progress'): ?>
                    <button class="btn btn-sm btn-success flex-1" onclick="completeConsult(<?= $apt['id'] ?>, <?= $apt['patient_id'] ?>)"><i class="fa-solid fa-check me-1"></i>Complete</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="prescribePatient(<?= $apt['patient_id'] ?>)"><i class="fa-solid fa-prescription"></i></button>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Appointments on <?= date('d M Y',strtotime($filterDate)) ?>
            <span class="badge bg-primary ms-1"><?= count($appointments) ?></span>
          </span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0">
              <thead><tr><th>Token</th><th>Patient</th><th>Time</th><th>Type</th><th>Symptoms</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($appointments as $apt): ?>
                <tr>
                  <td><span class="badge grad-primary fw-700">#<?= $apt['token_number'] ?></span></td>
                  <td>
                    <div class="fw-600"><?= htmlspecialchars($apt['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($apt['patient_code']) ?>
                      <?php if($apt['blood_group']): ?> · <span class="text-danger fw-600"><?= $apt['blood_group'] ?></span><?php endif; ?>
                    </div>
                  </td>
                  <td class="fw-600"><?= date('h:i A',strtotime($apt['appointment_time'])) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($apt['type']) ?></span></td>
                  <td class="text-muted text-xs"><?= $apt['symptoms'] ? htmlspecialchars(substr($apt['symptoms'],0,40)).'…' : '—' ?></td>
                  <td><span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span></td>
                  <td><span class="badge <?= $apt['payment_status']==='paid'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($apt['payment_status']) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if(in_array($apt['status'],['booked','confirmed','waiting'])): ?>
                      <button class="btn btn-sm btn-outline-primary" onclick="startConsult(<?= $apt['id'] ?>)"><i class="fa-solid fa-play"></i></button>
                      <?php endif; ?>
                      <?php if($apt['status']==='in_progress'): ?>
                      <button class="btn btn-sm btn-outline-success" onclick="completeConsult(<?= $apt['id'] ?>, <?= $apt['patient_id'] ?>)"><i class="fa-solid fa-check"></i></button>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-outline-info" onclick="prescribePatient(<?= $apt['patient_id'] ?>)"><i class="fa-solid fa-prescription"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($appointments)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No appointments for this date/filter.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function startConsult(id) {
  const res = await HMSAjax.put(APP_URL+'/api/appointments.php?id='+id, {status:'in_progress'});
  if (res.success) { HMS.toast('Consultation started!','success'); setTimeout(()=>location.reload(),700); }
}
async function completeConsult(id, patientId) {
  HMS.confirm('Mark consultation as complete?', async () => {
    const res = await HMSAjax.put(APP_URL+'/api/appointments.php?id='+id, {status:'completed'});
    if (res.success) { HMS.toast('Consultation completed!','success'); setTimeout(()=>location.reload(),700); }
  });
}
function prescribePatient(pid) {
  window.location.href = APP_URL+'/doctor/prescriptions.php?patient_id='+pid;
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
