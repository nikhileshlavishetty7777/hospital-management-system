<?php
// ============================================================
// patient/profile.php — Patient Profile & Medical Card
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('patient');

$pageTitle = 'My Profile';
$userId    = Auth::id();
$user      = Auth::user();
$patient   = Database::fetchOne("SELECT * FROM patients WHERE user_id=?", [$userId]);
if (!$patient) { flash('danger','Profile not found.'); redirect(APP_URL.'/login.php'); }
$pid = $patient['id'];

// Calculate age
$age = $patient['dob'] ? floor((time()-strtotime($patient['dob']))/31557600) : null;

// Medical summary
$lastVisit = Database::fetchOne("SELECT MAX(appointment_date) AS d FROM appointments WHERE patient_id=? AND status='completed'",[$pid])['d'];
$totalVisits = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND status='completed'",[$pid])['c'];
$activeRx    = Database::fetchOne("SELECT COUNT(*) AS c FROM prescriptions WHERE patient_id=? AND status='active'",[$pid])['c'];

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-id-card me-2 text-primary"></i>My Profile</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/patient/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Profile</li>
          </ol></nav>
        </div>
        <button class="btn btn-outline-primary ripple-btn" onclick="enableEdit()">
          <i class="fa-solid fa-pen me-2"></i>Edit Profile
        </button>
      </div>

      <div class="row g-4">
        <!-- Profile Card -->
        <div class="col-xl-4">
          <div class="card text-center hover-lift animate-scale-in">
            <div style="height:80px;background:linear-gradient(135deg,#0ea5e9,#6366f1);border-radius:var(--radius-lg) var(--radius-lg) 0 0"></div>
            <div class="card-body pt-0">
              <div class="mx-auto mb-3" style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#6366f1);display:grid;place-items:center;font-size:28px;font-weight:800;color:#fff;margin-top:-40px;border:4px solid var(--card-bg);box-shadow:0 4px 16px rgba(14,165,233,.3)">
                <?= strtoupper(substr($user['name'],0,2)) ?>
              </div>
              <h5 class="fw-800 mb-1"><?= htmlspecialchars($user['name']) ?></h5>
              <div class="text-muted text-sm mb-3"><?= htmlspecialchars($user['email']) ?></div>

              <!-- Patient ID badge -->
              <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill mb-3"
                   style="background:rgba(14,165,233,.1);border:1px solid rgba(14,165,233,.3)">
                <i class="fa-solid fa-id-card text-primary"></i>
                <span class="fw-800 text-primary"><?= htmlspecialchars($patient['patient_code']) ?></span>
              </div>

              <!-- Key info pills -->
              <div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
                <?php if($patient['blood_group']): ?>
                <span class="badge bg-danger py-2 px-3"><i class="fa-solid fa-droplet me-1"></i><?= htmlspecialchars($patient['blood_group']) ?></span>
                <?php endif; ?>
                <?php if($age): ?>
                <span class="badge bg-secondary py-2 px-3"><i class="fa-solid fa-calendar me-1"></i><?= $age ?> yrs</span>
                <?php endif; ?>
                <span class="badge bg-info py-2 px-3"><i class="fa-solid fa-<?= $patient['gender']==='male'?'mars':'venus' ?> me-1"></i><?= ucfirst($patient['gender']) ?></span>
              </div>

              <!-- Stats -->
              <div class="row g-2 text-center border-top pt-3">
                <div class="col-4"><div class="fw-800 text-primary"><?= $totalVisits ?></div><div class="text-muted text-xs">Visits</div></div>
                <div class="col-4"><div class="fw-800 text-success"><?= $activeRx ?></div><div class="text-muted text-xs">Active Rx</div></div>
                <div class="col-4"><div class="fw-800 text-warning" style="font-size:11px"><?= $lastVisit ? date('d M', strtotime($lastVisit)) : '—' ?></div><div class="text-muted text-xs">Last Visit</div></div>
              </div>
            </div>
          </div>

          <!-- QR Code placeholder -->
          <div class="card mt-3 text-center">
            <div class="card-body py-4">
              <i class="fa-solid fa-qrcode fa-4x text-primary mb-3 opacity-50"></i>
              <div class="fw-600 text-sm mb-1">Patient QR Card</div>
              <div class="text-muted text-xs mb-3">Show this at reception for quick check-in</div>
              <button class="btn btn-outline-primary btn-sm ripple-btn" onclick="HMS.toast('QR card downloaded!','success')">
                <i class="fa-solid fa-download me-2"></i>Download QR Card
              </button>
            </div>
          </div>
        </div>

        <!-- Profile Details -->
        <div class="col-xl-8">
          <form id="profileForm">
            <!-- Personal Info -->
            <div class="card mb-4 animate-fade-in">
              <div class="card-header fw-700"><i class="fa-solid fa-user me-2 text-primary"></i>Personal Information</div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control profile-field" value="<?= htmlspecialchars($user['name']) ?>" readonly/></div>
                  <div class="col-md-6"><label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control profile-field" value="<?= htmlspecialchars($user['email']) ?>" readonly/></div>
                  <div class="col-md-4"><label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control profile-field" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly/></div>
                  <div class="col-md-4"><label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control profile-field" value="<?= htmlspecialchars($patient['dob'] ?? '') ?>" readonly/></div>
                  <div class="col-md-2"><label class="form-label">Gender</label>
                    <input type="text" class="form-control profile-field" value="<?= ucfirst($patient['gender']) ?>" readonly/></div>
                  <div class="col-md-2"><label class="form-label">Blood Group</label>
                    <input type="text" class="form-control profile-field" value="<?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?>" readonly/></div>
                  <div class="col-12"><label class="form-label">Address</label>
                    <textarea name="address" class="form-control profile-field" rows="2" readonly><?= htmlspecialchars($patient['address'] ?? '') ?></textarea></div>
                  <div class="col-md-4"><label class="form-label">City</label>
                    <input type="text" name="city" class="form-control profile-field" value="<?= htmlspecialchars($patient['city'] ?? '') ?>" readonly/></div>
                  <div class="col-md-4"><label class="form-label">State</label>
                    <input type="text" name="state" class="form-control profile-field" value="<?= htmlspecialchars($patient['state'] ?? '') ?>" readonly/></div>
                  <div class="col-md-4"><label class="form-label">PIN Code</label>
                    <input type="text" name="pincode" class="form-control profile-field" value="<?= htmlspecialchars($patient['pincode'] ?? '') ?>" readonly/></div>
                </div>
              </div>
            </div>

            <!-- Medical Info -->
            <div class="card mb-4 animate-fade-in">
              <div class="card-header fw-700"><i class="fa-solid fa-notes-medical me-2 text-primary"></i>Medical Information</div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Known Allergies</label>
                    <?php if($patient['allergies']): ?>
                    <div class="alert alert-warning py-2 mb-0 text-sm">
                      <i class="fa-solid fa-triangle-exclamation me-1"></i><?= htmlspecialchars($patient['allergies']) ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted text-sm p-2">No known allergies recorded.</div>
                    <?php endif; ?>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Chronic Conditions</label>
                    <?php if($patient['chronic_diseases']): ?>
                    <div class="alert alert-info py-2 mb-0 text-sm">
                      <i class="fa-solid fa-stethoscope me-1"></i><?= htmlspecialchars($patient['chronic_diseases']) ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted text-sm p-2">No chronic conditions recorded.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Emergency + Insurance -->
            <div class="row g-4 mb-4">
              <div class="col-md-6">
                <div class="card h-100">
                  <div class="card-header fw-700"><i class="fa-solid fa-phone-volume me-2 text-danger"></i>Emergency Contact</div>
                  <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-2">
                      <div style="width:42px;height:42px;border-radius:50%;background:rgba(239,68,68,.1);display:grid;place-items:center"><i class="fa-solid fa-user text-danger"></i></div>
                      <div>
                        <div class="fw-700"><?= htmlspecialchars($patient['emergency_name'] ?? 'Not provided') ?></div>
                        <div class="text-muted text-xs"><?= htmlspecialchars($patient['emergency_relation'] ?? '') ?></div>
                      </div>
                    </div>
                    <?php if($patient['emergency_phone']): ?>
                    <a href="tel:<?= htmlspecialchars($patient['emergency_phone']) ?>" class="btn btn-outline-danger btn-sm w-100">
                      <i class="fa-solid fa-phone me-2"></i><?= htmlspecialchars($patient['emergency_phone']) ?>
                    </a>
                    <?php else: ?>
                    <div class="text-muted text-sm">No emergency contact provided.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card h-100">
                  <div class="card-header fw-700"><i class="fa-solid fa-shield-halved me-2 text-success"></i>Insurance</div>
                  <div class="card-body">
                    <?php if($patient['insurance_provider']): ?>
                    <div class="fw-700 mb-1"><?= htmlspecialchars($patient['insurance_provider']) ?></div>
                    <div class="text-muted text-xs mb-2">Policy: <?= htmlspecialchars($patient['insurance_number'] ?? '—') ?></div>
                    <?php if($patient['insurance_expiry']): ?>
                    <span class="badge <?= strtotime($patient['insurance_expiry']) > time() ? 'bg-success' : 'bg-danger' ?>">
                      Expires: <?= date('d M Y', strtotime($patient['insurance_expiry'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-muted text-sm text-center py-2"><i class="fa-solid fa-shield-xmark fa-2x d-block mb-2 opacity-25"></i>No insurance registered</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Save button (hidden until edit mode) -->
            <div id="saveBar" style="display:none" class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" onclick="cancelEdit()">Cancel</button>
              <button type="button" class="btn btn-primary ripple-btn" onclick="saveProfile()"><i class="fa-solid fa-save me-2"></i>Save Changes</button>
            </div>
          </form>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$patientId = $patient['id'];
$inlineScript = <<<JS
function enableEdit() {
  document.querySelectorAll('.profile-field').forEach(f => f.removeAttribute('readonly'));
  document.getElementById('saveBar').style.display = '';
  HMS.toast('Profile editing enabled.','info');
}

function cancelEdit() {
  document.querySelectorAll('.profile-field').forEach(f => f.setAttribute('readonly',''));
  document.getElementById('saveBar').style.display = 'none';
}

async function saveProfile() {
  const form = document.getElementById('profileForm');
  const fd   = new FormData(form);
  const body = Object.fromEntries(fd.entries());
  const res  = await HMSAjax.put(APP_URL+'/api/patients.php?id={$patientId}', body);
  if (res.success) {
    HMS.toast('Profile updated!','success');
    cancelEdit();
  }
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
