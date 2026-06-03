<?php
// ============================================================
// patient/dashboard.php — Patient Portal Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('patient');

$pageTitle = 'My Health Portal';
$userId    = Auth::id();
$user      = Auth::user();

$patient = Database::fetchOne("SELECT * FROM patients WHERE user_id=?", [$userId]);
if (!$patient) { flash('danger','Patient profile not found.'); redirect(APP_URL.'/login.php'); }

$pid = $patient['id'];

// Stats
$upcomingAppts = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled','completed')", [$pid])['c'];
$completedAppts= Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE patient_id=? AND status='completed'", [$pid])['c'];
$labReports    = Database::fetchOne("SELECT COUNT(*) AS c FROM lab_orders WHERE patient_id=? AND status='completed'", [$pid])['c'];
$pendingBills  = Database::fetchOne("SELECT COALESCE(SUM(balance),0) AS c FROM invoices WHERE patient_id=? AND payment_status!='paid'", [$pid])['c'];

// Next appointment
$nextAppt = Database::fetchOne("
    SELECT a.*, u.full_name AS doctor_name, d.specialization, dep.name AS dept_name, dep.color
    FROM appointments a
    JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE a.patient_id=? AND a.appointment_date>=CURDATE() AND a.status NOT IN ('cancelled','completed')
    ORDER BY a.appointment_date, a.appointment_time LIMIT 1
", [$pid]);

// Recent appointments
$appointments = Database::fetchAll("
    SELECT a.appointment_no, a.appointment_date, a.appointment_time, a.token_number, a.status, a.type,
           u.full_name AS doctor_name, d.specialization, dep.name AS dept_name
    FROM appointments a
    JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE a.patient_id=?
    ORDER BY a.appointment_date DESC LIMIT 6
", [$pid]);

// Recent prescriptions
$prescriptions = Database::fetchAll("
    SELECT pr.prescription_no, pr.created_at, pr.status, pr.diagnosis, pr.follow_up_date,
           u.full_name AS doctor_name,
           GROUP_CONCAT(pi.medicine_name ORDER BY pi.id SEPARATOR ', ') AS medicines
    FROM prescriptions pr
    JOIN doctors d ON d.id=pr.doctor_id JOIN users u ON u.id=d.user_id
    LEFT JOIN prescription_items pi ON pi.prescription_id=pr.id
    WHERE pr.patient_id=?
    GROUP BY pr.id ORDER BY pr.created_at DESC LIMIT 5
", [$pid]);

// Lab orders
$labOrders = Database::fetchAll("
    SELECT lo.*, lt.name AS test_name, lt.category
    FROM lab_orders lo JOIN lab_tests lt ON lt.id=lo.test_id
    WHERE lo.patient_id=? ORDER BY lo.created_at DESC LIMIT 5
", [$pid]);

// Invoices
$invoices = Database::fetchAll("
    SELECT * FROM invoices WHERE patient_id=? ORDER BY created_at DESC LIMIT 5
", [$pid]);

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <!-- Patient welcome card -->
      <div class="welcome-banner animate-fade-in" style="background:linear-gradient(135deg,#06b6d4,#0ea5e9,#6366f1)">
        <div>
          <div class="welcome-title">Hello, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?> 👋</div>
          <div class="welcome-subtitle">Patient ID: <strong><?= htmlspecialchars($patient['patient_code']) ?></strong>
            <?php if($patient['blood_group']): ?> · Blood Group: <strong><?= htmlspecialchars($patient['blood_group']) ?></strong><?php endif; ?>
            <?php if($patient['allergies']): ?> · <span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Allergy: <?= htmlspecialchars($patient['allergies']) ?></span><?php endif; ?>
          </div>
          <div class="d-flex gap-3 mt-3 flex-wrap">
            <span class="badge bg-white text-primary fw-600"><i class="fa-solid fa-calendar me-1"></i><?= $upcomingAppts ?> upcoming appointments</span>
            <?php if($pendingBills > 0): ?>
            <span class="badge bg-warning text-dark fw-600"><i class="fa-solid fa-file-invoice me-1"></i>₹<?= number_format($pendingBills,0) ?> due</span>
            <?php endif; ?>
          </div>
        </div>
        <i class="fa-solid fa-user-injured welcome-icon"></i>
      </div>

      <!-- Stats -->
      <div class="dashboard-grid mb-4">
        <div class="stat-card card-blue hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $upcomingAppts ?>">0</div>
            <div class="stat-label">Upcoming Appointments</div>
          </div>
        </div>
        <div class="stat-card card-green hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $completedAppts ?>">0</div>
            <div class="stat-label">Completed Visits</div>
          </div>
        </div>
        <div class="stat-card card-purple hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-flask"></i></div>
          <div>
            <div class="stat-value" data-counter="<?= $labReports ?>">0</div>
            <div class="stat-label">Lab Reports Ready</div>
          </div>
        </div>
        <div class="stat-card card-red hover-lift">
          <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
          <div>
            <div class="stat-value" data-prefix="₹" data-counter="<?= number_format($pendingBills,0,'.','') ?>">0</div>
            <div class="stat-label">Pending Bills</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Next Appointment -->
        <div class="col-xl-4">
          <?php if($nextAppt): ?>
          <div class="card h-100" style="border-top:3px solid <?= htmlspecialchars($nextAppt['color']) ?>">
            <div class="card-header fw-700"><i class="fa-solid fa-calendar-day me-2 text-primary"></i>Next Appointment</div>
            <div class="card-body">
              <div class="queue-token mb-3" style="background:linear-gradient(135deg,<?= htmlspecialchars($nextAppt['color']) ?>,#6366f1)">
                <div class="queue-number">#<?= $nextAppt['token_number'] ?></div>
                <div class="queue-label">Your Token Number</div>
              </div>
              <div class="mb-3">
                <div class="fw-700 mb-1"><?= htmlspecialchars($nextAppt['doctor_name']) ?></div>
                <div class="text-muted text-xs"><?= htmlspecialchars($nextAppt['specialization']) ?> · <?= htmlspecialchars($nextAppt['dept_name']) ?></div>
              </div>
              <div class="d-flex gap-3 text-sm">
                <div><i class="fa-solid fa-calendar me-1 text-primary"></i><?= date('d M Y', strtotime($nextAppt['appointment_date'])) ?></div>
                <div><i class="fa-solid fa-clock me-1 text-primary"></i><?= date('h:i A', strtotime($nextAppt['appointment_time'])) ?></div>
              </div>
              <div class="mt-3">
                <span class="status-badge status-<?= $nextAppt['status'] ?>"><?= ucfirst(str_replace('_',' ',$nextAppt['status'])) ?></span>
              </div>
            </div>
          </div>
          <?php else: ?>
          <div class="card h-100">
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
              <i class="fa-solid fa-calendar-plus fa-3x text-primary mb-3 opacity-50"></i>
              <p class="fw-600">No upcoming appointments</p>
              <p class="text-muted small">Book an appointment to get started</p>
              <a href="<?= APP_URL ?>/patient/appointments.php" class="btn btn-primary mt-2">
                <i class="fa-solid fa-plus me-2"></i>Book Now
              </a>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Recent Appointments -->
        <div class="col-xl-8">
          <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="fa-solid fa-history me-2 text-primary"></i>Recent Appointments</span>
              <a href="<?= APP_URL ?>/patient/appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
              <?php if(empty($appointments)): ?>
              <div class="text-center p-4 text-muted">No appointments yet.</div>
              <?php else: ?>
              <table class="table table-sm mb-0">
                <thead><tr><th>Appt No</th><th>Doctor</th><th>Date</th><th>Type</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($appointments as $a): ?>
                  <tr>
                    <td class="text-mono fw-600 text-primary text-xs"><?= htmlspecialchars($a['appointment_no']) ?></td>
                    <td>
                      <div class="fw-600 text-sm"><?= htmlspecialchars($a['doctor_name']) ?></div>
                      <div class="text-muted text-xs"><?= htmlspecialchars($a['dept_name']) ?></div>
                    </td>
                    <td class="text-sm"><?= date('d M', strtotime($a['appointment_date'])) ?><br><small class="text-muted"><?= date('h:i A', strtotime($a['appointment_time'])) ?></small></td>
                    <td><span class="badge bg-secondary text-xs"><?= strtoupper($a['type']) ?></span></td>
                    <td><span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Prescriptions -->
        <div class="col-xl-6">
          <div class="card">
            <div class="card-header d-flex justify-content-between">
              <span><i class="fa-solid fa-prescription me-2 text-primary"></i>My Prescriptions</span>
              <a href="<?= APP_URL ?>/patient/prescriptions.php" class="btn btn-sm btn-outline-primary">All</a>
            </div>
            <div class="list-group list-group-flush">
              <?php if(empty($prescriptions)): ?>
              <div class="list-group-item text-muted text-sm text-center py-3">No prescriptions yet.</div>
              <?php else: ?>
              <?php foreach ($prescriptions as $rx): ?>
              <div class="list-group-item px-3 py-3">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($rx['doctor_name']) ?></div>
                    <div class="text-muted text-xs mt-1"><?= htmlspecialchars(substr($rx['medicines'] ?? 'No medicines listed',0,50)) ?>…</div>
                    <?php if($rx['diagnosis']): ?>
                    <div class="text-xs mt-1"><i class="fa-solid fa-stethoscope me-1 text-primary"></i><?= htmlspecialchars(substr($rx['diagnosis'],0,40)) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="text-end">
                    <span class="status-badge status-<?= $rx['status'] ?>"><?= ucfirst($rx['status']) ?></span>
                    <div class="text-muted text-xs mt-1"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
                    <?php if($rx['follow_up_date']): ?>
                    <div class="text-xs text-warning mt-1"><i class="fa-solid fa-calendar me-1"></i>Follow-up: <?= date('d M Y', strtotime($rx['follow_up_date'])) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Lab Reports -->
        <div class="col-xl-6">
          <div class="card">
            <div class="card-header d-flex justify-content-between">
              <span><i class="fa-solid fa-flask me-2 text-primary"></i>Lab Reports</span>
              <a href="<?= APP_URL ?>/patient/reports.php" class="btn btn-sm btn-outline-primary">All</a>
            </div>
            <div class="list-group list-group-flush">
              <?php if(empty($labOrders)): ?>
              <div class="list-group-item text-muted text-sm text-center py-3">No lab orders yet.</div>
              <?php else: ?>
              <?php foreach ($labOrders as $lo): ?>
              <div class="list-group-item px-3 py-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-600 text-sm"><?= htmlspecialchars($lo['test_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($lo['category']) ?> · <?= date('d M Y', strtotime($lo['created_at'])) ?></div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="status-badge status-<?= $lo['status']==='completed'?'completed':($lo['status']==='cancelled'?'cancelled':'waiting') ?>"><?= ucfirst(str_replace('_',' ',$lo['status'])) ?></span>
                    <?php if($lo['status']==='completed' && $lo['report_file']): ?>
                    <a href="<?= APP_URL.'/assets/uploads/'.htmlspecialchars($lo['report_file']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="fa-solid fa-download"></i>
                    </a>
                    <?php endif; ?>
                  </div>
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
$inlineScript = "document.addEventListener('DOMContentLoaded', () => { HMS.initCounters(); });";
require_once __DIR__ . '/../includes/footer.php';
?>
