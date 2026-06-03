<?php
// ============================================================
// doctor/dashboard.php — Doctor Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle    = 'Doctor Dashboard';
$extraScripts = ['assets/js/charts.js'];

$userId = Auth::id();
$doctor = Database::fetchOne("
    SELECT d.*, dep.name AS dept_name, dep.color AS dept_color
    FROM doctors d
    JOIN departments dep ON dep.id = d.department_id
    WHERE d.user_id = ?
", [$userId]);

if (!$doctor) {
    flash('danger', 'Doctor profile not found. Contact administrator.');
    redirect(APP_URL . '/login.php');
}

$did = $doctor['id'];

// Stats
$todayAppts    = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE()", [$did])['c'];
$completedToday= Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status='completed'", [$did])['c'];
$pendingToday  = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id=? AND appointment_date=CURDATE() AND status IN ('booked','confirmed','waiting')", [$did])['c'];
$totalPatients = Database::fetchOne("SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id=?", [$did])['c'];
$monthEarnings = Database::fetchOne("
    SELECT COALESCE(SUM(i.paid),0) AS c FROM invoices i
    JOIN appointments a ON a.id = i.appointment_id
    WHERE a.doctor_id=? AND MONTH(i.created_at)=MONTH(NOW())
", [$did])['c'];

// Today's queue
$queue = Database::fetchAll("
    SELECT a.id, a.token_number, a.appointment_time, a.status, a.symptoms, a.type,
           u.full_name AS patient_name, p.patient_code, p.gender, p.dob, p.blood_group
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users    u ON u.id = p.user_id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
      AND a.status NOT IN ('cancelled','no_show')
    ORDER BY a.token_number
", [$did]);

// Recent prescriptions
$recentRx = Database::fetchAll("
    SELECT pr.*, u.full_name AS patient_name, p.patient_code, pr.created_at
    FROM prescriptions pr
    JOIN patients p ON p.id = pr.patient_id
    JOIN users    u ON u.id = p.user_id
    WHERE pr.doctor_id = ?
    ORDER BY pr.created_at DESC LIMIT 5
", [$did]);

// Weekly appointment data for chart
$weekData = Database::fetchAll("
    SELECT DATE_FORMAT(appointment_date,'%a') AS day, COUNT(*) AS cnt
    FROM appointments
    WHERE doctor_id=? AND appointment_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND CURDATE()
    GROUP BY appointment_date ORDER BY appointment_date
", [$did]);

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Welcome banner -->
      <div class="welcome-banner animate-fade-in" style="background:linear-gradient(135deg,#0ea5e9 0%,<?= htmlspecialchars($doctor['dept_color']) ?> 100%)">
        <div>
          <div class="welcome-title">Welcome, <?= htmlspecialchars(Auth::user()['name']) ?> 👨‍⚕️</div>
          <div class="welcome-subtitle"><?= htmlspecialchars($doctor['specialization']) ?> · <?= htmlspecialchars($doctor['dept_name']) ?></div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="badge bg-white text-dark fw-600">
              <i class="fa-solid fa-calendar-day me-1"></i><?= $todayAppts ?> patients today
            </span>
            <span class="badge bg-white fw-600" style="color:<?= htmlspecialchars($doctor['dept_color']) ?>">
              <i class="fa-solid fa-star me-1"></i>Rating: <?= number_format($doctor['rating'],1) ?>/5.0
            </span>
            <span class="badge bg-white text-success fw-600">
              <i class="fa-solid fa-check me-1"></i><?= $completedToday ?> completed
            </span>
          </div>
        </div>
        <i class="fa-solid fa-stethoscope welcome-icon"></i>
      </div>

      <!-- Stats -->
      <div class="dashboard-grid mb-4">
        <div class="stat-card card-blue hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $todayAppts ?>">0</div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-change up"><i class="fa-solid fa-hourglass-half"></i><?= $pendingToday ?> pending</div>
          </div>
        </div>
        <div class="stat-card card-green hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $completedToday ?>">0</div>
            <div class="stat-label">Completed Today</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i>On track</div>
          </div>
        </div>
        <div class="stat-card card-orange hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $totalPatients ?>">0</div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-change up"><i class="fa-solid fa-heart-pulse"></i>All time</div>
          </div>
        </div>
        <div class="stat-card card-purple hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= number_format($monthEarnings,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label">Earnings This Month</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i>Growing</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Today's Queue -->
        <div class="col-xl-7">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-list-ol me-2 text-primary"></i>Today's Patient Queue</span>
              <span class="badge bg-primary"><?= $todayAppts ?> total</span>
            </div>
            <div class="card-body p-0" style="max-height:420px;overflow-y:auto">
              <?php if (empty($queue)): ?>
                <div class="text-center p-5 text-muted">
                  <i class="fa-solid fa-calendar-xmark fa-3x mb-3 opacity-25"></i>
                  <p>No appointments scheduled for today</p>
                </div>
              <?php else: ?>
                <div class="list-group list-group-flush">
                  <?php foreach ($queue as $i => $q): ?>
                  <div class="list-group-item list-group-item-action px-4 py-3 animate-fade-in-left delay-<?= min($i+1,8) ?>">
                    <div class="d-flex align-items-center gap-3">
                      <!-- Token -->
                      <div class="text-center flex-shrink-0" style="width:48px">
                        <div class="fw-800" style="font-size:22px;color:var(--primary);line-height:1"><?= $q['token_number'] ?></div>
                        <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Token</div>
                      </div>
                      <!-- Avatar -->
                      <div class="user-avatar-sm avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($q['patient_name'],0,2)) ?></div>
                      <!-- Info -->
                      <div class="flex-1">
                        <div class="fw-700"><?= htmlspecialchars($q['patient_name']) ?></div>
                        <div class="text-muted text-xs d-flex gap-3 mt-1">
                          <span><?= htmlspecialchars($q['patient_code']) ?></span>
                          <span><?= ucfirst($q['gender']) ?></span>
                          <?php if($q['blood_group']): ?><span class="text-danger"><?= htmlspecialchars($q['blood_group']) ?></span><?php endif; ?>
                          <span><?= date('h:i A', strtotime($q['appointment_time'])) ?></span>
                        </div>
                        <?php if($q['symptoms']): ?>
                        <div class="text-xs text-muted mt-1" style="font-style:italic"><?= htmlspecialchars(substr($q['symptoms'],0,60)) ?>…</div>
                        <?php endif; ?>
                      </div>
                      <!-- Status + Actions -->
                      <div class="d-flex align-items-center gap-2">
                        <span class="status-badge status-<?= $q['status'] ?>"><?= ucfirst(str_replace('_',' ',$q['status'])) ?></span>
                        <button class="btn btn-sm btn-primary" onclick="startConsult(<?= $q['id'] ?>, '<?= htmlspecialchars($q['patient_name']) ?>')">
                          <i class="fa-solid fa-stethoscope"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Weekly Chart + Rx -->
        <div class="col-xl-5">
          <div class="chart-card mb-3">
            <div class="chart-card-header">
              <div class="chart-card-title">Weekly Appointments</div>
            </div>
            <div style="height:160px"><canvas id="weekChart"></canvas></div>
          </div>

          <!-- Recent Prescriptions -->
          <div class="card">
            <div class="card-header">
              <span><i class="fa-solid fa-prescription me-2 text-primary"></i>Recent Prescriptions</span>
            </div>
            <div class="list-group list-group-flush">
              <?php if (empty($recentRx)): ?>
              <div class="list-group-item text-muted text-sm">No prescriptions yet.</div>
              <?php else: ?>
              <?php foreach ($recentRx as $rx): ?>
              <div class="list-group-item px-3 py-2">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($rx['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($rx['patient_code']) ?> · <?= date('d M Y', strtotime($rx['created_at'])) ?></div>
                  </div>
                  <span class="status-badge status-<?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$weekLabels = json_encode(array_column($weekData, 'day'));
$weekValues = json_encode(array_column($weekData, 'cnt'));
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();
  HMSCharts.appointmentsChart('weekChart', {$weekLabels}, [{
    label:'Appointments', data:{$weekValues},
    backgroundColor:'rgba(14,165,233,.7)', borderRadius:6, borderSkipped:false
  }]);
});

function startConsult(apptId, patientName) {
  HMS.confirm('Start consultation with ' + patientName + '?', async () => {
    const res = await HMSAjax.put(APP_URL + '/api/appointments.php?id=' + apptId, { status: 'in_progress' });
    if (res.success) {
      HMS.toast('Consultation started!', 'success');
      setTimeout(() => location.reload(), 800);
    }
  });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
