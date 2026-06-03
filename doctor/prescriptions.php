<?php
// ============================================================
// doctor/prescriptions.php — Prescription Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'Prescriptions';
$doctor    = Database::fetchOne("SELECT id FROM doctors WHERE user_id=?", [Auth::id()]);
if (!$doctor) { redirect(APP_URL.'/login.php'); }
$did = $doctor['id'];

// Preload patient if coming from patients page
$prePatientId = (int)($_GET['patient_id'] ?? 0);
$prePatient   = null;
if ($prePatientId) {
    $prePatient = Database::fetchOne("SELECT p.*,u.full_name,u.phone FROM patients p JOIN users u ON u.id=p.user_id WHERE p.id=?",[$prePatientId]);
}

// Stats
$totalRx    = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE doctor_id=?",[$did])['c'];
$activeRx   = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE doctor_id=? AND status='active'",[$did])['c'];
$todayRx    = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE doctor_id=? AND DATE(created_at)=CURDATE()",[$did])['c'];

// List prescriptions
$prescriptions = Database::fetchAll("
    SELECT pr.id, pr.prescription_no, pr.diagnosis, pr.follow_up_date, pr.status, pr.created_at,
           u.full_name AS patient_name, p.patient_code, p.id AS patient_id,
           GROUP_CONCAT(pi.medicine_name ORDER BY pi.id SEPARATOR ', ') AS medicines
    FROM prescriptions pr
    JOIN patients p ON p.id=pr.patient_id
    JOIN users    u ON u.id=p.user_id
    LEFT JOIN prescription_items pi ON pi.prescription_id=pr.id
    WHERE pr.doctor_id=?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
    LIMIT 50
", [$did]);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-prescription me-2 text-primary"></i>Prescriptions</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/doctor/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Prescriptions</li>
          </ol></nav>
        </div>
        <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#createRxModal">
          <i class="fa-solid fa-plus me-2"></i>New Prescription
        </button>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-file-prescription"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalRx ?>">0</div><div class="stat-label">Total Rx</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-value" data-counter="<?= $activeRx ?>">0</div><div class="stat-label">Active</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayRx ?>">0</div><div class="stat-label">Today</div></div>
          </div>
        </div>
      </div>

      <!-- Prescription List -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-list me-2 text-primary"></i>All Prescriptions</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="rxTable">
              <thead>
                <tr><th>#</th><th>Rx No</th><th>Patient</th><th>Diagnosis</th><th>Medicines</th><th>Follow-up</th><th>Status</th><th>Date</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($prescriptions as $i => $rx): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($rx['prescription_no']) ?></span></td>
                  <td>
                    <div class="fw-600"><?= htmlspecialchars($rx['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($rx['patient_code']) ?></div>
                  </td>
                  <td class="text-sm"><?= htmlspecialchars(substr($rx['diagnosis'] ?? 'N/A', 0, 40)) ?><?= strlen($rx['diagnosis'] ?? '') > 40 ? '…' : '' ?></td>
                  <td class="text-sm text-muted"><?= htmlspecialchars(substr($rx['medicines'] ?? 'None', 0, 50)) ?></td>
                  <td class="text-xs"><?= $rx['follow_up_date'] ? date('d M Y', strtotime($rx['follow_up_date'])) : '<span class="text-muted">—</span>' ?></td>
                  <td><span class="status-badge status-<?= $rx['status']==='active'?'completed':($rx['status']==='completed'?'in_progress':'cancelled') ?>"><?= ucfirst($rx['status']) ?></span></td>
                  <td class="text-xs text-muted"><?= date('d M Y', strtotime($rx['created_at'])) ?></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="viewRx(<?= $rx['id'] ?>)" title="View"><i class="fa-solid fa-eye"></i></button>
                      <button class="btn btn-sm btn-outline-success" onclick="printRx(<?= $rx['id'] ?>)" title="Print"><i class="fa-solid fa-print"></i></button>
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

<!-- Create Prescription Modal -->
<div class="modal fade" id="createRxModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-prescription me-2 text-primary"></i>New Prescription</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Patient -->
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <input type="text" id="rxPatientSearch" class="form-control" placeholder="Search patient by name or code…"
              value="<?= $prePatient ? htmlspecialchars($prePatient['full_name']) : '' ?>"/>
            <input type="hidden" id="rxPatientId" value="<?= $prePatientId ?: '' ?>"/>
            <div id="rxPatientResults"></div>
          </div>
          <!-- Appointment -->
          <div class="col-md-6">
            <label class="form-label">Linked Appointment</label>
            <select id="rxApptId" class="form-select">
              <option value="">No appointment</option>
            </select>
          </div>
          <!-- Diagnosis -->
          <div class="col-12">
            <label class="form-label">Diagnosis / Chief Complaint *</label>
            <textarea id="rxDiagnosis" class="form-control" rows="2" placeholder="Primary diagnosis…"></textarea>
          </div>

          <!-- Medicine rows -->
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <label class="form-label mb-0 fw-700">Medicines</label>
              <button class="btn btn-sm btn-outline-primary ripple-btn" onclick="addRxItem()"><i class="fa-solid fa-plus me-1"></i>Add Medicine</button>
            </div>
            <div id="rxItems">
              <!-- rows added by JS -->
            </div>
          </div>

          <!-- Notes + Follow-up -->
          <div class="col-md-8">
            <label class="form-label">Clinical Notes</label>
            <textarea id="rxNotes" class="form-control" rows="2" placeholder="Additional instructions, advice…"></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Follow-up Date</label>
            <input type="date" id="rxFollowUp" class="form-control" min="<?= date('Y-m-d') ?>"/>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-outline-primary me-2" onclick="savePrint()"><i class="fa-solid fa-print me-1"></i>Save & Print</button>
        <button class="btn btn-primary ripple-btn" onclick="submitRx()"><i class="fa-solid fa-floppy-disk me-2"></i>Save Prescription</button>
      </div>
    </div>
  </div>
</div>

<!-- View Prescription Modal -->
<div class="modal fade" id="viewRxModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-prescription me-2 text-primary"></i>Prescription Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewRxBody">
        <div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="printRxBtn"><i class="fa-solid fa-print me-2"></i>Print</button>
      </div>
    </div>
  </div>
</div>
<?php
$doctorName = Auth::user()['name'];
$inlineScript = <<<'JS'
let rxItems = [];
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  addRxItem();

  HMSAjax.liveSearch('#rxPatientSearch','rxPatientResults',
    APP_URL+'/ajax/search_patient.php',
    items => '<div class="border rounded shadow-sm">' + items.map(p =>
      '<div class="p-2 border-bottom cursor-pointer text-sm hover-lift" onclick="selectRxPatient('+p.id+',\''+p.full_name.replace(/'/g,"\\'")+'\')">'+
      '<strong>'+p.full_name+'</strong> <small class=text-muted>'+p.patient_code+' · '+p.phone+'</small></div>'
    ).join('')+'</div>'
  );

  // Pre-select patient if coming from patients page
  const preId = document.getElementById('rxPatientId').value;
  if (preId) loadPatientAppts(preId);
});

function selectRxPatient(id, name) {
  document.getElementById('rxPatientId').value = id;
  document.getElementById('rxPatientSearch').value = name;
  document.getElementById('rxPatientResults').innerHTML = '';
  loadPatientAppts(id);
}

async function loadPatientAppts(pid) {
  const res = await HMSAjax.get(APP_URL+'/api/appointments.php?patient_id='+pid+'&status=completed&limit=10');
  const sel = document.getElementById('rxApptId');
  sel.innerHTML = '<option value="">No appointment</option>';
  (res.data||[]).forEach(a => {
    sel.innerHTML += '<option value="'+a.id+'">'+a.appointment_no+' — '+a.appointment_date+'</option>';
  });
}

function addRxItem() {
  rxItems.push({ medicine:'', dosage:'', frequency:'', duration:'', instructions:'' });
  renderRxItems();
}

function removeRxItem(i) { rxItems.splice(i,1); renderRxItems(); }

function renderRxItems() {
  const freqs = ['Once daily','Twice daily','Thrice daily','Four times daily','As needed','Every 6 hours','Every 8 hours','Weekly'];
  document.getElementById('rxItems').innerHTML = rxItems.length === 0
    ? '<p class="text-muted text-sm text-center py-2">Click "Add Medicine" to start</p>'
    : rxItems.map((item,i) => `
    <div class="card mb-2" style="background:var(--bg)">
      <div class="card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label text-xs">Medicine Name *</label>
            <input type="text" class="form-control form-control-sm" value="${item.medicine}" placeholder="Medicine name…"
              oninput="rxItems[${i}].medicine=this.value"/>
          </div>
          <div class="col-md-2">
            <label class="form-label text-xs">Dosage</label>
            <input type="text" class="form-control form-control-sm" value="${item.dosage}" placeholder="e.g. 500mg"
              oninput="rxItems[${i}].dosage=this.value"/>
          </div>
          <div class="col-md-2">
            <label class="form-label text-xs">Frequency</label>
            <select class="form-select form-select-sm" onchange="rxItems[${i}].frequency=this.value">
              <option value="">Select</option>
              ${freqs.map(f=>'<option value="'+f+'" '+(item.frequency===f?'selected':'')+'>'+f+'</option>').join('')}
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label text-xs">Duration</label>
            <input type="text" class="form-control form-control-sm" value="${item.duration}" placeholder="e.g. 7 days"
              oninput="rxItems[${i}].duration=this.value"/>
          </div>
          <div class="col-md-2">
            <label class="form-label text-xs">Instructions</label>
            <input type="text" class="form-control form-control-sm" value="${item.instructions}" placeholder="Before/after food"
              oninput="rxItems[${i}].instructions=this.value"/>
          </div>
          <div class="col-md-1">
            <button class="btn btn-sm btn-outline-danger w-100" onclick="removeRxItem(${i})"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      </div>
    </div>`).join('');
}

async function submitRx(andPrint=false) {
  const pid      = document.getElementById('rxPatientId').value;
  const diagnosis= document.getElementById('rxDiagnosis').value.trim();
  const apptId   = document.getElementById('rxApptId').value;
  const notes    = document.getElementById('rxNotes').value;
  const followUp = document.getElementById('rxFollowUp').value;

  if (!pid)       { HMS.toast('Please select a patient.','warning'); return; }
  if (!diagnosis) { HMS.toast('Diagnosis is required.','warning'); return; }
  const validItems = rxItems.filter(i => i.medicine.trim());
  if (!validItems.length) { HMS.toast('Add at least one medicine.','warning'); return; }

  const payload = {
    patient_id: pid, doctor_id: '{$did}',
    appointment_id: apptId||null, diagnosis, notes, follow_up_date: followUp||null,
    medicines: validItems
  };

  const res = await HMSAjax.post(APP_URL+'/ajax/appointments.php?action=create_rx', payload);
  if (res.success) {
    HMS.toast('Prescription saved: '+res.prescription_no,'success');
    bootstrap.Modal.getInstance(document.getElementById('createRxModal')).hide();
    if (andPrint) printRxDirect(res.prescription_id);
    setTimeout(() => location.reload(), 900);
  }
}

function savePrint() { submitRx(true); }

async function viewRx(id) {
  const modal = new bootstrap.Modal(document.getElementById('viewRxModal'));
  document.getElementById('viewRxBody').innerHTML = '<div class="text-center py-4"><div class="pulse-ring mx-auto"></div></div>';
  modal.show();
  const res = await HMSAjax.get(APP_URL+'/api/patients.php?action=prescription&id='+id);
  document.getElementById('viewRxBody').innerHTML = '<div class="text-center py-3 text-muted">Prescription viewer loaded for ID: '+id+'</div>';
  document.getElementById('printRxBtn').onclick = () => printRx(id);
}

function printRx(id) {
  HMS.toast('Opening print view…','info');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
