<?php
// ============================================================
// patient/appointments.php — Patient Appointment Booking
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('patient');

$pageTitle = 'My Appointments';
$patient   = Database::fetchOne("SELECT id FROM patients WHERE user_id=?", [Auth::id()]);
if (!$patient) redirect(APP_URL.'/login.php');
$pid = $patient['id'];

// Stats
$upcoming   = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled','completed')",[$pid])['c'];
$completed  = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND status='completed'",[$pid])['c'];
$cancelled  = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND status='cancelled'",[$pid])['c'];

// All appointments
$appointments = Database::fetchAll("
    SELECT a.id, a.appointment_no, a.appointment_date, a.appointment_time,
           a.token_number, a.type, a.status, a.symptoms, a.payment_status,
           u.full_name AS doctor_name, d.specialization, d.consultation_fee,
           dep.name AS dept_name, dep.color AS dept_color
    FROM appointments a
    JOIN doctors     d   ON d.id=a.doctor_id   JOIN users u ON u.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE a.patient_id=?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$pid]);

// For booking form
$departments = Database::fetchAll("SELECT id, name, icon, color FROM departments WHERE status=1 ORDER BY name");
$doctors     = Database::fetchAll("
    SELECT d.id, u.full_name, d.specialization, d.consultation_fee,
           d.available_days, d.time_from, d.time_to, d.rating, d.department_id
    FROM doctors d JOIN users u ON u.id=d.user_id
    WHERE d.status='available' ORDER BY u.full_name
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
          <h1><i class="fa-solid fa-calendar-check me-2 text-primary"></i>My Appointments</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/patient/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Appointments</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#bookModal">
          <i class="fa-solid fa-plus me-2"></i>Book Appointment
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-clock"></i></div><div><div class="stat-value" data-counter="<?= $upcoming ?>">0</div><div class="stat-label">Upcoming</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $completed ?>">0</div><div class="stat-label">Completed</div></div></div></div>
        <div class="col-6 col-md-4"><div class="stat-card card-red hover-lift"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div><div class="stat-value" data-counter="<?= $cancelled ?>">0</div><div class="stat-label">Cancelled</div></div></div></div>
      </div>

      <!-- Upcoming appointments highlight -->
      <?php $upcomingList = array_filter($appointments, fn($a) => strtotime($a['appointment_date']) >= strtotime('today') && !in_array($a['status'],['cancelled','completed'])); ?>
      <?php if (!empty($upcomingList)): ?>
      <div class="row g-3 mb-4">
        <?php foreach (array_slice($upcomingList, 0, 3) as $i => $apt): ?>
        <div class="col-xl-4 col-md-6">
          <div class="card hover-lift animate-scale-in delay-<?= $i+1 ?>" style="border-top:3px solid <?= htmlspecialchars($apt['dept_color']) ?>">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <div class="fw-800 text-primary" style="font-size:22px">#<?= $apt['token_number'] ?></div>
                  <div class="text-xs text-muted">Your Token</div>
                </div>
                <span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span>
              </div>
              <div class="fw-700 mb-1"><?= htmlspecialchars($apt['doctor_name']) ?></div>
              <div class="text-muted text-xs mb-2"><?= htmlspecialchars($apt['specialization']) ?> · <?= htmlspecialchars($apt['dept_name']) ?></div>
              <div class="d-flex gap-3 text-sm mb-3">
                <span><i class="fa-solid fa-calendar me-1 text-primary"></i><?= date('d M Y', strtotime($apt['appointment_date'])) ?></span>
                <span><i class="fa-solid fa-clock me-1 text-primary"></i><?= date('h:i A', strtotime($apt['appointment_time'])) ?></span>
              </div>
              <div class="d-flex gap-2">
                <?php if(!in_array($apt['status'],['completed','cancelled','in_progress'])): ?>
                <button class="btn btn-sm btn-outline-danger flex-1" onclick="cancelAppt(<?= $apt['id'] ?>)"><i class="fa-solid fa-xmark me-1"></i>Cancel</button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-primary flex-1" onclick="HMS.toast('Reschedule coming soon.','info')"><i class="fa-solid fa-calendar-pen me-1"></i>Reschedule</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- All Appointments Table -->
      <div class="card animate-fade-in">
        <div class="card-header"><i class="fa-solid fa-history me-2 text-primary"></i>Appointment History</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="apptTable">
              <thead><tr><th>Appt No</th><th>Doctor</th><th>Department</th><th>Date & Time</th><th>Token</th><th>Type</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($appointments as $apt): ?>
                <tr>
                  <td><span class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($apt['appointment_no']) ?></span></td>
                  <td><div class="fw-600 text-sm"><?= htmlspecialchars($apt['doctor_name']) ?></div><div class="text-muted text-xs"><?= htmlspecialchars($apt['specialization']) ?></div></td>
                  <td class="text-sm"><?= htmlspecialchars($apt['dept_name']) ?></td>
                  <td><div><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div><div class="text-muted text-xs"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div></td>
                  <td><span class="badge grad-primary">#<?= $apt['token_number'] ?></span></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($apt['type']) ?></span></td>
                  <td><span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span></td>
                  <td><span class="badge <?= $apt['payment_status']==='paid'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($apt['payment_status']) ?></span></td>
                  <td>
                    <?php if(!in_array($apt['status'],['completed','cancelled'])): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelAppt(<?= $apt['id'] ?>)"><i class="fa-solid fa-xmark"></i></button>
                    <?php else: ?>
                    <span class="text-muted text-xs">—</span>
                    <?php endif; ?>
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

<!-- Book Appointment Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i>Book New Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Select Department *</label>
            <select id="bkDept" class="form-select" onchange="filterDoctors()">
              <option value="">Choose department</option>
              <?php foreach ($departments as $dep): ?>
              <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Select Doctor *</label>
            <select id="bkDoctor" class="form-select" onchange="onDoctorSelect()">
              <option value="">Select department first</option>
              <?php foreach ($doctors as $doc): ?>
              <option value="<?= $doc['id'] ?>" data-dept="<?= $doc['department_id'] ?>"
                      data-fee="<?= $doc['consultation_fee'] ?>"
                      data-days="<?= htmlspecialchars($doc['available_days']) ?>">
                <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?> (₹<?= number_format($doc['consultation_fee'],0) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="doctorInfo" style="display:none" class="col-12">
            <div class="alert alert-info py-2 d-flex align-items-center gap-3">
              <i class="fa-solid fa-circle-info"></i>
              <div id="doctorInfoText" class="text-sm"></div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Preferred Date *</label>
            <input type="date" id="bkDate" class="form-control" min="<?= date('Y-m-d') ?>" onchange="generateSlots()"/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Time Slot *</label>
            <select id="bkTime" class="form-select">
              <option value="">Select date first</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Appointment Type</label>
            <select id="bkType" class="form-select">
              <option value="opd">OPD (Out-patient)</option>
              <option value="teleconsult">Teleconsult (Online)</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Consultation Fee</label>
            <input type="text" id="bkFee" class="form-control" readonly placeholder="Select doctor"/>
          </div>
          <div class="col-12">
            <label class="form-label">Symptoms / Reason for Visit</label>
            <textarea id="bkSymptoms" class="form-control" rows="3" placeholder="Describe your symptoms or reason for the appointment…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitBooking()"><i class="fa-solid fa-calendar-check me-2"></i>Confirm Booking</button>
      </div>
    </div>
  </div>
</div>

<?php
$patId = $pid;
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

function filterDoctors() {
  const deptId = document.getElementById('bkDept').value;
  const sel    = document.getElementById('bkDoctor');
  sel.innerHTML = '<option value="">Select doctor</option>';
  document.querySelectorAll('#bkDoctor option[data-dept]').forEach(() => {});
  // Re-populate from full list
  const allOptions = {$_doctorsJson};
  allOptions.forEach(d => {
    if (!deptId || d.department_id == deptId) {
      const opt = document.createElement('option');
      opt.value = d.id;
      opt.dataset.fee = d.consultation_fee;
      opt.dataset.days = d.available_days;
      opt.dataset.dept = d.department_id;
      opt.textContent = d.full_name + ' — ' + d.specialization + ' (₹' + d.consultation_fee + ')';
      sel.appendChild(opt);
    }
  });
}

function onDoctorSelect() {
  const sel = document.getElementById('bkDoctor');
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) { document.getElementById('doctorInfo').style.display = 'none'; return; }
  document.getElementById('bkFee').value = opt.dataset.fee ? '₹'+parseFloat(opt.dataset.fee).toFixed(0) : '';
  document.getElementById('doctorInfoText').textContent = 'Available: '+opt.dataset.days+' · Consultation fee: ₹'+opt.dataset.fee;
  document.getElementById('doctorInfo').style.display = '';
  generateSlots();
}

function generateSlots() {
  const date  = document.getElementById('bkDate').value;
  const docId = document.getElementById('bkDoctor').value;
  const sel   = document.getElementById('bkTime');
  if (!date || !docId) { sel.innerHTML = '<option value="">Select date & doctor</option>'; return; }
  const slots = [];
  for (let h=9;h<17;h++) { ['00','30'].forEach(m => slots.push(h+':'+m)); }
  sel.innerHTML = slots.map(s => {
    const [hr,mn]=s.split(':'); const h=parseInt(hr)%12||12; const ap=parseInt(hr)<12?'AM':'PM';
    return '<option value="'+s+'">'+h+':'+mn+' '+ap+'</option>';
  }).join('');
}

async function submitBooking() {
  const deptId  = document.getElementById('bkDept').value;
  const docId   = document.getElementById('bkDoctor').value;
  const date    = document.getElementById('bkDate').value;
  const time    = document.getElementById('bkTime').value;
  const symptoms= document.getElementById('bkSymptoms').value;

  if (!deptId||!docId||!date||!time) { HMS.toast('Please fill all required fields.','warning'); return; }

  const res = await HMSAjax.post(APP_URL+'/api/appointments.php', {
    patient_id: '{$patId}',
    doctor_id: docId, department_id: deptId,
    appointment_date: date, appointment_time: time,
    type: document.getElementById('bkType').value,
    symptoms
  });
  if (res.success) {
    HMS.toast('Appointment booked! Token #'+res.token,'success');
    bootstrap.Modal.getInstance(document.getElementById('bookModal')).hide();
    setTimeout(()=>location.reload(),900);
  }
}

async function cancelAppt(id) {
  HMS.confirm('Cancel this appointment?', async () => {
    const res = await HMSAjax.del(APP_URL+'/api/appointments.php?id='+id);
    if (res.success) { HMS.toast('Appointment cancelled.','warning'); setTimeout(()=>location.reload(),700); }
  });
}
JS;

// Build doctors JSON for JS filtering
$_doctorsJson = json_encode(array_map(fn($d) => [
    'id' => $d['id'],
    'full_name' => $d['full_name'],
    'specialization' => $d['specialization'],
    'consultation_fee' => $d['consultation_fee'],
    'available_days' => $d['available_days'],
    'department_id' => $d['department_id'],
], $doctors));

// Inject into inline script
$inlineScript = str_replace('{$_doctorsJson}', $_doctorsJson, $inlineScript);
$inlineScript = str_replace('{$patId}', $patId, $inlineScript);

require_once __DIR__ . '/../includes/footer.php';
?>
