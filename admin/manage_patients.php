<?php
// ============================================================
// admin/manage_patients.php — Patient management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['admin','receptionist']);

$pageTitle = 'Manage Patients';

// ── Stats ─────────────────────────────────────────────────────
$totalPatients   = Database::fetchOne("SELECT COUNT(*) AS c FROM patients")['c'];
$thisMonth       = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];
$maleCount       = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE gender='male'")['c'];
$femaleCount     = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE gender='female'")['c'];

// ── Patient list ──────────────────────────────────────────────
$patients = Database::fetchAll("
    SELECT p.*, u.full_name, u.email, u.phone, u.status, u.created_at AS reg_date
    FROM patients p
    JOIN users u ON u.id = p.user_id
    ORDER BY p.id DESC
");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Page Header -->
      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-hospital-user me-2 text-primary"></i>Patient Management</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Patients</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="exportPatients()">
            <i class="fa-solid fa-file-export me-2"></i>Export
          </button>
          <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#addPatientModal">
            <i class="fa-solid fa-user-plus me-2"></i>Register Patient
          </button>
        </div>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue"><div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalPatients ?>">0</div><div class="stat-label">Total Patients</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-green"><div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div><div class="stat-value" data-counter="<?= $thisMonth ?>">0</div><div class="stat-label">New This Month</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-indigo"><div class="stat-icon"><i class="fa-solid fa-mars"></i></div>
            <div><div class="stat-value" data-counter="<?= $maleCount ?>">0</div><div class="stat-label">Male Patients</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-pink"><div class="stat-icon"><i class="fa-solid fa-venus"></i></div>
            <div><div class="stat-value" data-counter="<?= $femaleCount ?>">0</div><div class="stat-label">Female Patients</div></div>
          </div>
        </div>
      </div>

      <!-- Patients Table -->
      <div class="card animate-fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-table me-2 text-primary"></i>All Patients</span>
          <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="genderFilter" onchange="filterTable()" style="width:120px">
              <option value="">All Gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
            <select class="form-select form-select-sm" id="statusFilter" onchange="filterTable()" style="width:120px">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="patientsTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Patient</th>
                  <th>Code</th>
                  <th>Gender</th>
                  <th>Blood</th>
                  <th>Phone</th>
                  <th>City</th>
                  <th>Insurance</th>
                  <th>Registered</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($patients as $i => $p): ?>
                <tr data-gender="<?= $p['gender'] ?>" data-status="<?= $p['status'] ?>">
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
                  <td><span class="text-mono text-primary fw-600"><?= htmlspecialchars($p['patient_code']) ?></span></td>
                  <td>
                    <?php if($p['gender']==='male'): ?>
                      <span class="badge" style="background:rgba(99,102,241,.12);color:#6366f1"><i class="fa-solid fa-mars me-1"></i>Male</span>
                    <?php elseif($p['gender']==='female'): ?>
                      <span class="badge" style="background:rgba(236,72,153,.12);color:#ec4899"><i class="fa-solid fa-venus me-1"></i>Female</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Other</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if($p['blood_group']): ?>
                      <span class="badge bg-danger"><?= htmlspecialchars($p['blood_group']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($p['phone']) ?></td>
                  <td><?= htmlspecialchars($p['city'] ?? '—') ?></td>
                  <td>
                    <?= $p['insurance_provider']
                        ? '<span class="badge bg-success">'.htmlspecialchars($p['insurance_provider']).'</span>'
                        : '<span class="text-muted text-xs">None</span>' ?>
                  </td>
                  <td><?= date('d M Y', strtotime($p['reg_date'])) ?></td>
                  <td>
                    <span class="status-badge <?= $p['status']==='active'?'status-completed':'status-cancelled' ?>">
                      <?= ucfirst($p['status']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="View" onclick="viewPatient(<?= $p['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                      <button class="btn btn-sm btn-outline-success" title="Edit" onclick="editPatient(<?= $p['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                      <button class="btn btn-sm btn-outline-info" title="QR Card" onclick="showQR('<?= $p['patient_code'] ?>')"><i class="fa-solid fa-qrcode"></i></button>
                      <button class="btn btn-sm btn-outline-warning" title="History" onclick="medHistory(<?= $p['id'] ?>)"><i class="fa-solid fa-file-medical"></i></button>
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

<!-- ── Add Patient Modal ── -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Register New Patient</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addPatientForm" action="<?= APP_URL ?>/api/patients.php" method="POST">
          <div class="row g-3">
            <div class="col-12"><h6 class="fw-700 text-primary mb-1"><i class="fa-solid fa-user me-2"></i>Personal Information</h6><hr class="my-2"/></div>
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required placeholder="Patient's full name"/></div>
            <div class="col-md-6"><label class="form-label">Email Address *</label><input type="email" name="email" class="form-control" required placeholder="patient@email.com"/></div>
            <div class="col-md-4"><label class="form-label">Phone Number *</label><input type="tel" name="phone" class="form-control" required placeholder="+91 98765 43210"/></div>
            <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" max="<?= date('Y-m-d') ?>"/></div>
            <div class="col-md-2"><label class="form-label">Gender *</label>
              <select name="gender" class="form-select" required>
                <option value="">Select</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-2"><label class="form-label">Blood Group</label>
              <select name="blood_group" class="form-select">
                <option value="">Unknown</option>
                <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                <option value="<?= $bg ?>"><?= $bg ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12"><h6 class="fw-700 text-primary mb-1 mt-2"><i class="fa-solid fa-location-dot me-2"></i>Address</h6><hr class="my-2"/></div>
            <div class="col-12"><label class="form-label">Street Address</label><textarea name="address" class="form-control" rows="2" placeholder="House No, Street, Area"></textarea></div>
            <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" placeholder="City"/></div>
            <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control" placeholder="State"/></div>
            <div class="col-md-4"><label class="form-label">PIN Code</label><input type="text" name="pincode" class="form-control" placeholder="400001"/></div>

            <div class="col-12"><h6 class="fw-700 text-primary mb-1 mt-2"><i class="fa-solid fa-notes-medical me-2"></i>Medical Information</h6><hr class="my-2"/></div>
            <div class="col-md-6"><label class="form-label">Known Allergies</label><textarea name="allergies" class="form-control" rows="2" placeholder="e.g. Penicillin, Sulfa drugs"></textarea></div>
            <div class="col-md-6"><label class="form-label">Chronic Diseases</label><textarea name="chronic_diseases" class="form-control" rows="2" placeholder="e.g. Diabetes, Hypertension"></textarea></div>

            <div class="col-12"><h6 class="fw-700 text-primary mb-1 mt-2"><i class="fa-solid fa-phone-volume me-2"></i>Emergency Contact</h6><hr class="my-2"/></div>
            <div class="col-md-4"><label class="form-label">Contact Name</label><input type="text" name="emergency_name" class="form-control" placeholder="Full name"/></div>
            <div class="col-md-4"><label class="form-label">Contact Phone</label><input type="tel" name="emergency_phone" class="form-control" placeholder="Phone number"/></div>
            <div class="col-md-4"><label class="form-label">Relation</label><input type="text" name="emergency_relation" class="form-control" placeholder="e.g. Spouse, Parent"/></div>

            <div class="col-12"><h6 class="fw-700 text-primary mb-1 mt-2"><i class="fa-solid fa-shield-halved me-2"></i>Insurance Details</h6><hr class="my-2"/></div>
            <div class="col-md-4"><label class="form-label">Insurance Provider</label><input type="text" name="insurance_provider" class="form-control" placeholder="Provider name"/></div>
            <div class="col-md-4"><label class="form-label">Policy Number</label><input type="text" name="insurance_number" class="form-control" placeholder="Policy/Member ID"/></div>
            <div class="col-md-4"><label class="form-label">Expiry Date</label><input type="date" name="insurance_expiry" class="form-control"/></div>

            <div class="col-12"><h6 class="fw-700 text-primary mb-1 mt-2"><i class="fa-solid fa-lock me-2"></i>Account Login</h6><hr class="my-2"/></div>
            <div class="col-md-6"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required placeholder="Set login password" minlength="8"/></div>
            <div class="col-md-6"><label class="form-label">Confirm Password *</label><input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password"/></div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitPatientForm()">
          <i class="fa-solid fa-user-plus me-2"></i>Register Patient
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', function() {
  HMS.initCounters();
});

function filterTable() {
  const gender = document.getElementById('genderFilter').value.toLowerCase();
  const status = document.getElementById('statusFilter').value.toLowerCase();
  document.querySelectorAll('#patientsTable tbody tr').forEach(row => {
    const rg = row.dataset.gender || '';
    const rs = row.dataset.status || '';
    const show = (!gender || rg === gender) && (!status || rs === status);
    row.style.display = show ? '' : 'none';
  });
}

function submitPatientForm() {
  const form = document.getElementById('addPatientForm');
  const pw   = form.querySelector('[name=password]').value;
  const cpw  = form.querySelector('[name=confirm_password]').value;
  if (pw !== cpw) { HMS.toast('Passwords do not match!', 'danger'); return; }
  HMSAjax.ajaxForm('#addPatientForm', APP_URL + '/api/patients.php', res => {
    bootstrap.Modal.getInstance(document.getElementById('addPatientModal')).hide();
    setTimeout(() => location.reload(), 800);
  });
  form.dispatchEvent(new Event('submit', { bubbles: true }));
}

function viewPatient(id) {
  window.location.href = APP_URL + '/admin/manage_patients.php?view=' + id;
}
function editPatient(id) {
  HMS.toast('Loading patient editor…', 'info');
}
function showQR(code) {
  HMS.toast('QR Code for ' + code + ' — feature coming soon', 'info');
}
function medHistory(id) {
  HMS.toast('Loading medical history…', 'info');
}
function exportPatients() {
  HMS.toast('Preparing export…', 'info');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
