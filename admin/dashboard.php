<?php
// ============================================================
// admin/dashboard.php — Admin Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle   = 'Admin Dashboard';
$extraScripts = ['assets/js/charts.js', 'assets/js/dashboard.js'];

// ── Stats queries ────────────────────────────────────────────
$totalPatients   = Database::fetchOne("SELECT COUNT(*) AS c FROM patients")['c'];
$totalDoctors    = Database::fetchOne("SELECT COUNT(*) AS c FROM doctors")['c'];
$todayAppts      = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = CURDATE()")['c'];
$monthRevenue    = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];
$bedsOccupied    = Database::fetchOne("SELECT SUM(occupied_beds) AS c FROM wards")['c'];
$totalBeds       = Database::fetchOne("SELECT SUM(total_beds) AS c FROM wards")['c'];
$pharmacySales   = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()")['c'];
$pendingLabs     = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE status IN ('ordered','sample_collected','processing')")['c'];
$availableDoctors= Database::fetchOne("SELECT COUNT(*) AS c FROM doctors WHERE status='available'")['c'];
$newPatientsMonth= Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'];

// ── Recent appointments ──────────────────────────────────────
$recentAppts = Database::fetchAll("
    SELECT a.appointment_no, a.appointment_date, a.appointment_time, a.token_number, a.status, a.type,
           u_p.full_name AS patient_name, p.patient_code,
           u_d.full_name AS doctor_name,  d.specialization,
           dep.name AS dept_name
    FROM appointments a
    JOIN patients    p   ON p.id   = a.patient_id
    JOIN users       u_p ON u_p.id = p.user_id
    JOIN doctors     d   ON d.id   = a.doctor_id
    JOIN users       u_d ON u_d.id = d.user_id
    JOIN departments dep ON dep.id = a.department_id
    ORDER BY a.created_at DESC LIMIT 10
");

// ── Revenue last 7 months ────────────────────────────────────
$revenueData = Database::fetchAll("
    SELECT
        YEAR(created_at) AS yr,
        MONTH(created_at) AS mn,
        DATE_FORMAT(MIN(created_at), '%b %Y') AS month,
        SUM(paid) AS total
    FROM invoices
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY yr, mn
");

// ── Department patient distribution ─────────────────────────
$deptData = Database::fetchAll("
    SELECT dep.name, COUNT(a.id) AS cnt
    FROM appointments a
    JOIN departments dep ON dep.id = a.department_id
    WHERE MONTH(a.created_at) = MONTH(NOW())
    GROUP BY dep.id ORDER BY cnt DESC LIMIT 8
");

// ── Doctor availability ──────────────────────────────────────
$doctors = Database::fetchAll("
    SELECT u.full_name, d.specialization, d.status, dep.name AS dept,
           d.consultation_fee,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id=d.id AND appointment_date=CURDATE()) AS today_count
    FROM doctors d
    JOIN users u ON u.id=d.user_id
    JOIN departments dep ON dep.id=d.department_id
    ORDER BY d.status='available' DESC LIMIT 6
");

// ── Activity feed ────────────────────────────────────────────
$activity = Database::fetchAll("
    SELECT al.action, al.created_at, al.table_name, u.full_name, u.role
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC LIMIT 8
");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Welcome Banner -->
      <div class="welcome-banner animate-fade-in">
        <div>
          <?php
$hour = date('H');

if ($hour < 12) {
    $greeting = 'Morning';
} elseif ($hour < 17) {
    $greeting = 'Afternoon';
} else {
    $greeting = 'Evening';
}
?>

<div class="welcome-title">
    Good <?= $greeting ?>, <?= explode(' ', Auth::user()['name'])[1] ?? Auth::user()['name'] ?> 👋
</div>
          <div class="welcome-subtitle">Here's what's happening at the hospital today — <?= date('l, d F Y') ?></div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="badge bg-white text-primary fw-600"><i class="fa-solid fa-calendar-day me-1"></i><?= $todayAppts ?> appointments today</span>
            <span class="badge bg-white text-success fw-600"><i class="fa-solid fa-bed me-1"></i><?= $bedsOccupied ?>/<?= $totalBeds ?> beds occupied</span>
            <span class="badge bg-white text-warning fw-600"><i class="fa-solid fa-flask me-1"></i><?= $pendingLabs ?> pending labs</span>
          </div>
        </div>
        <i class="fa-solid fa-hospital welcome-icon"></i>
      </div>

      <!-- ── Stat Cards ── -->
      <div class="dashboard-grid mb-4">

        <div class="stat-card card-blue hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $totalPatients ?>">0</div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i>+<?= $newPatientsMonth ?> this month</div>
          </div>
        </div>

        <div class="stat-card card-purple hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $totalDoctors ?>">0</div>
            <div class="stat-label">Total Doctors</div>
            <div class="stat-change up"><i class="fa-solid fa-circle-check"></i><?= $availableDoctors ?> available now</div>
          </div>
        </div>

        <div class="stat-card card-orange hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $todayAppts ?>">0</div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-change up"><i class="fa-solid fa-clock"></i>Live queue active</div>
          </div>
        </div>

        <div class="stat-card card-green hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= number_format($monthRevenue, 0, '.', '') ?>" data-prefix="₹">0</div>
            <div class="stat-label">Revenue This Month</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i>+12.5% vs last month</div>
          </div>
        </div>

        <div class="stat-card card-red hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-bed"></i></div>
          <div>
            <div class="stat-value"><?= $bedsOccupied ?>/<?= $totalBeds ?></div>
            <div class="stat-label">Bed Occupancy</div>
            <div class="stat-change <?= ($bedsOccupied/$totalBeds)>0.8?'down':'up' ?>">
              <i class="fa-solid fa-percent"></i><?= $totalBeds>0?round(($bedsOccupied/$totalBeds)*100):0 ?>% occupied
            </div>
          </div>
        </div>

        <div class="stat-card card-indigo hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-pills"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= number_format($pharmacySales,0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label">Pharmacy Sales Today</div>
            <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i>Active</div>
          </div>
        </div>

        <div class="stat-card card-pink hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-flask"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $pendingLabs ?>">0</div>
            <div class="stat-label">Pending Lab Reports</div>
            <div class="stat-change <?= $pendingLabs>5?'down':'up' ?>"><i class="fa-solid fa-hourglass-half"></i>Awaiting results</div>
          </div>
        </div>

        <div class="stat-card card-teal hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $availableDoctors ?>">0</div>
            <div class="stat-label">Doctors Available</div>
            <div class="stat-change up"><i class="fa-solid fa-circle text-success"></i>Online now</div>
          </div>
        </div>

      </div><!-- /.dashboard-grid -->

      <!-- ── Charts Row ── -->
      <div class="row g-4 mb-4">

        <!-- Revenue Chart -->
        <div class="col-xl-8">
          <div class="chart-card">
            <div class="chart-card-header">
              <div>
                <div class="chart-card-title">Revenue Analytics</div>
                <div class="chart-card-subtitle">Monthly revenue overview — last 7 months</div>
              </div>
              <div class="chart-filter-group btn-group" id="revFilter">
                <button class="btn btn-sm btn-primary active" onclick="filterRev('month',this)">Monthly</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="filterRev('week',this)">Weekly</button>
              </div>
            </div>
            <div style="height:260px">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Department Pie -->
        <div class="col-xl-4">
          <div class="chart-card">
            <div class="chart-card-header">
              <div>
                <div class="chart-card-title">Department Distribution</div>
                <div class="chart-card-subtitle">Patients by dept this month</div>
              </div>
            </div>
            <div style="height:260px">
              <canvas id="deptChart"></canvas>
            </div>
          </div>
        </div>

      </div>

      <!-- ── Appointments + Activity Row ── -->
      <div class="row g-4 mb-4">

        <!-- Appointments Chart -->
        <div class="col-xl-6">
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">Appointment Trends</div>
              <div class="chart-card-subtitle">OPD / IPD / Emergency — last 7 days</div>
            </div>
            <div style="height:220px">
              <canvas id="apptChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Activity Timeline -->
        <div class="col-xl-6">
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">Activity Feed</div>
              <div class="chart-card-subtitle">Recent system actions</div>
            </div>
            <div class="timeline mt-2" style="max-height:220px;overflow-y:auto">
              <?php
              $actColors = ['LOGIN'=>'#22c55e','LOGOUT'=>'#64748b','LOGIN_FAILED'=>'#ef4444'];
              foreach ($activity as $i => $a):
                $color = $actColors[$a['action']] ?? '#0ea5e9';
                $ago   = (new DateTime($a['created_at']))->diff(new DateTime())->format('%hh %im ago');
              ?>
              <div class="timeline-item animate-fade-in-left delay-<?= ($i%8)+1 ?>">
                <div class="timeline-dot" style="background:<?= $color ?>">
                  <i class="fa-solid fa-bolt" style="font-size:6px"></i>
                </div>
                <div class="timeline-time"><?= htmlspecialchars($ago) ?></div>
                <div class="timeline-title">
                  <?= htmlspecialchars($a['action']) ?>
                  <?php if($a['full_name']): ?>— <span class="text-primary"><?= htmlspecialchars($a['full_name']) ?></span><?php endif; ?>
                </div>
                <div class="timeline-desc"><?= htmlspecialchars(ucfirst($a['role'] ?? 'system')) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- ── Doctor Availability ── -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-user-doctor me-2 text-primary"></i>Doctor Availability</span>
              <a href="<?= APP_URL ?>/admin/manage_doctors.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-3">
              <div class="row g-3">
                <?php foreach ($doctors as $i => $doc): ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                  <div class="doctor-card animate-scale-in delay-<?= $i+1 ?>">
                    <div class="doctor-avatar avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($doc['full_name'],3,2)) ?></div>
                    <div class="doctor-name"><?= htmlspecialchars($doc['full_name']) ?></div>
                    <div class="doctor-spec"><?= htmlspecialchars($doc['specialization']) ?></div>
                    <div class="mb-2">
                      <span class="badge" style="background:rgba(14,165,233,.1);color:#0ea5e9;font-size:10px">
                        <?= htmlspecialchars($doc['dept']) ?>
                      </span>
                    </div>
                    <div class="doctor-status <?= $doc['status']==='available'?'text-success':'text-warning' ?>">
                      <span class="dot" style="background:<?= $doc['status']==='available'?'#22c55e':'#f59e0b' ?>"></span>
                      <?= ucfirst(str_replace('_',' ',$doc['status'])) ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:11px">
                      <i class="fa-solid fa-calendar-check me-1"></i><?= $doc['today_count'] ?> today
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Recent Appointments Table ── -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Recent Appointments</span>
          <a href="<?= APP_URL ?>/admin/appointments.php" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i>Book New
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table hms-table mb-0" id="apptTable">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Appointment No</th>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Department</th>
                  <th>Date & Time</th>
                  <th>Token</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentAppts as $i => $apt): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><span class="text-mono fw-600 text-primary"><?= htmlspecialchars($apt['appointment_no']) ?></span></td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="user-avatar-sm avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($apt['patient_name'],0,2)) ?></div>
                      <div>
                        <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($apt['patient_name']) ?></div>
                        <div class="text-muted text-xs"><?= htmlspecialchars($apt['patient_code']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($apt['doctor_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($apt['specialization']) ?></div>
                  </td>
                  <td><?= htmlspecialchars($apt['dept_name']) ?></td>
                  <td>
                    <div><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div>
                    <div class="text-muted text-xs"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                  </td>
                  <td>
                    <span class="badge grad-primary">#<?= $apt['token_number'] ?></span>
                  </td>
                  <td>
                    <span class="badge bg-secondary"><?= strtoupper($apt['type']) ?></span>
                  </td>
                  <td>
                    <span class="status-badge status-<?= $apt['status'] ?>">
                      <?= ucfirst(str_replace('_',' ',$apt['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" title="View" onclick="viewAppt('<?= $apt['appointment_no'] ?>')">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-success" title="Edit">
                        <i class="fa-solid fa-pen"></i>
                      </button>
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

<?php
// Pass chart data to JS
$revLabels  = json_encode(array_column($revenueData, 'month'));
$revValues  = json_encode(array_column($revenueData, 'total'));
$deptLabels = json_encode(array_column($deptData, 'name'));
$deptValues = json_encode(array_column($deptData, 'cnt'));
$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', function() {
  // Revenue Chart
  HMSCharts.revenueChart('revenueChart', {$revLabels}, {$revValues});

  // Dept Pie
  HMSCharts.departmentPieChart('deptChart', {$deptLabels}, {$deptValues});

  // Appointments bar (sample data)
  const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  HMSCharts.appointmentsChart('apptChart', days);

  // Counter init
  HMS.initCounters();
});

function filterRev(type, btn) {
  document.querySelectorAll('#revFilter .btn').forEach(b => b.classList.remove('active','btn-primary'));
  document.querySelectorAll('#revFilter .btn').forEach(b => b.classList.add('btn-outline-secondary'));
  btn.classList.add('active','btn-primary');
  btn.classList.remove('btn-outline-secondary');
}

function viewAppt(no) {
  HMS.toast('Loading appointment ' + no + '...', 'info');
}
JS;

require_once __DIR__ . '/../includes/footer.php';
?>
