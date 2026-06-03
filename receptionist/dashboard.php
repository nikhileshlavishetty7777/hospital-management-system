<?php
// ============================================================
// receptionist/dashboard.php — Receptionist Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole(['receptionist','admin']);

$pageTitle = 'Receptionist Dashboard';

$todayAppts   = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE()")['c'];
$todayPending = Database::fetchOne("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date=CURDATE() AND status='booked'")['c'];
$todayBilling = Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices WHERE DATE(created_at)=CURDATE()")['c'];
$newPatients  = Database::fetchOne("SELECT COUNT(*) AS c FROM patients WHERE DATE(created_at)=CURDATE()")['c'];

// Queue
$queue = Database::fetchAll("
    SELECT a.token_number, a.appointment_time, a.status, a.type,
           u.full_name AS patient_name, p.patient_code,
           u_d.full_name AS doctor_name, dep.name AS dept_name
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id JOIN users u ON u.id=p.user_id
    JOIN doctors d ON d.id=a.doctor_id JOIN users u_d ON u_d.id=d.user_id
    JOIN departments dep ON dep.id=a.department_id
    WHERE a.appointment_date=CURDATE() AND a.status NOT IN ('cancelled','completed','no_show')
    ORDER BY a.token_number ASC LIMIT 12
");

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="welcome-banner" style="background:linear-gradient(135deg,#6366f1,#0ea5e9)">
        <div>
          <div class="welcome-title">Reception Desk 🏥</div>
          <div class="welcome-subtitle"><?= date('l, d F Y') ?> · Managing patient flow & registrations</div>
          <div class="d-flex gap-3 mt-3">
            <span class="badge bg-white text-primary fw-600"><i class="fa-solid fa-list-ol me-1"></i><?= $todayPending ?> in queue</span>
            <span class="badge bg-white text-success fw-600"><i class="fa-solid fa-user-plus me-1"></i><?= $newPatients ?> registered today</span>
          </div>
        </div>
        <i class="fa-solid fa-house-medical welcome-icon"></i>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="stat-card card-blue hover-lift"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayAppts ?>">0</div><div class="stat-label">Today's Appointments</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-orange hover-lift"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="stat-value" data-counter="<?= $todayPending ?>">0</div><div class="stat-label">In Queue</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-green hover-lift"><div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div><div class="stat-value" data-counter="<?= number_format($todayBilling,0,'.','') ?>" data-prefix="₹">0</div><div class="stat-label">Today's Billing</div></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card card-purple hover-lift"><div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div>
            <div><div class="stat-value" data-counter="<?= $newPatients ?>">0</div><div class="stat-label">New Patients Today</div></div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header fw-700"><i class="fa-solid fa-bolt me-2 text-primary"></i>Quick Actions</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-6 col-md-3">
                  <a href="<?= APP_URL ?>/receptionist/registration.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="fa-solid fa-user-plus fa-lg d-block mb-2"></i>Register Patient
                  </a>
                </div>
                <div class="col-6 col-md-3">
                  <a href="<?= APP_URL ?>/receptionist/appointments.php" class="btn btn-outline-success w-100 py-3">
                    <i class="fa-solid fa-calendar-plus fa-lg d-block mb-2"></i>Book Appointment
                  </a>
                </div>
                <div class="col-6 col-md-3">
                  <a href="<?= APP_URL ?>/receptionist/billing.php" class="btn btn-outline-warning w-100 py-3">
                    <i class="fa-solid fa-file-invoice-dollar fa-lg d-block mb-2"></i>Create Invoice
                  </a>
                </div>
                <div class="col-6 col-md-3">
                  <button class="btn btn-outline-info w-100 py-3" onclick="HMS.toast('Search coming soon','info')">
                    <i class="fa-solid fa-search fa-lg d-block mb-2"></i>Search Patient
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Queue -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fa-solid fa-list-ol me-2 text-primary"></i>Live Queue — Today</span>
          <span class="badge bg-primary"><?= count($queue) ?> active</span>
        </div>
        <div class="card-body p-0">
          <?php if(empty($queue)): ?>
          <div class="text-center p-5 text-muted"><i class="fa-solid fa-calendar-xmark fa-3x mb-3 opacity-25"></i><p>No active appointments in queue</p></div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Token</th><th>Patient</th><th>Doctor</th><th>Dept</th><th>Time</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($queue as $i => $q): ?>
                <tr class="animate-fade-in delay-<?= min($i+1,8) ?>">
                  <td><span class="badge grad-primary fw-800" style="font-size:16px">#<?= $q['token_number'] ?></span></td>
                  <td>
                    <div class="fw-600"><?= htmlspecialchars($q['patient_name']) ?></div>
                    <div class="text-muted text-xs"><?= htmlspecialchars($q['patient_code']) ?></div>
                  </td>
                  <td class="text-sm"><?= htmlspecialchars($q['doctor_name']) ?></td>
                  <td class="text-sm"><?= htmlspecialchars($q['dept_name']) ?></td>
                  <td><?= date('h:i A', strtotime($q['appointment_time'])) ?></td>
                  <td><span class="badge bg-secondary"><?= strtoupper($q['type']) ?></span></td>
                  <td><span class="status-badge status-<?= $q['status'] ?>"><?= ucfirst(str_replace('_',' ',$q['status'])) ?></span></td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary" onclick="HMS.toast('Checked in','success')"><i class="fa-solid fa-user-check"></i></button>
                      <button class="btn btn-sm btn-outline-warning" onclick="HMS.toast('Invoice loading…','info')"><i class="fa-solid fa-receipt"></i></button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$inlineScript = "document.addEventListener('DOMContentLoaded', () => HMS.initCounters());";
require_once __DIR__ . '/../includes/footer.php';
?>
