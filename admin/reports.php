<?php
// ============================================================
// admin/reports.php — Analytics & Reports Dashboard
// ============================================================
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle    = 'Analytics & Reports';
$extraScripts = ['assets/js/charts.js'];

// Revenue last 12 months
$revenueMonthly = Database::fetchAll("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           COALESCE(SUM(paid),0)           AS revenue,
           COUNT(*)                        AS invoice_count
    FROM invoices
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");

// Patient growth monthly
$patientGrowth = Database::fetchAll("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           COUNT(*) AS new_patients
    FROM patients
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");

// Department wise appointments
$deptAppts = Database::fetchAll("
    SELECT dep.name, dep.color, COUNT(a.id) AS total,
           SUM(a.status='completed') AS completed,
           SUM(a.status='cancelled') AS cancelled
    FROM departments dep
    LEFT JOIN appointments a ON a.department_id=dep.id
    GROUP BY dep.id ORDER BY total DESC
");

// Top doctors by appointments
$topDoctors = Database::fetchAll("
    SELECT u.full_name, d.specialization, COUNT(a.id) AS total,
           SUM(a.status='completed') AS completed,
           d.rating, d.consultation_fee
    FROM doctors d
    JOIN users u ON u.id=d.user_id
    LEFT JOIN appointments a ON a.doctor_id=d.id
    GROUP BY d.id ORDER BY total DESC LIMIT 8
");

// Payment method distribution
$paymentMethods = Database::fetchAll("
    SELECT payment_method, COUNT(*) AS cnt, SUM(paid) AS total
    FROM invoices GROUP BY payment_method ORDER BY cnt DESC
");

// Bed utilisation
$bedUtilisation = Database::fetchAll("
    SELECT name, ward_type, total_beds, occupied_beds,
           ROUND((occupied_beds/total_beds)*100,1) AS occupancy_pct
    FROM wards WHERE total_beds > 0 ORDER BY occupancy_pct DESC
");

// KPI summary
$kpis = [
    'total_revenue'   => Database::fetchOne("SELECT COALESCE(SUM(paid),0) AS c FROM invoices")['c'],
    'total_patients'  => Database::fetchOne("SELECT COUNT(*) AS c FROM patients")['c'],
    'total_appts'     => Database::fetchOne("SELECT COUNT(*) AS c FROM appointments")['c'],
    'avg_daily_appts' => Database::fetchOne("SELECT AVG(cnt) AS c FROM (SELECT DATE(appointment_date) AS d, COUNT(*) AS cnt FROM appointments GROUP BY d) t")['c'],
    'completed_rate'  => Database::fetchOne("SELECT ROUND(AVG(status='completed')*100,1) AS c FROM appointments")['c'],
    'avg_rating'      => Database::fetchOne("SELECT ROUND(AVG(rating),2) AS c FROM doctors WHERE total_ratings>0")['c'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <div id="mainContent">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="main-inner">

      <div class="page-header animate-fade-in-down">
        <div>
          <h1><i class="fa-solid fa-chart-line me-2 text-primary"></i>Analytics & Reports</h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active">Analytics</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">
          <select class="form-select form-select-sm" id="reportPeriod" style="width:140px" onchange="HMS.toast('Filtering…','info')">
            <option value="month">This Month</option>
            <option value="quarter">This Quarter</option>
            <option value="year">This Year</option>
            <option value="custom">Custom Range</option>
          </select>
          <button class="btn btn-primary ripple-btn" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i>Print Report
          </button>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
          <div class="stat-card card-green hover-lift text-center" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-indian-rupee-sign"></i></div>
            <div class="stat-value text-center" style="font-size:16px" data-counter="<?= number_format($kpis['total_revenue'],0,'.','') ?>" data-prefix="₹">0</div>
            <div class="stat-label text-center">Total Revenue</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-blue hover-lift" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-users"></i></div>
            <div class="stat-value text-center" style="font-size:16px" data-counter="<?= $kpis['total_patients'] ?>">0</div>
            <div class="stat-label text-center">Total Patients</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-purple hover-lift" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-value text-center" style="font-size:16px" data-counter="<?= $kpis['total_appts'] ?>">0</div>
            <div class="stat-label text-center">Total Appointments</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-orange hover-lift" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-calculator"></i></div>
            <div class="stat-value text-center" style="font-size:16px"><?= number_format((float)$kpis['avg_daily_appts'],1) ?></div>
            <div class="stat-label text-center">Avg Daily Appts</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-teal hover-lift" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-value text-center" style="font-size:16px"><?= $kpis['completed_rate'] ?>%</div>
            <div class="stat-label text-center">Completion Rate</div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="stat-card card-pink hover-lift" style="flex-direction:column;gap:8px;padding:16px 12px">
            <div class="stat-icon mx-auto" style="width:40px;height:40px;font-size:16px"><i class="fa-solid fa-star"></i></div>
            <div class="stat-value text-center" style="font-size:16px"><?= $kpis['avg_rating'] ?? '—' ?></div>
            <div class="stat-label text-center">Avg Doctor Rating</div>
          </div>
        </div>
      </div>

      <!-- Revenue Chart (full width) -->
      <div class="chart-card mb-4 animate-fade-in">
        <div class="chart-card-header">
          <div>
            <div class="chart-card-title">Revenue Trend — Last 12 Months</div>
            <div class="chart-card-subtitle">Monthly revenue collected</div>
          </div>
        </div>
        <div style="height:280px"><canvas id="revenueChart"></canvas></div>
      </div>

      <div class="row g-4 mb-4">
        <!-- Patient Growth -->
        <div class="col-xl-6">
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">Patient Growth</div>
              <div class="chart-card-subtitle">New registrations per month</div>
            </div>
            <div style="height:220px"><canvas id="patientChart"></canvas></div>
          </div>
        </div>

        <!-- Payment Methods -->
        <div class="col-xl-6">
          <div class="chart-card">
            <div class="chart-card-header">
              <div class="chart-card-title">Payment Methods</div>
              <div class="chart-card-subtitle">Revenue by payment type</div>
            </div>
            <div style="height:220px"><canvas id="paymentChart"></canvas></div>
          </div>
        </div>
      </div>

      <!-- Department + Bed Utilisation -->
      <div class="row g-4 mb-4">
        <div class="col-xl-6">
          <div class="card">
            <div class="card-header fw-700"><i class="fa-solid fa-hospital me-2 text-primary"></i>Department Performance</div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Department</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Rate</th></tr></thead>
                <tbody>
                  <?php foreach ($deptAppts as $dept): ?>
                  <tr>
                    <td>
                      <span class="d-flex align-items-center gap-2">
                        <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($dept['color']) ?>;flex-shrink:0"></span>
                        <?= htmlspecialchars($dept['name']) ?>
                      </span>
                    </td>
                    <td class="fw-700"><?= $dept['total'] ?></td>
                    <td class="text-success"><?= $dept['completed'] ?></td>
                    <td class="text-danger"><?= $dept['cancelled'] ?></td>
                    <td>
                      <?php $rate = $dept['total'] > 0 ? round(($dept['completed']/$dept['total'])*100) : 0; ?>
                      <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-1" style="height:6px">
                          <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                        </div>
                        <span class="text-xs fw-600"><?= $rate ?>%</span>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-6">
          <div class="card">
            <div class="card-header fw-700"><i class="fa-solid fa-bed me-2 text-primary"></i>Bed Utilisation</div>
            <div class="card-body p-0">
              <table class="table table-sm mb-0">
                <thead><tr><th>Ward</th><th>Type</th><th>Beds</th><th>Occupied</th><th>Occupancy</th></tr></thead>
                <tbody>
                  <?php foreach ($bedUtilisation as $ward): ?>
                  <tr>
                    <td class="fw-600 text-sm"><?= htmlspecialchars($ward['name']) ?></td>
                    <td><span class="badge bg-secondary text-xs"><?= strtoupper($ward['ward_type']) ?></span></td>
                    <td><?= $ward['total_beds'] ?></td>
                    <td class="fw-700"><?= $ward['occupied_beds'] ?></td>
                    <td>
                      <?php $pct = $ward['occupancy_pct']; $cls = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success'); ?>
                      <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-1" style="height:6px">
                          <div class="progress-bar bg-<?= $cls ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="text-xs fw-700 text-<?= $cls ?>"><?= $pct ?>%</span>
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

      <!-- Top Doctors -->
      <div class="card mb-4">
        <div class="card-header fw-700"><i class="fa-solid fa-trophy me-2 text-warning"></i>Top Performing Doctors</div>
        <div class="card-body p-0">
          <table class="table hms-table mb-0">
            <thead><tr><th>Rank</th><th>Doctor</th><th>Specialization</th><th>Total Appts</th><th>Completed</th><th>Completion Rate</th><th>Rating</th><th>Fee</th></tr></thead>
            <tbody>
              <?php foreach ($topDoctors as $i => $doc): ?>
              <tr>
                <td>
                  <?php if($i===0): ?><i class="fa-solid fa-trophy text-warning"></i>
                  <?php elseif($i===1): ?><i class="fa-solid fa-medal" style="color:#94a3b8"></i>
                  <?php elseif($i===2): ?><i class="fa-solid fa-award" style="color:#cd7f32"></i>
                  <?php else: ?><span class="text-muted fw-700"><?= $i+1 ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="user-avatar-sm avatar-<?= ($i%5)+1 ?>"><?= strtoupper(substr($doc['full_name'],3,2)) ?></div>
                    <span class="fw-600"><?= htmlspecialchars($doc['full_name']) ?></span>
                  </div>
                </td>
                <td class="text-muted text-sm"><?= htmlspecialchars($doc['specialization']) ?></td>
                <td class="fw-700"><?= $doc['total'] ?></td>
                <td class="text-success fw-700"><?= $doc['completed'] ?></td>
                <td>
                  <?php $rate = $doc['total']>0?round(($doc['completed']/$doc['total'])*100):0; ?>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-1" style="height:6px">
                      <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                    </div>
                    <span class="text-xs"><?= $rate ?>%</span>
                  </div>
                </td>
                <td>
                  <span class="text-warning fw-700"><i class="fa-solid fa-star text-xs me-1"></i><?= number_format($doc['rating'],1) ?></span>
                </td>
                <td class="fw-600">₹<?= number_format($doc['consultation_fee'],0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<?php
$revLabels      = json_encode(array_column($revenueMonthly,'month'));
$revValues      = json_encode(array_column($revenueMonthly,'revenue'));
$patLabels      = json_encode(array_column($patientGrowth,'month'));
$patValues      = json_encode(array_column($patientGrowth,'new_patients'));
$payLabels      = json_encode(array_column($paymentMethods,'payment_method'));
$payValues      = json_encode(array_column($paymentMethods,'total'));

$inlineScript = <<<JS
document.addEventListener('DOMContentLoaded', () => {
  HMS.initCounters();

  HMSCharts.revenueChart('revenueChart', {$revLabels}, {$revValues});

  HMSCharts.patientGrowthChart('patientChart', {$patLabels}, {$patValues},
    {$patValues}.map(v => Math.floor(v * 0.6)));

  HMSCharts.departmentPieChart('paymentChart',
    {$payLabels}.map(l => l.toUpperCase()),
    {$payValues}
  );
});
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
