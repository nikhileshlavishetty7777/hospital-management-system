<?php
// ============================================================
// doctor/schedule.php — Doctor Weekly Schedule
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'My Schedule';
$doctor    = Database::fetchOne("
    SELECT d.*, dep.name AS dept_name
    FROM doctors d JOIN departments dep ON dep.id=d.department_id
    WHERE d.user_id=?", [Auth::id()]);
if (!$doctor) redirect(APP_URL.'/login.php');
$did = $doctor['id'];

// Build 7-day calendar from today
$weekStart  = clean($_GET['week'] ?? date('Y-m-d'));
$weekStartDt= new DateTime($weekStart);
// Snap to Monday
$dow        = (int)$weekStartDt->format('N') - 1;
$weekStartDt->modify("-{$dow} days");
$weekEndDt  = clone $weekStartDt;
$weekEndDt->modify('+6 days');

// Appointments for this week
$appts = Database::fetchAll("
    SELECT a.appointment_date, a.appointment_time, a.token_number, a.status, a.type,
           u.full_name AS patient_name, p.patient_code
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id
    JOIN users    u ON u.id=p.user_id
    WHERE a.doctor_id=? AND a.appointment_date BETWEEN ? AND ?
      AND a.status NOT IN ('cancelled','no_show')
    ORDER BY a.appointment_date, a.appointment_time
", [$did, $weekStartDt->format('Y-m-d'), $weekEndDt->format('Y-m-d')]);

// Group by date
$byDate = [];
foreach ($appts as $a) $byDate[$a['appointment_date']][] = $a;

// Active leaves
$leaves = Database::fetchAll("
    SELECT * FROM doctor_leaves WHERE doctor_id=? AND status='approved'
    AND to_date >= CURDATE() ORDER BY from_date", [$did]);

// Weekly stats
$weekTotal    = count($appts);
$weekCompleted= count(array_filter($appts, fn($a) => $a['status']==='completed'));

$prevWeek = (clone $weekStartDt)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $weekStartDt)->modify('+7 days')->format('Y-m-d');

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-clock me-2 text-primary"></i>My Schedule</h1>
          <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/doctor/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Schedule</li>
          </ol></nav>
        </div>
        <button class="btn btn-outline-warning ripple-btn" data-bs-toggle="modal" data-bs-target="#leaveModal">
          <i class="fa-solid fa-umbrella-beach me-2"></i>Apply Leave
        </button>
      </div>

      <!-- Doctor info card -->
      <div class="welcome-banner animate-fade-in" style="background:linear-gradient(135deg,#0ea5e9,#6366f1)">
        <div>
          <div class="welcome-title"><?= htmlspecialchars(Auth::user()['name']) ?></div>
          <div class="welcome-subtitle"><?= htmlspecialchars($doctor['specialization']) ?> · <?= htmlspecialchars($doctor['dept_name']) ?></div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="badge bg-white text-dark fw-600"><i class="fa-solid fa-clock me-1"></i><?= date('h:i A',strtotime($doctor['time_from'])) ?> — <?= date('h:i A',strtotime($doctor['time_to'])) ?></span>
            <span class="badge bg-white text-dark fw-600"><i class="fa-solid fa-calendar me-1"></i><?= $doctor['available_days'] ?></span>
            <span class="badge bg-white text-dark fw-600"><i class="fa-solid fa-hourglass me-1"></i><?= $doctor['slot_duration'] ?> min slots</span>
            <span class="badge <?= $doctor['status']==='available'?'bg-success':'bg-warning text-dark' ?> fw-600"><?= ucfirst(str_replace('_',' ',$doctor['status'])) ?></span>
          </div>
        </div>
        <i class="fa-solid fa-calendar-days welcome-icon"></i>
      </div>

      <!-- Week stats -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-week"></i></div>
            <div><div class="stat-value" data-counter="<?= $weekTotal ?>">0</div><div class="stat-label">This Week Total</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="stat-value" data-counter="<?= $weekCompleted ?>">0</div><div class="stat-label">Completed</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="stat-value" data-counter="<?= $weekTotal ?>">0</div><div class="stat-label">Pending</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
            <div><div class="stat-value" data-counter="<?= count($leaves) ?>">0</div><div class="stat-label">Active Leaves</div></div>
          </div>
        </div>
      </div>

      <!-- Week navigation -->
      <div class="card mb-4">
        <div class="card-body py-3 d-flex justify-content-between align-items-center">
          <a href="?week=<?= $prevWeek ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chevron-left me-1"></i>Prev Week</a>
          <h6 class="fw-700 mb-0">
            <?= $weekStartDt->format('d M') ?> — <?= $weekEndDt->format('d M Y') ?>
          </h6>
          <a href="?week=<?= $nextWeek ?>" class="btn btn-outline-secondary btn-sm">Next Week<i class="fa-solid fa-chevron-right ms-1"></i></a>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="card animate-fade-in">
        <div class="card-header fw-700"><i class="fa-solid fa-calendar-days me-2 text-primary"></i>Weekly Calendar</div>
        <div class="card-body p-0">
          <div class="row g-0" style="min-height:400px">
            <?php
            $dayPtr = clone $weekStartDt;
            $dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            for ($d = 0; $d < 7; $d++):
              $dateStr  = $dayPtr->format('Y-m-d');
              $isToday  = $dateStr === date('Y-m-d');
              $dayAppts = $byDate[$dateStr] ?? [];
              $isOff    = !str_contains($doctor['available_days'], $dayPtr->format('D'));
            ?>
            <div class="col border-end <?= $d===6?'border-end-0':'' ?>" style="min-width:0">
              <!-- Day header -->
              <div class="p-2 text-center border-bottom <?= $isToday?'bg-primary text-white':($isOff?'bg-light':'') ?>">
                <div class="fw-700 text-xs text-uppercase"><?= $dayNames[$d] ?></div>
                <div class="fw-800" style="font-size:20px"><?= $dayPtr->format('d') ?></div>
                <div class="text-xs <?= $isToday?'text-white opacity-75':'text-muted' ?>"><?= $dayPtr->format('M') ?></div>
                <?php if($isOff): ?><div class="badge bg-secondary text-xs mt-1">Off</div><?php endif; ?>
              </div>
              <!-- Appointments -->
              <div class="p-2" style="min-height:160px">
                <?php if(empty($dayAppts) && !$isOff): ?>
                  <div class="text-center text-muted text-xs py-3">No appointments</div>
                <?php endif; ?>
                <?php foreach ($dayAppts as $apt): ?>
                <div class="mb-1 p-2 rounded text-xs animate-fade-in"
                     style="background:rgba(14,165,233,.1);border-left:3px solid var(--primary)">
                  <div class="fw-700"><?= date('h:i A',strtotime($apt['appointment_time'])) ?></div>
                  <div class="text-truncate"><?= htmlspecialchars($apt['patient_name']) ?></div>
                  <span class="status-badge status-<?= $apt['status'] ?>" style="font-size:9px;padding:2px 6px"><?= ucfirst(str_replace('_',' ',$apt['status'])) ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php $dayPtr->modify('+1 day'); endfor; ?>
          </div>
        </div>
      </div>

      <!-- Leave History -->
      <?php if (!empty($leaves)): ?>
      <div class="card mt-4">
        <div class="card-header fw-700"><i class="fa-solid fa-umbrella-beach me-2 text-warning"></i>Approved Leaves</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($leaves as $lv):
                $days = (new DateTime($lv['from_date']))->diff(new DateTime($lv['to_date']))->days + 1;
              ?>
              <tr>
                <td><?= date('d M Y',strtotime($lv['from_date'])) ?></td>
                <td><?= date('d M Y',strtotime($lv['to_date'])) ?></td>
                <td><?= $days ?></td>
                <td class="text-muted text-xs"><?= htmlspecialchars($lv['reason'] ?? '—') ?></td>
                <td><span class="status-badge status-<?= $lv['status']==='approved'?'completed':'booked' ?>"><?= ucfirst($lv['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<!-- Leave Application Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-800"><i class="fa-solid fa-umbrella-beach me-2 text-warning"></i>Apply for Leave</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">From Date *</label>
          <input type="date" id="leaveFrom" class="form-control" min="<?= date('Y-m-d') ?>"/></div>
        <div class="mb-3"><label class="form-label">To Date *</label>
          <input type="date" id="leaveTo" class="form-control" min="<?= date('Y-m-d') ?>"/></div>
        <div class="mb-3"><label class="form-label">Reason</label>
          <textarea id="leaveReason" class="form-control" rows="3" placeholder="Reason for leave…"></textarea></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning ripple-btn" onclick="submitLeave()"><i class="fa-solid fa-paper-plane me-2"></i>Submit Request</button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => HMS.initCounters());

async function submitLeave() {
  const from   = document.getElementById('leaveFrom').value;
  const to     = document.getElementById('leaveTo').value;
  const reason = document.getElementById('leaveReason').value;
  if (!from || !to) { HMS.toast('Please select both dates.','warning'); return; }
  if (to < from)    { HMS.toast('End date must be after start date.','warning'); return; }

  const res = await HMSAjax.post(APP_URL+'/ajax/appointments.php?action=apply_leave', {from_date:from, to_date:to, reason});
  if (res.success) {
    HMS.toast('Leave request submitted!','success');
    bootstrap.Modal.getInstance(document.getElementById('leaveModal')).hide();
  }
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
