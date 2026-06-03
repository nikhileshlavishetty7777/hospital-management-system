<?php
// ============================================================
// receptionist/registration.php — Enhanced Patient Registration
// Uses SAME sidebar / navbar / dashboard UI as all other pages
// NEW: photo upload, AJAX email check, patient code preview,
//      print patient card, instant duplicate detection
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['receptionist','admin']);

$pageTitle = 'Patient Registration';

// ── Stats ─────────────────────────────────────────────────────
$todayReg   = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE DATE(created_at)=CURDATE()")['c'];
$totalPats  = Database::fetchOne("SELECT COUNT(*) AS c FROM patients")['c'];
$monthReg   = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];

// ── Inline AJAX handler (same file, ?ajax=1) ──────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Check email uniqueness
    if ($_GET['ajax'] === 'check_email' && !empty($_GET['email'])) {
        $exists = Database::fetchOne("SELECT id FROM users WHERE email=?", [clean($_GET['email'])]);
        echo json_encode(['available' => !$exists]);
        exit;
    }

    // Check phone uniqueness
    if ($_GET['ajax'] === 'check_phone' && !empty($_GET['phone'])) {
        $exists = Database::fetchOne("SELECT id FROM users WHERE phone=?", [clean($_GET['phone'])]);
        echo json_encode(['available' => !$exists]);
        exit;
    }

    // Preview next patient code
    if ($_GET['ajax'] === 'next_code') {
        $last = Database::fetchOne("SELECT id FROM users ORDER BY id DESC LIMIT 1");
        $nextId = ($last['id'] ?? 0) + 1;
        echo json_encode(['code' => generateCode('PAT', $nextId)]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

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
          <h1><i class="fa-solid fa-user-plus me-2 text-primary"></i>Patient Registration</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/receptionist/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Register Patient</li>
          </ol></nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="resetForm()">
            <i class="fa-solid fa-rotate-right me-2"></i>Reset Form
          </button>
        </div>
      </div>

      <!-- Stat Cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-blue hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalPats ?>">0</div><div class="stat-label">Total Patients</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayReg ?>">0</div><div class="stat-label">Registered Today</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-purple hover-lift">
            <div class="stat-icon"><i class="fa-solid fa-calendar-month"></i></div>
            <div><div class="stat-value" data-counter="<?= $monthReg ?>">0</div><div class="stat-label">This Month</div></div>
          </div>
        </div>
      </div>

      <div class="row g-4">

        <!-- ── LEFT: Photo + Patient Card Preview ────────────── -->
        <div class="col-xl-3 col-lg-4">

          <!-- Photo Upload Card -->
          <div class="card mb-4 animate-scale-in">
            <div class="card-header fw-700"><i class="fa-solid fa-camera me-2 text-primary"></i>Patient Photo</div>
            <div class="card-body text-center">
              <!-- Preview circle -->
              <div id="photoPreviewWrap" style="position:relative;display:inline-block;margin-bottom:16px;">
                <div id="photoPreview"
                     style="width:110px;height:110px;border-radius:50%;
                            background:linear-gradient(135deg,var(--primary),var(--secondary));
                            display:grid;place-items:center;
                            font-size:36px;font-weight:800;color:#fff;
                            overflow:hidden;margin:0 auto;
                            border:3px solid var(--border);
                            box-shadow:var(--shadow-lg);
                            cursor:pointer;transition:var(--transition);"
                     onclick="document.getElementById('photoInput').click()"
                     title="Click to upload photo">
                  <i class="fa-solid fa-user" id="photoIcon"></i>
                </div>
                <!-- Camera overlay on hover -->
                <div id="photoOverlay"
                     style="position:absolute;inset:0;border-radius:50%;
                            background:rgba(0,0,0,.5);display:none;
                            place-items:center;cursor:pointer;"
                     onclick="document.getElementById('photoInput').click()">
                  <i class="fa-solid fa-camera" style="color:#fff;font-size:20px"></i>
                </div>
              </div>

              <input type="file" id="photoInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                     style="display:none" onchange="previewPhoto(this)"/>

              <div class="text-muted text-xs mb-3">JPG, PNG or WebP<br>Max 5 MB — Click photo to change</div>

              <button type="button" class="btn btn-outline-primary btn-sm w-100 ripple-btn"
                      onclick="document.getElementById('photoInput').click()">
                <i class="fa-solid fa-upload me-2"></i>Upload Photo
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm w-100 mt-2" id="removePhotoBtn"
                      style="display:none" onclick="removePhoto()">
                <i class="fa-solid fa-trash me-2"></i>Remove Photo
              </button>
            </div>
          </div>

          <!-- Patient ID Preview Card -->
          <div class="card animate-scale-in delay-2">
            <div class="card-header fw-700"><i class="fa-solid fa-id-card me-2 text-success"></i>Patient ID Preview</div>
            <div class="card-body">
              <div class="text-center py-2">
                <!-- Mini patient card -->
                <div style="background:linear-gradient(135deg,var(--primary),var(--secondary));
                            border-radius:12px;padding:16px;color:#fff;text-align:left;
                            box-shadow:0 4px 16px rgba(14,165,233,.3);">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.2);display:grid;place-items:center">
                      <i class="fa-solid fa-hospital-user" style="font-size:14px"></i>
                    </div>
                    <div>
                      <div style="font-size:9px;opacity:.7;text-transform:uppercase;letter-spacing:.5px">MediCare HMS</div>
                      <div style="font-size:10px;font-weight:700">Patient Card</div>
                    </div>
                  </div>
                  <div id="cardPatientName" style="font-size:14px;font-weight:800;margin-bottom:2px">Full Name</div>
                  <div id="cardPatientId" style="font-size:11px;opacity:.8;font-family:monospace">PAT-?????</div>
                  <div class="d-flex justify-content-between mt-2" style="font-size:9px;opacity:.6">
                    <span id="cardGender">Gender</span>
                    <span id="cardBlood">Blood: —</span>
                  </div>
                </div>
              </div>
              <button class="btn btn-outline-success btn-sm w-100 mt-3" onclick="printPatientCard()" id="printCardBtn" disabled>
                <i class="fa-solid fa-print me-2"></i>Print Card
              </button>
            </div>
          </div>

        </div>

        <!-- ── RIGHT: Registration Form ──────────────────────── -->
        <div class="col-xl-9 col-lg-8">
          <div class="card animate-fade-in">
            <div class="card-header fw-700">
              <i class="fa-solid fa-clipboard-list me-2 text-primary"></i>
              New Patient Registration Form
              <span class="badge bg-primary ms-2" id="stepBadge">Step 1 of 4</span>
            </div>
            <div class="card-body">

              <!-- Step Indicator Tabs -->
              <div class="d-flex mb-4" id="stepTabs">
                <?php
                $steps = [
                  ['fa-user','Personal Info'],
                  ['fa-notes-medical','Medical Info'],
                  ['fa-phone-volume','Emergency & Insurance'],
                  ['fa-lock','Account Setup'],
                ];
                foreach ($steps as $si => [$icon, $label]):
                ?>
                <div class="flex-1 text-center step-tab py-2 px-1"
                     data-step="<?= $si ?>"
                     onclick="gotoStep(<?= $si ?>)"
                     style="cursor:pointer;border-bottom:3px solid <?= $si===0 ? 'var(--primary)' : 'var(--border)' ?>;transition:all .3s">
                  <i class="fa-solid <?= $icon ?> mb-1 d-block"
                     style="font-size:16px;color:<?= $si===0 ? 'var(--primary)' : 'var(--text-muted)' ?>"></i>
                  <div class="fw-600 text-xs d-none d-md-block"
                       style="color:<?= $si===0 ? 'var(--primary)' : 'var(--text-muted)' ?>"><?= $label ?></div>
                </div>
                <?php endforeach; ?>
              </div>

              <form id="regForm" enctype="multipart/form-data">

                <!-- ── STEP 0: Personal Info ──────────────────── -->
                <div class="step-panel" data-step="0">
                  <div class="row g-3">
                    <div class="col-12">
                      <h6 class="fw-700 text-primary mb-0">
                        <i class="fa-solid fa-user me-2"></i>Personal Information
                      </h6>
                      <hr class="mt-2 mb-3"/>
                    </div>

                    <div class="col-md-8">
                      <label class="form-label">Full Name *</label>
                      <input type="text" name="full_name" id="fullNameInput" class="form-control" required
                             placeholder="Patient's full name" minlength="3"
                             oninput="updateCard()"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Patient Code</label>
                      <div class="input-group">
                        <input type="text" id="patCodePreview" class="form-control" readonly
                               style="font-family:var(--font-mono);font-weight:700;color:var(--primary)"
                               placeholder="Auto-generated"/>
                        <button type="button" class="btn btn-outline-primary" onclick="refreshCode()" title="Refresh">
                          <i class="fa-solid fa-rotate-right"></i>
                        </button>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Email Address *</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" name="email" id="emailInput" class="form-control" required
                               placeholder="patient@email.com"/>
                        <span class="input-group-text" id="emailStatus" title="Check availability">
                          <i class="fa-solid fa-circle-question text-muted"></i>
                        </span>
                      </div>
                      <div id="emailMsg" class="text-xs mt-1"></div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Phone Number *</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" name="phone" id="phoneInput" class="form-control" required
                               placeholder="+91 98765 43210"/>
                        <span class="input-group-text" id="phoneStatus">
                          <i class="fa-solid fa-circle-question text-muted"></i>
                        </span>
                      </div>
                      <div id="phoneMsg" class="text-xs mt-1"></div>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">Date of Birth</label>
                      <input type="date" name="dob" class="form-control"
                             max="<?= date('Y-m-d') ?>"
                             onchange="calcAge(this)"/>
                    </div>
                    <div class="col-md-2">
                      <label class="form-label">Age</label>
                      <input type="text" id="ageDisplay" class="form-control" readonly placeholder="Auto"/>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Gender *</label>
                      <select name="gender" class="form-select" required onchange="updateCard()">
                        <option value="">Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Blood Group</label>
                      <select name="blood_group" class="form-select" onchange="updateCard()">
                        <option value="">Unknown</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?= $bg ?>"><?= $bg ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Street Address</label>
                      <textarea name="address" class="form-control" rows="2"
                                placeholder="House No., Street, Area…"></textarea>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">City</label>
                      <input type="text" name="city" class="form-control" placeholder="City"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">State</label>
                      <input type="text" name="state" class="form-control" placeholder="State"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">PIN Code</label>
                      <input type="text" name="pincode" class="form-control" placeholder="400001"/>
                    </div>
                  </div>
                </div>

                <!-- ── STEP 1: Medical Info ───────────────────── -->
                <div class="step-panel" data-step="1" style="display:none">
                  <div class="row g-3">
                    <div class="col-12"><h6 class="fw-700 text-primary"><i class="fa-solid fa-notes-medical me-2"></i>Medical Information</h6><hr class="mt-2 mb-3"/></div>
                    <div class="col-md-6">
                      <label class="form-label">Known Allergies</label>
                      <textarea name="allergies" class="form-control" rows="4"
                                placeholder="e.g. Penicillin, Aspirin, Peanuts…"></textarea>
                      <div class="text-muted text-xs mt-1">
                        <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>
                        Critical information — used for prescription safety checks
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Chronic Diseases / Conditions</label>
                      <textarea name="chronic_diseases" class="form-control" rows="4"
                                placeholder="e.g. Diabetes Type 2, Hypertension, Asthma…"></textarea>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Marital Status</label>
                      <select name="marital_status" class="form-select">
                        <option value="">Select</option>
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="divorced">Divorced</option>
                        <option value="widowed">Widowed</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Occupation</label>
                      <input type="text" name="occupation" class="form-control" placeholder="e.g. Teacher, Engineer…"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Nationality</label>
                      <input type="text" name="nationality" class="form-control" value="Indian"/>
                    </div>
                  </div>
                </div>

                <!-- ── STEP 2: Emergency + Insurance ─────────── -->
                <div class="step-panel" data-step="2" style="display:none">
                  <div class="row g-3">
                    <div class="col-12"><h6 class="fw-700 text-primary"><i class="fa-solid fa-phone-volume me-2"></i>Emergency Contact</h6><hr class="mt-2 mb-3"/></div>
                    <div class="col-md-4">
                      <label class="form-label">Contact Name</label>
                      <input type="text" name="emergency_name" class="form-control" placeholder="Full name"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Contact Phone</label>
                      <input type="tel" name="emergency_phone" class="form-control" placeholder="Phone number"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Relation</label>
                      <select name="emergency_relation" class="form-select">
                        <option value="">Select</option>
                        <?php foreach(['Spouse','Parent','Child','Sibling','Guardian','Friend','Other'] as $r): ?>
                        <option value="<?= $r ?>"><?= $r ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-12 mt-3"><h6 class="fw-700 text-primary"><i class="fa-solid fa-shield-halved me-2"></i>Insurance Details</h6><hr class="mt-2 mb-3"/></div>
                    <div class="col-md-4">
                      <label class="form-label">Insurance Provider</label>
                      <input type="text" name="insurance_provider" class="form-control"
                             placeholder="e.g. Star Health, LIC…"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Policy / Member ID</label>
                      <input type="text" name="insurance_number" class="form-control" placeholder="Policy number"/>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Policy Expiry Date</label>
                      <input type="date" name="insurance_expiry" class="form-control"
                             min="<?= date('Y-m-d') ?>"/>
                    </div>

                    <!-- Document upload -->
                    <div class="col-12 mt-3"><h6 class="fw-700 text-primary"><i class="fa-solid fa-folder-open me-2"></i>Documents (Optional)</h6><hr class="mt-2 mb-3"/></div>
                    <div class="col-12">
                      <div class="p-3 rounded text-center"
                           style="border:2px dashed var(--border);cursor:pointer;transition:var(--transition)"
                           onclick="document.getElementById('docInput').click()"
                           id="docDropZone"
                           ondragover="this.style.borderColor='var(--primary)';event.preventDefault()"
                           ondragleave="this.style.borderColor='var(--border)'"
                           ondrop="handleDocDrop(event)">
                        <i class="fa-solid fa-file-arrow-up fa-2x text-primary mb-2 d-block opacity-50"></i>
                        <div class="fw-600 text-sm">Upload Insurance / ID Documents</div>
                        <div class="text-muted text-xs mt-1">PDF, JPG, PNG — max 10 MB</div>
                        <input type="file" id="docInput" name="documents[]" multiple
                               accept=".pdf,.jpg,.jpeg,.png" style="display:none"
                               onchange="showDocFiles(this.files)"/>
                      </div>
                      <div id="docList" class="mt-2"></div>
                    </div>
                  </div>
                </div>

                <!-- ── STEP 3: Account Setup ──────────────────── -->
                <div class="step-panel" data-step="3" style="display:none">
                  <div class="row g-3">
                    <div class="col-12"><h6 class="fw-700 text-primary"><i class="fa-solid fa-lock me-2"></i>Patient Portal Account</h6><hr class="mt-2 mb-3"/></div>

                    <div class="col-12">
                      <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                        <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
                        <div class="text-sm">
                          A unique <strong>Patient ID</strong> (e.g. <code id="finalPatCode">PAT-?????</code>) will be generated automatically.
                          The patient can log in to the <strong>Patient Portal</strong> using their email and the password you set below.
                        </div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Password *</label>
                      <div class="input-group">
                        <input type="password" name="password" id="pwField" class="form-control"
                               required minlength="8" placeholder="Min 8 characters"
                               oninput="checkPwStrength(this.value)"/>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pwField','eyePw')">
                          <i class="fa-solid fa-eye" id="eyePw"></i>
                        </button>
                      </div>
                      <div class="progress mt-2" style="height:5px">
                        <div class="progress-bar" id="pwBar" style="width:0%;transition:all .3s"></div>
                      </div>
                      <div class="text-xs mt-1" id="pwText" style="color:var(--text-muted)"></div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Confirm Password *</label>
                      <div class="input-group">
                        <input type="password" name="confirm_password" id="cpwField" class="form-control"
                               required placeholder="Repeat password"
                               oninput="checkMatch()"/>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePw('cpwField','eyyCpw')">
                          <i class="fa-solid fa-eye" id="eyyCpw"></i>
                        </button>
                      </div>
                      <div class="text-xs mt-1" id="matchText" style="color:var(--text-muted)"></div>
                    </div>

                    <!-- Summary preview -->
                    <div class="col-12">
                      <div class="card" style="background:var(--bg)">
                        <div class="card-body py-3">
                          <div class="fw-700 text-sm mb-2"><i class="fa-solid fa-eye me-2 text-primary"></i>Registration Summary</div>
                          <div class="row g-2 text-sm" id="summaryBlock">
                            <div class="col-6"><span class="text-muted">Name:</span> <span id="sumName" class="fw-600">—</span></div>
                            <div class="col-6"><span class="text-muted">Email:</span> <span id="sumEmail" class="fw-600">—</span></div>
                            <div class="col-6"><span class="text-muted">Phone:</span> <span id="sumPhone" class="fw-600">—</span></div>
                            <div class="col-6"><span class="text-muted">Gender:</span> <span id="sumGender" class="fw-600">—</span></div>
                            <div class="col-6"><span class="text-muted">Blood:</span> <span id="sumBlood" class="fw-600">—</span></div>
                            <div class="col-6"><span class="text-muted">Patient ID:</span> <span id="sumCode" class="fw-600 text-primary" style="font-family:monospace">—</span></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- ── Navigation Buttons ─────────────────────── -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                  <button type="button" class="btn btn-outline-secondary" id="btnPrev"
                          onclick="stepNav(-1)" style="display:none">
                    <i class="fa-solid fa-arrow-left me-2"></i>Back
                  </button>
                  <button type="button" class="btn btn-primary ripple-btn ms-auto" id="btnNext"
                          onclick="stepNav(1)">
                    Next Step <i class="fa-solid fa-arrow-right ms-2"></i>
                  </button>
                  <button type="button" class="btn btn-success ripple-btn ms-2" id="btnSubmit"
                          style="display:none" onclick="submitRegistration()">
                    <i class="fa-solid fa-user-plus me-2"></i>Register Patient
                  </button>
                </div>

              </form>
            </div>
          </div>
        </div><!-- /.col -->
      </div><!-- /.row -->

    </main>
  </div>
</div>

<!-- ── Success Modal (shown after registration) ───────────────── -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-body text-center p-5">
        <div class="mb-4" style="width:80px;height:80px;border-radius:50%;background:rgba(34,197,94,.15);display:grid;place-items:center;margin:0 auto">
          <i class="fa-solid fa-circle-check fa-3x text-success animate-bounce-in"></i>
        </div>
        <h4 class="fw-800 mb-2">Patient Registered!</h4>
        <p class="text-muted mb-1">Patient ID assigned:</p>
        <div class="mb-3" style="font-family:monospace;font-size:22px;font-weight:800;color:var(--primary)" id="successPatCode"></div>
        <p class="text-muted text-sm mb-4" id="successPatName"></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <button class="btn btn-success ripple-btn" onclick="printPatientCard()">
            <i class="fa-solid fa-print me-2"></i>Print Patient Card
          </button>
          <button class="btn btn-primary ripple-btn" onclick="bookAppointmentNow()">
            <i class="fa-solid fa-calendar-plus me-2"></i>Book Appointment
          </button>
          <button class="btn btn-outline-secondary" onclick="registerAnother()">
            <i class="fa-solid fa-rotate-right me-2"></i>Register Another
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Print Card Template (hidden) -->
<div id="printTemplate" style="display:none">
  <div style="width:340px;padding:20px;font-family:'Plus Jakarta Sans',sans-serif;background:#fff;border-radius:12px;border:1px solid #e2e8f0">
    <div style="background:linear-gradient(135deg,#0ea5e9,#6366f1);border-radius:8px;padding:16px;color:#fff;margin-bottom:16px">
      <div style="font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">MediCare Hospital Management System</div>
      <div style="font-size:16px;font-weight:800" id="printName">Patient Name</div>
      <div style="font-size:13px;font-family:monospace;font-weight:700;margin-top:2px" id="printCode">PAT-?????</div>
    </div>
    <table style="width:100%;font-size:12px;border-collapse:collapse">
      <tr><td style="color:#64748b;padding:3px 0">Gender:</td><td style="font-weight:600" id="printGender">—</td></tr>
      <tr><td style="color:#64748b;padding:3px 0">Blood Group:</td><td style="font-weight:600" id="printBlood">—</td></tr>
      <tr><td style="color:#64748b;padding:3px 0">Phone:</td><td style="font-weight:600" id="printPhone">—</td></tr>
      <tr><td style="color:#64748b;padding:3px 0">Registered:</td><td style="font-weight:600"><?= date('d M Y') ?></td></tr>
    </table>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
// ─────────────────────────────────────────────────────────────
// receptionist/registration.php — JavaScript
// ─────────────────────────────────────────────────────────────
let currentStep   = 0;
const TOTAL_STEPS = 4;
let emailValid    = false;
let phoneValid    = true;  // phone duplicate check optional
let registeredPatCode = '';
let registeredPatId   = 0;

document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  refreshCode();
  focusFirst();
});

