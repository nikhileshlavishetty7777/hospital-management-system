<?php
// ============================================================
// admin/appointments.php — Appointment Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['admin','receptionist']);

$pageTitle = 'Appointment Management';

// Filters
$filterDate   = clean($_GET['date']   ?? date('Y-m-d'));
$filterDoctor = (int)($_GET['doctor'] ?? 0);
$filterStatus = clean($_GET['status'] ?? '');

$where  = ['1=1'];
$params = [];

if ($filterDate)   { $where[] = 'a.appointment_date=?';  $params[] = $filterDate; }
if ($filterDoctor) { $where[] = 'a.doctor_id=?';          $params[] = $filterDoctor; }
if ($filterStatus) { $where[] = 'a.status=?';             $params[] = $filterStatus; }

$ws = implode(' AND ', $where);

$appointments = Database::fetchAll("
    SELECT a.id, a.appointment_no, a.appointment_date, a.appointment_time,
           a.token_number, a.type, a.status, a.symptoms, a.payment_status,
           u_p.full_name AS patient_name, p.patient_code, p.id AS patient_id,
           u_d.full_name AS doctor_name,  d.id AS doctor_id,
           dep.name AS dept_name
    FROM appointments a
    JOIN patients p    ON p.id=a.patient_id
    JOIN users    u_p  ON u_p.id=p.user_id
    JOIN doctors  d    ON d.id=a.doctor_id
    JOIN users    u_d  ON u_d.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE {$ws}
    ORDER BY a.appointment_time ASC
", $params);

// Doctors for dropdown
$doctors = Database::fetchAll("
    SELECT d.id, u.full_name, d.specialization, d.status
    FROM doctors d JOIN users u ON u.id=d.user_id
    ORDER BY u.full_name
");

// Departments
$departments = Database::fetchAll("SELECT id, name FROM departments WHERE status=1 ORDER BY name");

// Stats for today
$todayTotal     = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE()")['c'];
$todayCompleted = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE() AND status='completed'")['c'];
$todayPending   = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE() AND status IN ('booked','confirmed','waiting')")['c'];
$todayInProgress= Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE() AND status='in_progress'")['c'];

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Appointments</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Appointments</li>
            </ol>
          </nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#bookApptModal">
          <i class="fa-solid fa-plus me-2"></i>Book Appointment
        </button>
      </div>

      <!-- Today's counters -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayTotal ?>">0</div><div class="stat-label">Today Total</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayPending ?>">0</div><div class="stat-label">Pending</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayInProgress ?>">0</div><div class="stat-label">In Progress</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayCompleted ?>">0</div><div class="stat-label">Completed</div></div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="card mb-4 animate-fade-in">
        <div class="card-body py-3">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>"/>
            </div>
            <div class="col-md-3">
              <label class="form-label">Doctor</label>
              <select name="doctor" class="form-select">
                <option value="">All Doctors</option>
                <?php foreach ($doctors as $doc): ?>
                <option value="<?= $doc['id'] ?>" <?= $filterDoctor==$doc['id']?'selected':'' ?>>
                  <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="">All Status</option>
                <?php foreach (['booked','confirmed','waiting','in_progress','completed','cancelled','no_show'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Filter</button>
              <a href="<?= APP_URL ?>/admin/appointments.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-right me-1"></i>Reset</a>
            </div>
          </form>
        </div>
      </div>

      <!-- Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>Appointments
            <span class="badge bg-primary ms-2"><?= count($appointments) ?></span>
          </span>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="HMS.toast('Printing…','info')">
              <i class="fa-solid fa-print me-1"></i>Print
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="apptTable">
              <thead>
                <tr>
                  <th>Token</th>
                  <th>Appt No</th>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Dept</th>
                  <th>Time</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($appointments as $apt): ?>
                <tr>
                  <td>
                    <span class="badge grad-primary fw-700" style="font-size:14px">#<?= $apt['token_number'] ?></span>
                  </td>
                  <td><span class="text-mono text-primary fw-600 text-xs"><?= htmlspecialchars($apt['appointment_no']) ?></span></td>
                  <td>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($apt['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($apt['patient_code']) ?></div>
                  </td>
                  <td>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($apt['doctor_name']) ?></div>
                  </td>
                  <td class="text-sm"><?= htmlspecialchars($apt['dept_name']) ?></td>
                  <td>
                    <div class="fw-600"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                    <div class="text-muted text-xs"><?= date('d M', strtotime($apt['appointment_date'])) ?></div>
                  </td>
                  <td><span class="badge bg-secondary"><?= strtoupper($apt['type']) ?></span></td>
                  <td><span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span></td>
                  <td>
                    <span class="badge <?= $apt['payment_status']==='paid'?'bg-success':'bg-warning text-dark' ?>">
                      <?= ucfirst($apt['payment_status']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if(in_array($apt['status'], ['booked','confirmed'])): ?>
                      <button class="btn btn-sm btn-outline-success" title="Check In" onclick="updateStatus(<?= $apt['id'] ?>,'waiting')">
                        <i class="fa-solid fa-user-check"></i>
                      </button>
                      <?php endif; ?>
                      <?php if(in_array($apt['status'], ['waiting','confirmed'])): ?>
                      <button class="btn btn-sm btn-outline-primary" title="Start" onclick="updateStatus(<?= $apt['id'] ?>,'in_progress')">
                        <i class="fa-solid fa-play"></i>
                      </button>
                      <?php endif; ?>
                      <?php if($apt['status']==='in_progress'): ?>
                      <button class="btn btn-sm btn-outline-success" title="Complete" onclick="updateStatus(<?= $apt['id'] ?>,'completed')">
                        <i class="fa-solid fa-check"></i>
                      </button>
                      <?php endif; ?>
                      <?php if(!in_array($apt['status'], ['completed','cancelled'])): ?>
                      <button class="btn btn-sm btn-outline-danger" title="Cancel" onclick="cancelAppt(<?= $apt['id'] ?>)">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($appointments)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No appointments found for selected filters.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- ── Book Appointment Modal ── -->
<div class="modal fade" id="bookApptModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Book Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <input type="text" id="apptPatientSearch" class="form-control" placeholder="Search patient…"/>
            <input type="hidden" id="apptPatientId"/>
            <div id="apptPatientResults"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Department *</label>
            <select id="apptDept" class="form-select" onchange="loadDeptDoctors()">
              <option value="">Select Department</option>
              <?php foreach ($departments as $dep): ?>
              <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Doctor *</label>
            <select id="apptDoctor" class="form-select" onchange="loadSlots()">
              <option value="">Select Doctor</option>
              <?php foreach ($doctors as $doc): ?>
              <option value="<?= $doc['id'] ?>" data-dept="" data-spec="<?= htmlspecialchars($doc['specialization']) ?>">
                <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date *</label>
            <input type="date" id="apptDate" class="form-control" min="<?= date('Y-m-d') ?>" onchange="loadSlots()"/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Time Slot *</label>
            <select id="apptTime" class="form-select">
              <option value="">Select doctor and date first</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select id="apptType" class="form-select">
              <option value="opd">OPD</option>
              <option value="ipd">IPD</option>
              <option value="emergency">Emergency</option>
              <option value="teleconsult">Teleconsult</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fee (₹)</label>
            <input type="text" id="apptFee" class="form-control" readonly placeholder="Auto-filled"/>
          </div>
          <div class="col-12">
            <label class="form-label">Symptoms / Reason</label>
            <textarea id="apptSymptoms" class="form-control" rows="2" placeholder="Brief description of symptoms…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitAppt()"><i class="fa-solid fa-calendar-check me-2"></i>Confirm Booking</button>
      </div>
    </div>
  </div>
</div>

<?php
$doctorsJson = json_encode(array_map(fn($d) => [
    'id' => $d['id'],
    'name' => $d['full_name'],
    'specialization' => $d['specialization'],
    'fee' => 0,
], $doctors));

$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();

  HMSAjax.liveSearch('#apptPatientSearch','apptPatientResults',
    APP_URL + '/ajax/search_patient.php',
    items => '<div class="border rounded overflow-hidden">' + items.map(p =>
      '<div class="p-2 border-bottom cursor-pointer hover-bg text-sm" onclick="selectApptPatient(' + p.id + ',\'' + p.full_name.replace("'","\'") + '\')">' +
      '<strong>' + p.full_name + '</strong> <small class=text-muted>' + p.patient_code + '</small></div>'
    ).join('') + '</div>'
  );
});

function selectApptPatient(id, name) {
  document.getElementById('apptPatientId').value = id;
  document.getElementById('apptPatientSearch').value = name;
  document.getElementById('apptPatientResults').innerHTML = '';
}

function loadSlots() {
  const doctorId = document.getElementById('apptDoctor').value;
  const date     = document.getElementById('apptDate').value;
  const sel      = document.getElementById('apptTime');
  if (!doctorId || !date) { sel.innerHTML = '<option>Select doctor and date first</option>'; return; }

  // Generate 30-min slots 9am-5pm
  const times = [];
  for (let h = 9; h < 17; h++) {
    times.push(String(h).padStart(2,'0') + ':00');
    times.push(String(h).padStart(2,'0') + ':30');
  }
  sel.innerHTML = times.map(t => `<option value="${t}">${formatTime(t)}</option>`).join('');
}

function formatTime(t) {
  const [h,m] = t.split(':');
  const hr  = h % 12 || 12;
  const ampm = h < 12 ? 'AM' : 'PM';
  return hr + ':' + m + ' ' + ampm;
}

async function submitAppt() {
  const pid     = document.getElementById('apptPatientId').value;
  const docId   = document.getElementById('apptDoctor').value;
  const deptId  = document.getElementById('apptDept').value;
  const date    = document.getElementById('apptDate').value;
  const time    = document.getElementById('apptTime').value;
  const type    = document.getElementById('apptType').value;
  const symptoms= document.getElementById('apptSymptoms').value;

  if (!pid || !docId || !deptId || !date || !time) {
    HMS.toast('Please fill all required fields.', 'warning'); return;
  }

  const res = await HMSAjax.post(APP_URL + '/api/appointments.php', {
    patient_id: pid, doctor_id: docId, department_id: deptId,
    appointment_date: date, appointment_time: time, type, symptoms
  });

  if (res.success) {
    HMS.toast('Appointment booked! Token #' + res.token, 'success');
    bootstrap.Modal.getInstance(document.getElementById('bookApptModal')).hide();
    setTimeout(() => location.reload(), 900);
  }
}

async function updateStatus(id, status) {
  const labels = { waiting:'checked in', in_progress:'started', completed:'completed', cancelled:'cancelled' };
  HMS.confirm('Mark appointment as ' + (labels[status] || status) + '?', async () => {
    const res = await HMSAjax.put(APP_URL + '/api/appointments.php?id=' + id, { status });
    if (res.success) { HMS.toast('Status updated!','success'); setTimeout(() => location.reload(), 700); }
  });
}

async function cancelAppt(id) {
  HMS.confirm('Cancel this appointment?', async () => {
    const res = await HMSAjax.del(APP_URL + '/api/appointments.php?id=' + id);
    if (res.success) { HMS.toast('Appointment cancelled','warning'); setTimeout(() => location.reload(), 700); }
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
