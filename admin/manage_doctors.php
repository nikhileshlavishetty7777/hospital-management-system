<?php
// ============================================================
// admin/manage_doctors.php — Doctor Management
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle = 'Manage Doctors';

// Stats
$totalDoctors    = Database::fetchOne("SELECT COUNT(*) AS c FROM doctors")['c'];
$available       = Database::fetchOne("SELECT COUNT(*) AS c FROM doctors WHERE status='available'")['c'];
$onLeave         = Database::fetchOne("SELECT COUNT(*) AS c FROM doctors WHERE status='on_leave'")['c'];

// Doctor list
$doctors = Database::fetchAll("
    SELECT d.id, d.doctor_code, d.specialization, d.qualification,
           d.experience_years, d.consultation_fee, d.status, d.rating,
           d.available_days, d.time_from, d.time_to,
           u.full_name, u.email, u.phone, u.avatar, u.created_at,
           dep.name AS dept_name, dep.color AS dept_color,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id=d.id) AS total_appts,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id=d.id AND appointment_date=CURDATE()) AS today_appts
    FROM doctors d
    JOIN users u ON u.id=d.user_id
    JOIN departments dep ON dep.id=d.department_id
    ORDER BY d.status='available' DESC, u.full_name
");

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
          <h1><i class="fa-solid fa-user-doctor me-2 text-primary"></i>Doctor Management</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Doctors</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" onclick="HMS.toast('Export coming soon','info')">
            <i class="fa-solid fa-file-export me-2"></i>Export
          </button>
          <button class="btn btn-primary ripple-btn" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
            <i class="fa-solid fa-user-plus me-2"></i>Add Doctor
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
            <div><div class="stat-value" data-counter="<?= $totalDoctors ?>">0</div><div class="stat-label">Total Doctors</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-value" data-counter="<?= $available ?>">0</div><div class="stat-label">Available</div></div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
            <div><div class="stat-value" data-counter="<?= $onLeave ?>">0</div><div class="stat-label">On Leave</div></div>
          </div>
        </div>
      </div>

      <!-- View toggle: grid / table -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
          <select class="form-select form-select-sm" id="deptFilter" onchange="filterDoctors()" style="width:180px">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dep): ?>
            <option value="<?= htmlspecialchars($dep['name']) ?>"><?= htmlspecialchars($dep['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-select form-select-sm" id="statusFilter" onchange="filterDoctors()" style="width:140px">
            <option value="">All Status</option>
            <option value="available">Available</option>
            <option value="on_leave">On Leave</option>
            <option value="off_duty">Off Duty</option>
          </select>
        </div>
        <div class="btn-group">
          <button class="btn btn-sm btn-primary active" id="btnGrid" onclick="setView('grid')"><i class="fa-solid fa-grip me-1"></i>Grid</button>
          <button class="btn btn-sm btn-outline-secondary" id="btnList" onclick="setView('list')"><i class="fa-solid fa-list me-1"></i>List</button>
        </div>
      </div>

      <!-- Grid View -->
      <div id="gridView" class="row g-4 mb-4">
        <?php foreach ($doctors as $i => $doc): ?>
        <div class="col-xl-3 col-lg-4 col-md-6 doctor-filter-item"
             data-dept="<?= htmlspecialchars($doc['dept_name']) ?>"
             data-status="<?= $doc['status'] ?>">
          <div class="card hover-lift h-100 animate-scale-in delay-<?= min($i+1,8) ?>">
            <!-- Top color bar -->
            <div style="height:4px;background:<?= htmlspecialchars($doc['dept_color']) ?>"></div>
            <div class="card-body text-center p-4">
              <!-- Avatar -->
              <div class="mx-auto mb-3" style="
                width:72px;height:72px;border-radius:50%;
                background:linear-gradient(135deg,<?= htmlspecialchars($doc['dept_color']) ?>,#6366f1);
                display:grid;place-items:center;
                font-size:24px;font-weight:800;color:#fff;
                box-shadow:0 4px 16px <?= htmlspecialchars($doc['dept_color']) ?>55">
                <?= strtoupper(substr($doc['full_name'],3,2)) ?>
              </div>

              <h6 class="fw-800 mb-1"><?= htmlspecialchars($doc['full_name']) ?></h6>
              <div class="text-muted text-xs mb-2"><?= htmlspecialchars($doc['specialization']) ?></div>

              <div class="mb-2">
                <span class="badge" style="background:<?= htmlspecialchars($doc['dept_color']) ?>22;color:<?= htmlspecialchars($doc['dept_color']) ?>">
                  <?= htmlspecialchars($doc['dept_name']) ?>
                </span>
              </div>

              <!-- Rating stars -->
              <div class="mb-3">
                <?php
                $rating = (float)$doc['rating'];
                for ($s=1; $s<=5; $s++):
                  $cls = $s <= round($rating) ? 'text-warning' : 'text-muted opacity-25';
                ?>
                <i class="fa-solid fa-star <?= $cls ?>" style="font-size:11px"></i>
                <?php endfor; ?>
                <span class="text-muted text-xs ms-1">(<?= $doc['total_ratings'] ?>)</span>
              </div>

              <!-- Stats row -->
              <div class="row g-2 text-center mb-3">
                <div class="col-4">
                  <div class="fw-700 text-sm"><?= $doc['experience_years'] ?>y</div>
                  <div class="text-muted text-xs">Exp.</div>
                </div>
                <div class="col-4">
                  <div class="fw-700 text-sm">₹<?= number_format($doc['consultation_fee'],0) ?></div>
                  <div class="text-muted text-xs">Fee</div>
                </div>
                <div class="col-4">
                  <div class="fw-700 text-sm"><?= $doc['today_appts'] ?></div>
                  <div class="text-muted text-xs">Today</div>
                </div>
              </div>

              <!-- Status -->
              <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                <span class="doctor-status <?= $doc['status']==='available'?'text-success':'text-warning' ?>">
                  <span class="dot d-inline-block me-1" style="width:7px;height:7px;border-radius:50%;background:<?= $doc['status']==='available'?'#22c55e':'#f59e0b' ?>"></span>
                  <?= ucfirst(str_replace('_',' ',$doc['status'])) ?>
                </span>
              </div>

              <!-- Working days -->
              <div class="text-muted text-xs mb-3">
                <i class="fa-solid fa-clock me-1"></i>
                <?= date('h:i A', strtotime($doc['time_from'])) ?> — <?= date('h:i A', strtotime($doc['time_to'])) ?>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-1" onclick="viewDoctor(<?= $doc['id'] ?>)">
                  <i class="fa-solid fa-eye me-1"></i>View
                </button>
                <button class="btn btn-sm btn-outline-success flex-1" onclick="editDoctor(<?= $doc['id'] ?>)">
                  <i class="fa-solid fa-pen me-1"></i>Edit
                </button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- List View (hidden by default) -->
      <div id="listView" style="display:none">
        <div class="card animate-fade-in">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table hms-table mb-0">
                <thead>
                  <tr><th>#</th><th>Doctor</th><th>Code</th><th>Department</th><th>Specialization</th>
                  <th>Experience</th><th>Fee</th><th>Status</th><th>Today</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($doctors as $i => $doc): ?>
                  <tr class="doctor-filter-item" data-dept="<?= htmlspecialchars($doc['dept_name']) ?>" data-status="<?= $doc['status'] ?>">
                    <td><?= $i+1 ?></td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="user-avatar-sm avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($doc['full_name'],3,2)) ?></div>
                        <div>
                          <div class="fw-600"><?= htmlspecialchars($doc['full_name']) ?></div>
                          <div class="text-muted text-xs"><?= htmlspecialchars($doc['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td><span class="text-mono text-primary fw-600"><?= htmlspecialchars($doc['doctor_code']) ?></span></td>
                    <td><?= htmlspecialchars($doc['dept_name']) ?></td>
                    <td><?= htmlspecialchars($doc['specialization']) ?></td>
                    <td><?= $doc['experience_years'] ?> yrs</td>
                    <td>₹<?= number_format($doc['consultation_fee'],0) ?></td>
                    <td>
                      <span class="status-badge <?= $doc['status']==='available'?'status-completed':($doc['status']==='on_leave'?'status-waiting':'status-cancelled') ?>">
                        <?= ucfirst(str_replace('_',' ',$doc['status'])) ?>
                      </span>
                    </td>
                    <td><?= $doc['today_appts'] ?> appts</td>
                    <td>
                      <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewDoctor(<?= $doc['id'] ?>)"><i class="fa-solid fa-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success" onclick="editDoctor(<?= $doc['id'] ?>)"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-sm btn-outline-warning" onclick="toggleLeave(<?= $doc['id'] ?>,'<?= $doc['status'] ?>')"><i class="fa-solid fa-umbrella-beach"></i></button>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add New Doctor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addDoctorForm">
          <div class="row g-3">
            <div class="col-12"><h6 class="fw-700 text-primary"><i class="fa-solid fa-user me-2"></i>Personal Info</h6><hr class="my-2"/></div>
            <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required placeholder="Dr. Full Name"/></div>
            <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required/></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"/></div>
            <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required minlength="8"/></div>
            <div class="col-md-4"><label class="form-label">License No</label><input type="text" name="license_number" class="form-control" placeholder="Medical license"/></div>

            <div class="col-12 mt-2"><h6 class="fw-700 text-primary"><i class="fa-solid fa-stethoscope me-2"></i>Professional Info</h6><hr class="my-2"/></div>
            <div class="col-md-6">
              <label class="form-label">Department *</label>
              <select name="department_id" class="form-select" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $dep): ?>
                <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Specialization *</label><input type="text" name="specialization" class="form-control" required placeholder="e.g. Cardiologist"/></div>
            <div class="col-md-6"><label class="form-label">Qualification *</label><input type="text" name="qualification" class="form-control" required placeholder="e.g. MBBS, MD, DM"/></div>
            <div class="col-md-3"><label class="form-label">Experience (years)</label><input type="number" name="experience_years" class="form-control" min="0" value="0"/></div>
            <div class="col-md-3"><label class="form-label">Consultation Fee (₹)</label><input type="number" name="consultation_fee" class="form-control" min="0" value="500"/></div>

            <div class="col-12 mt-2"><h6 class="fw-700 text-primary"><i class="fa-solid fa-clock me-2"></i>Schedule</h6><hr class="my-2"/></div>
            <div class="col-12">
              <label class="form-label">Available Days</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="days[]" value="<?= $day ?>" id="day<?= $day ?>"
                         <?= in_array($day,['Mon','Tue','Wed','Thu','Fri'])?'checked':'' ?>>
                  <label class="form-check-label" for="day<?= $day ?>"><?= $day ?></label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-3"><label class="form-label">Time From</label><input type="time" name="time_from" class="form-control" value="09:00"/></div>
            <div class="col-md-3"><label class="form-label">Time To</label><input type="time" name="time_to" class="form-control" value="17:00"/></div>
            <div class="col-md-3">
              <label class="form-label">Slot Duration</label>
              <select name="slot_duration" class="form-select">
                <option value="15">15 min</option>
                <option value="20">20 min</option>
                <option value="30" selected>30 min</option>
                <option value="45">45 min</option>
                <option value="60">60 min</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label">Bio / About</label><textarea name="bio" class="form-control" rows="3" placeholder="Doctor's professional biography…"></textarea></div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary ripple-btn" onclick="submitDoctorForm()">
          <i class="fa-solid fa-user-plus me-2"></i>Add Doctor
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

function setView(v) {
  document.getElementById('gridView').style.display = v === 'grid' ? '' : 'none';
  document.getElementById('listView').style.display = v === 'list' ? '' : 'none';
  document.getElementById('btnGrid').className = 'btn btn-sm ' + (v==='grid'?'btn-primary active':'btn-outline-secondary');
  document.getElementById('btnList').className = 'btn btn-sm ' + (v==='list'?'btn-primary active':'btn-outline-secondary');
}

function filterDoctors() {
  const dept   = document.getElementById('deptFilter').value;
  const status = document.getElementById('statusFilter').value;
  document.querySelectorAll('.doctor-filter-item').forEach(el => {
    const d = el.dataset.dept   || '';
    const s = el.dataset.status || '';
    const show = (!dept || d === dept) && (!status || s === status);
    el.style.display = show ? '' : 'none';
    // hide parent col if card
    const col = el.closest('[class*="col-"]');
    if (col) col.style.display = show ? '' : 'none';
  });
}

async function submitDoctorForm() {
  const form = document.getElementById('addDoctorForm');
  const days = [...form.querySelectorAll('[name="days[]"]:checked')].map(c => c.value).join(',');
  const fd   = new FormData(form);
  fd.set('available_days', days);
  const res = await HMSAjax.post(APP_URL + '/api/doctors.php', fd);
  if (res.success) {
    HMS.toast(res.message, 'success');
    bootstrap.Modal.getInstance(document.getElementById('addDoctorModal')).hide();
    setTimeout(() => location.reload(), 900);
  }
}

function viewDoctor(id) { HMS.toast('Loading doctor profile #' + id, 'info'); }
function editDoctor(id) { HMS.toast('Opening editor for doctor #' + id, 'info'); }

async function toggleLeave(id, currentStatus) {
  const newStatus = currentStatus === 'on_leave' ? 'available' : 'on_leave';
  const label     = newStatus === 'on_leave' ? 'mark as On Leave' : 'mark as Available';
  HMS.confirm('Are you sure you want to ' + label + '?', async () => {
    const res = await HMSAjax.put(APP_URL + '/api/doctors.php?id=' + id, { status: newStatus });
    if (res.success) { HMS.toast('Status updated!', 'success'); setTimeout(() => location.reload(), 700); }
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