// ── Step navigation ───────────────────────────────────────────
function gotoStep(step) {
  if (step < 0 || step >= TOTAL_STEPS) return;

  // Validate step 0 before leaving
  if (step > currentStep && currentStep === 0 && !validateStep0()) return;

  document.querySelectorAll('.step-panel').forEach((p, i) => {
    p.style.display = i === step ? '' : 'none';
  });
  document.querySelectorAll('#stepTabs .step-tab').forEach((t, i) => {
    const icon = t.querySelector('i');
    const lbl  = t.querySelector('div');
    const active = i === step;
    const done   = i < step;
    t.style.borderBottomColor = active ? 'var(--primary)' : (done ? 'var(--success)' : 'var(--border)');
    if (icon) icon.style.color = active ? 'var(--primary)' : (done ? 'var(--success)' : 'var(--text-muted)');
    if (lbl)  lbl.style.color  = active ? 'var(--primary)' : (done ? 'var(--success)' : 'var(--text-muted)');
  });

  currentStep = step;
  document.getElementById('btnPrev').style.display   = step === 0 ? 'none' : '';
  document.getElementById('btnNext').style.display   = step === TOTAL_STEPS - 1 ? 'none' : '';
  document.getElementById('btnSubmit').style.display = step === TOTAL_STEPS - 1 ? '' : 'none';
  document.getElementById('stepBadge').textContent   = `Step ${step + 1} of ${TOTAL_STEPS}`;

  // Populate summary on last step
  if (step === TOTAL_STEPS - 1) populateSummary();

  // Scroll to top of card
  document.querySelector('.card.animate-fade-in')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function stepNav(dir) { gotoStep(currentStep + dir); }

function focusFirst() {
  document.querySelector('[name=full_name]')?.focus();
}

// ── Validation step 0 ─────────────────────────────────────────
function validateStep0() {
  const name   = document.querySelector('[name=full_name]').value.trim();
  const email  = document.querySelector('[name=email]').value.trim();
  const phone  = document.querySelector('[name=phone]').value.trim();
  const gender = document.querySelector('[name=gender]').value;

  if (name.length < 3)       { HMS.toast('Full name must be at least 3 characters.','warning'); return false; }
  if (!isValidEmail(email))  { HMS.toast('Please enter a valid email address.','warning'); return false; }
  if (phone.length < 10)     { HMS.toast('Please enter a valid phone number.','warning'); return false; }
  if (!gender)               { HMS.toast('Please select gender.','warning'); return false; }
  if (!emailValid)           { HMS.toast('Please wait for email validation to complete.','warning'); return false; }
  return true;
}

function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

// ── Live patient code preview ─────────────────────────────────
async function refreshCode() {
  try {
    const res = await fetch(APP_URL + '/receptionist/registration.php?ajax=next_code');
    const data = await res.json();
    document.getElementById('patCodePreview').value = data.code || 'PAT-?????';
    document.getElementById('finalPatCode').textContent = data.code || '—';
    document.getElementById('cardPatientId').textContent = data.code || 'PAT-?????';
    document.getElementById('sumCode').textContent = data.code || '—';
  } catch {}
}

// ── Live email check ──────────────────────────────────────────
let emailTimer;
document.addEventListener('DOMContentLoaded', () => {
  const emailInp = document.getElementById('emailInput');
  if (!emailInp) return;
  emailInp.addEventListener('blur', () => checkEmailAvailability());
  emailInp.addEventListener('input', () => {
    clearTimeout(emailTimer);
    emailValid = false;
    emailTimer = setTimeout(checkEmailAvailability, 600);
  });

  const phoneInp = document.getElementById('phoneInput');
  if (phoneInp) {
    phoneInp.addEventListener('blur', () => checkPhoneAvailability());
  }
});

async function checkEmailAvailability() {
  const email = document.getElementById('emailInput').value.trim();
  const status = document.getElementById('emailStatus');
  const msg    = document.getElementById('emailMsg');
  if (!isValidEmail(email)) { emailValid = false; return; }

  status.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-muted"></i>';
  try {
    const res  = await fetch(APP_URL + '/receptionist/registration.php?ajax=check_email&email=' + encodeURIComponent(email));
    const data = await res.json();
    if (data.available) {
      status.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i>';
      msg.innerHTML    = '<span class="text-success">✓ Email is available</span>';
      emailValid = true;
    } else {
      status.innerHTML = '<i class="fa-solid fa-circle-xmark text-danger"></i>';
      msg.innerHTML    = '<span class="text-danger">✗ This email is already registered. <a href="' + APP_URL + '/login.php" class="text-primary">Login instead?</a></span>';
      emailValid = false;
    }
  } catch {
    status.innerHTML = '<i class="fa-solid fa-circle-question text-muted"></i>';
    emailValid = true; // don't block on network error
  }
}

async function checkPhoneAvailability() {
  const phone  = document.getElementById('phoneInput').value.trim();
  const status = document.getElementById('phoneStatus');
  const msg    = document.getElementById('phoneMsg');
  if (phone.length < 10) return;

  status.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-muted"></i>';
  try {
    const res  = await fetch(APP_URL + '/receptionist/registration.php?ajax=check_phone&phone=' + encodeURIComponent(phone));
    const data = await res.json();
    if (data.available) {
      status.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i>';
      msg.innerHTML    = '';
    } else {
      status.innerHTML = '<i class="fa-solid fa-circle-exclamation text-warning"></i>';
      msg.innerHTML    = '<span class="text-warning">⚠ A patient with this phone already exists.</span>';
    }
  } catch {
    status.innerHTML = '<i class="fa-solid fa-circle-question text-muted"></i>';
  }
}

// ── Photo preview ─────────────────────────────────────────────
function previewPhoto(input) {
  if (!input.files[0]) return;
  if (input.files[0].size > 5 * 1024 * 1024) {
    HMS.toast('Photo must be under 5 MB.', 'warning');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = `<img src="${e.target.result}" alt="preview" style="width:100%;height:100%;object-fit:cover"/>`;
    document.getElementById('removePhotoBtn').style.display = '';
    preview.addEventListener('mouseenter', () => { document.getElementById('photoOverlay').style.display = 'grid'; });
    preview.addEventListener('mouseleave', () => { document.getElementById('photoOverlay').style.display = 'none'; });
  };
  reader.readAsDataURL(input.files[0]);
}

function removePhoto() {
  document.getElementById('photoInput').value = '';
  document.getElementById('photoPreview').innerHTML = '<i class="fa-solid fa-user" id="photoIcon"></i>';
  document.getElementById('removePhotoBtn').style.display = 'none';
}

// ── Live patient card update ──────────────────────────────────
function updateCard() {
  const name   = document.querySelector('[name=full_name]')?.value.trim() || 'Full Name';
  const gender = document.querySelector('[name=gender]')?.value || '—';
  const blood  = document.querySelector('[name=blood_group]')?.value || '—';

  document.getElementById('cardPatientName').textContent = name || 'Full Name';
  document.getElementById('cardGender').textContent      = gender.charAt(0).toUpperCase() + gender.slice(1) || '—';
  document.getElementById('cardBlood').textContent       = 'Blood: ' + (blood || '—');
}

// ── Age calculator ────────────────────────────────────────────
function calcAge(input) {
  if (!input.value) return;
  const age = Math.floor((new Date() - new Date(input.value)) / 31557600000);
  document.getElementById('ageDisplay').value = age >= 0 ? age + ' years' : 'Invalid';
}

// ── Password strength ─────────────────────────────────────────
function checkPwStrength(pw) {
  let score = 0;
  if (pw.length >= 8)          score++;
  if (/[A-Z]/.test(pw))        score++;
  if (/[0-9]/.test(pw))        score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const map = [
    { w:'15%',  color:'#ef4444', label:'Too weak'  },
    { w:'35%',  color:'#f97316', label:'Weak'       },
    { w:'60%',  color:'#f59e0b', label:'Fair'       },
    { w:'80%',  color:'#22c55e', label:'Good'       },
    { w:'100%', color:'#0ea5e9', label:'Strong'     },
  ];
  const lvl = pw.length === 0 ? null : map[Math.min(score, 4)];
  document.getElementById('pwBar').style.width      = lvl?.w     || '0';
  document.getElementById('pwBar').style.background = lvl?.color || '';
  document.getElementById('pwText').textContent     = lvl?.label || '';
  document.getElementById('pwText').style.color     = lvl?.color || '';
  checkMatch();
}

function checkMatch() {
  const pw  = document.getElementById('pwField').value;
  const cpw = document.getElementById('cpwField').value;
  const el  = document.getElementById('matchText');
  if (!cpw) { el.textContent = ''; return; }
  if (pw === cpw) { el.textContent = '✓ Passwords match'; el.style.color = 'var(--success)'; }
  else            { el.textContent = '✗ Passwords do not match'; el.style.color = 'var(--danger)'; }
}

function togglePw(fieldId, eyeId) {
  const f = document.getElementById(fieldId);
  const i = document.getElementById(eyeId);
  f.type = f.type === 'password' ? 'text' : 'password';
  i.className = f.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}

// ── Summary population (step 3) ───────────────────────────────
function populateSummary() {
  const f = n => document.querySelector(`[name=${n}]`)?.value || '—';
  document.getElementById('sumName').textContent   = f('full_name');
  document.getElementById('sumEmail').textContent  = f('email');
  document.getElementById('sumPhone').textContent  = f('phone');
  document.getElementById('sumGender').textContent = f('gender');
  document.getElementById('sumBlood').textContent  = f('blood_group') || 'Unknown';
  document.getElementById('sumCode').textContent   = document.getElementById('patCodePreview').value;
}

// ── Document upload display ───────────────────────────────────
function showDocFiles(files) {
  const list = document.getElementById('docList');
  if (!files.length) { list.innerHTML = ''; return; }
  list.innerHTML = [...files].map(f =>
    `<div class="d-flex align-items-center gap-2 p-2 rounded mb-1" style="background:var(--bg)">
       <i class="fa-solid ${f.type==='application/pdf'?'fa-file-pdf text-danger':'fa-file-image text-primary'}"></i>
       <span class="text-sm flex-1">${f.name}</span>
       <span class="text-muted text-xs">${(f.size/1024/1024).toFixed(1)} MB</span>
     </div>`
  ).join('');
}

function handleDocDrop(e) {
  e.preventDefault();
  document.getElementById('docDropZone').style.borderColor = 'var(--border)';
  const input = document.getElementById('docInput');
  // DataTransfer items → pseudo-assign
  showDocFiles(e.dataTransfer.files);
}

// ── AJAX form submission ──────────────────────────────────────
async function submitRegistration() {
  const form = document.getElementById('regForm');
  const pw   = document.getElementById('pwField').value;
  const cpw  = document.getElementById('cpwField').value;

  if (!pw || pw.length < 8) { HMS.toast('Password must be at least 8 characters.','warning'); return; }
  if (pw !== cpw)            { HMS.toast('Passwords do not match.','danger'); return; }

  const btn = document.getElementById('btnSubmit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Registering…';

  const fd = new FormData(form);
  // Attach photo file if selected
  const photoFile = document.getElementById('photoInput').files[0];
  if (photoFile) fd.set('avatar', photoFile);

  try {
    const res = await HMSAjax.post(APP_URL + '/api/patients.php', fd);
    if (res.success) {
      registeredPatCode = res.patient_code || document.getElementById('patCodePreview').value;
      registeredPatId   = res.patient_id   || 0;

      // Update print template
      document.getElementById('printName').textContent   = form.querySelector('[name=full_name]').value;
      document.getElementById('printCode').textContent   = registeredPatCode;
      document.getElementById('printGender').textContent = form.querySelector('[name=gender]').value;
      document.getElementById('printBlood').textContent  = form.querySelector('[name=blood_group]').value || '—';
      document.getElementById('printPhone').textContent  = form.querySelector('[name=phone]').value;

      // Show success modal
      document.getElementById('successPatCode').textContent = registeredPatCode;
      document.getElementById('successPatName').textContent =
        `${form.querySelector('[name=full_name]').value} has been registered successfully.`;

      document.getElementById('printCardBtn').disabled = false;
      new bootstrap.Modal(document.getElementById('successModal')).show();

    } else {
      HMS.toast(res.message || 'Registration failed.','danger');
    }
  } catch (err) {
    HMS.toast('Network error. Please try again.','danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-user-plus me-2"></i>Register Patient';
  }
}

// ── Print patient card ────────────────────────────────────────
function printPatientCard() {
  const tpl = document.getElementById('printTemplate').innerHTML;
  const win = window.open('', '_blank', 'width=420,height=300');
  win.document.write(`<!DOCTYPE html><html><head>
    <title>Patient Card — ${document.getElementById('printCode').textContent}</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
    <style>body{margin:20px;background:#f8fafc;font-family:'Plus Jakarta Sans',sans-serif;} @media print{body{margin:0;}}</style>
    </head><body>${tpl}<script>window.onload=()=>{window.print();window.close();}<\/script></body></html>`);
  win.document.close();
}

// ── Post-success actions ──────────────────────────────────────
function bookAppointmentNow() {
  bootstrap.Modal.getInstance(document.getElementById('successModal')).hide();
  window.location.href = APP_URL + '/receptionist/appointments.php';
}

function registerAnother() {
  bootstrap.Modal.getInstance(document.getElementById('successModal')).hide();
  resetForm();
}

// ── Reset ─────────────────────────────────────────────────────
function resetForm() {
  document.getElementById('regForm').reset();
  document.getElementById('ageDisplay').value = '';
  document.getElementById('photoPreview').innerHTML = '<i class="fa-solid fa-user" id="photoIcon"></i>';
  document.getElementById('removePhotoBtn').style.display = 'none';
  document.getElementById('docList').innerHTML = '';
  document.getElementById('emailMsg').innerHTML = '';
  document.getElementById('phoneMsg').innerHTML = '';
  document.getElementById('pwBar').style.width = '0';
  document.getElementById('pwText').textContent = '';
  document.getElementById('matchText').textContent = '';
  emailValid = false;
  gotoStep(0);
  refreshCode();
  focusFirst();
  HMS.toast('Form has been reset.','info');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
