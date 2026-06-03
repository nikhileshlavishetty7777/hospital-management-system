<?php
// ============================================================
// receptionist/appointments.php — Book & Manage Appointments
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['receptionist','admin']);

$pageTitle = 'Appointments';

$filterDate = clean($_GET['date'] ?? date('Y-m-d'));

$appointments = Database::fetchAll("
    SELECT a.id, a.appointment_no, a.appointment_date, a.appointment_time,
           a.token_number, a.type, a.status, a.payment_status,
           u_p.full_name AS patient_name, p.patient_code,
           u_d.full_name AS doctor_name,  d.specialization,
           dep.name AS dept_name
    FROM appointments a
    JOIN patients    p   ON p.id=a.patient_id  JOIN users u_p ON u_p.id=p.user_id
    JOIN doctors     d   ON d.id=a.doctor_id   JOIN users u_d ON u_d.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE a.appointment_date=?
    ORDER BY a.token_number
", [$filterDate]);

$stats = Database::fetchOne("
    SELECT COUNT(*) AS total,
           SUM(status IN ('booked','confirmed','waiting')) AS pending,
           SUM(status='completed') AS completed,
           SUM(status='in_progress') AS in_progress
    FROM appointments WHERE appointment_date=?", [$filterDate]);

$doctors     = Database::fetchAll("SELECT d.id, u.full_name, d.specialization, d.consultation_fee FROM doctors d JOIN users u ON u.id=d.user_id WHERE d.status='available' ORDER BY u.full_name");
$departments = Database::fetchAll("SELECT id, name FROM departments WHERE status=1 ORDER BY name");

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
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/receptionist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Appointments</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#bookModal">
          <i class="fa-solid fa-plus me-2"></i>Book Appointment
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><div class="stat-value" data-counter="<?= $stats['total']??0 ?>">0</div><div class="stat-label">Total</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="stat-value" data-counter="<?= $stats['pending']??0 ?>">0</div><div class="stat-label">Pending</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div><div><div class="stat-value" data-counter="<?= $stats['in_progress']??0 ?>">0</div><div class="stat-label">In Progress</div></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="stat-value" data-counter="<?= $stats['completed']??0 ?>">0</div><div class="stat-label">Completed</div></div></div></div>
      </div>

      <!-- Date filter -->
      <div class="card mb-4">
        <div class="card-body py-3">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">Date</label>
              <input type="date" name="date" class="form-control" value="<?= $filterDate ?>"/></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>View</button></div>
            <div class="col-md-2"><a href="?" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-calendar me-1"></i>Today</a></div>
          </form>
        </div>
      </div>

      <!-- Live Token Board -->
      <div class="card mb-4 animate-fade-in">
        <div class="card-header fw-700"><i class="fa-solid fa-display me-2 text-primary"></i>Token Display — <?= date('d M Y',strtotime($filterDate)) ?></div>
        <div class="card-body">
          <?php if(empty($appointments)): ?>
          <div class="text-center py-3 text-muted">No appointments scheduled.</div>
          <?php else: ?>
          <div class="row g-2">
            <?php foreach ($appointments as $i => $apt): ?>
            <div class="col-6 col-md-3 col-lg-2">
              <div class="text-center p-2 rounded hover-lift" style="
                background:<?= $apt['status']==='completed'?'rgba(34,197,94,.12)':($apt['status']==='in_progress'?'rgba(139,92,246,.12)':($apt['status']==='cancelled'?'rgba(239,68,68,.08)':'rgba(14,165,233,.1)')) ?>;
                border:1px solid <?= $apt['status']==='completed'?'rgba(34,197,94,.3)':($apt['status']==='in_progress'?'rgba(139,92,246,.3)':'rgba(14,165,233,.2)') ?>;
                cursor:default">
                <div class="fw-900" style="font-size:24px;color:<?= $apt['status']==='completed'?'var(--success)':($apt['status']==='in_progress'?'var(--secondary)':'var(--primary)') ?>"><?= $apt['token_number'] ?></div>
                <div class="text-xs fw-600 text-truncate"><?= htmlspecialchars(explode(' ',$apt['patient_name'])[0]) ?></div>
                <div class="text-xs text-muted"><?= date('h:i A',strtotime($apt['appointment_time'])) ?></div>
                <span class="status-badge status-<?= $apt['status'] ?>" style="font-size:9px;padding:2px 5px"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Table -->
      <div class="card animate-fade-in">
        <div class="card-header"><i class="fa-solid fa-table me-2 text-primary"></i>Appointment Details</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="apptTable">
              <thead><tr><th>Token</th><th>Appt No</th><th>Patient</th><th>Doctor</th><th>Time</th><th>Type</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($appointments as $apt): ?>
                <tr>
                  <td><span class="badge grad-primary fw-700">#<?= $apt['token_number'] ?></span></td>
                  <td><span class="text-mono fw-600 text-primary text-xs"><?= $apt['appointment_no'] ?></span></td>
                  <td><div class="fw-600"><?= htmlspecialchars($apt['patient_name']) ?></div><div class="text-muted text-xs"><?= $apt['patient_code'] ?></div></td>
                  <td><div class="fw-600 text-sm"><?= htmlspecialchars($apt['doctor_name']) ?></div><div class="text-muted text-xs"><?= $apt['specialization'] ?></div></td>
                  <td class="fw-600"><?= date('h:i A',strtotime($apt['appointment_time'])) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($apt['type']) ?></span></td>
                  <td><span class="status-badge status-<?= $apt['status'] ?>"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span></td>
                  <td><span class="badge <?= $apt['payment_status']==='paid'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($apt['payment_status']) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <?php if(!in_array($apt['status'],['completed','cancelled'])): ?>
                      <button class="btn btn-sm btn-outline-success" onclick="checkIn(<?= $apt['id'] ?>)" title="Check In"><i class="fa-solid fa-user-check"></i></button>
                      <?php endif; ?>
                      <?php if($apt['payment_status']!=='paid'): ?>
                      <a href="<?= APP_URL ?>/receptionist/billing.php?appt_id=<?= $apt['id'] ?>" class="btn btn-sm btn-outline-warning" title="Invoice"><i class="fa-solid fa-receipt"></i></a>
                      <?php endif; ?>
                      <?php if(!in_array($apt['status'],['completed','cancelled'])): ?>
                      <button class="btn btn-sm btn-outline-danger" onclick="cancelAppt(<?= $apt['id'] ?>)" title="Cancel"><i class="fa-solid fa-xmark"></i></button>
                      <?php endif; ?>
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

<!-- Book Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
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
            <input type="text" id="bPatientSearch" class="form-control" placeholder="Search patient…"/>
            <input type="hidden" id="bPatientId"/>
            <div id="bPatientResults"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Department *</label>
            <select id="bDept" class="form-select" onchange="loadDoctors()">
              <option value="">Select</option>
              <?php foreach ($departments as $dep): ?>
              <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Doctor *</label>
            <select id="bDoctor" class="form-select" onchange="showFee()">
              <option value="">Select department first</option>
              <?php foreach ($doctors as $doc): ?>
              <option value="<?= $doc['id'] ?>" data-fee="<?= $doc['consultation_fee'] ?>">
                <?= htmlspecialchars($doc['full_name']) ?> — <?= htmlspecialchars($doc['specialization']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Date *</label>
            <input type="date" id="bDate" class="form-control" min="<?= date('Y-m-d') ?>" onchange="genSlots()"/>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fee (₹)</label>
            <input type="text" id="bFee" class="form-control" readonly/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Time Slot *</label>
            <select id="bTime" class="form-select">
              <option value="">Pick doctor & date</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Type</label>
            <select id="bType" class="form-select">
              <option value="opd">OPD</option>
              <option value="ipd">IPD</option>
              <option value="emergency">Emergency</option>
              <option value="teleconsult">Teleconsult</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Payment</label>
            <select id="bPayment" class="form-select">
              <option value="pending">Collect Later</option>
              <option value="paid">Paid Now</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Symptoms</label>
            <textarea id="bSymptoms" class="form-control" rows="2" placeholder="Chief complaint…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitBook()"><i class="fa-solid fa-calendar-check me-2"></i>Confirm Booking</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSAjax.liveSearch('#bPatientSearch','bPatientResults',APP_URL+'/ajax/search_patient.php',
    items => '<div class="border rounded shadow-sm">' + items.map(p =>
      '<div class="p-2 border-bottom text-sm cursor-pointer" onclick="selectPat('+p.id+',\''+p.full_name.replace(/'/g,"\\'")+'\')">'+
      '<strong>'+p.full_name+'</strong> <small class=text-muted>'+p.patient_code+'</small></div>'
    ).join('')+'</div>'
  );
});

function selectPat(id, name) {
  document.getElementById('bPatientId').value = id;
  document.getElementById('bPatientSearch').value = name;
  document.getElementById('bPatientResults').innerHTML = '';
}

function showFee() {
  const sel = document.getElementById('bDoctor');
  const fee = sel.options[sel.selectedIndex]?.dataset.fee;
  document.getElementById('bFee').value = fee ? '₹'+parseFloat(fee).toFixed(0) : '';
  genSlots();
}

function genSlots() {
  const docId = document.getElementById('bDoctor').value;
  const date  = document.getElementById('bDate').value;
  const sel   = document.getElementById('bTime');
  if (!docId || !date) return;
  const slots = [];
  for (let h=9;h<17;h++) { slots.push(h+':00'); slots.push(h+':30'); }
  sel.innerHTML = slots.map(s => {
    const [hr,mn]=s.split(':'); const hh=hr%12||12; const ap=hr<12?'AM':'PM';
    return '<option value="'+s+'">'+hh+':'+mn+' '+ap+'</option>';
  }).join('');
}

async function submitBook() {
  const pid   = document.getElementById('bPatientId').value;
  const docId = document.getElementById('bDoctor').value;
  const deptId= document.getElementById('bDept').value;
  const date  = document.getElementById('bDate').value;
  const time  = document.getElementById('bTime').value;
  if (!pid||!docId||!deptId||!date||!time) { HMS.toast('Please fill all required fields.','warning'); return; }

  const res = await HMSAjax.post(APP_URL+'/api/appointments.php', {
    patient_id:pid, doctor_id:docId, department_id:deptId,
    appointment_date:date, appointment_time:time,
    type:document.getElementById('bType').value,
    symptoms:document.getElementById('bSymptoms').value
  });
  if (res.success) {
    HMS.toast('Appointment booked! Token #'+res.token,'success');
    bootstrap.Modal.getInstance(document.getElementById('bookModal')).hide();
    setTimeout(()=>location.reload(),800);
  }
}

async function checkIn(id) {
  const res = await HMSAjax.put(APP_URL+'/api/appointments.php?id='+id,{status:'waiting'});
  if (res.success) { HMS.toast('Patient checked in!','success'); setTimeout(()=>location.reload(),700); }
}

async function cancelAppt(id) {
  HMS.confirm('Cancel this appointment?', async () => {
    const res = await HMSAjax.del(APP_URL+'/api/appointments.php?id='+id);
    if (res.success) { HMS.toast('Cancelled.','warning'); setTimeout(()=>location.reload(),700); }
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
