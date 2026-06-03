<?php
// ============================================================
// includes/sidebar.php — Role-aware sidebar navigation
// ============================================================
$role = Auth::role();
$user = Auth::user();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function navLink(string $href, string $icon, string $label, string $currentDir, string $currentPage): string {
    $parts   = explode('/', trim($href, '/'));
    $active  = (end($parts) === $currentPage || (count($parts) >= 2 && $parts[count($parts)-2] === $currentDir && end($parts) === $currentPage));
    $cls     = $active ? 'active' : '';
    return "<li class=\"nav-item\">
      <a class=\"nav-link {$cls}\" href=\"{$href}\">
        <i class=\"{$icon} nav-icon\"></i>
        <span class=\"nav-label\">{$label}</span>
      </a>
    </li>";
}
?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="HMS.closeSidebar()"></div>

<!-- ═══ SIDEBAR ════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-logo">
      <i class="fa-solid fa-hospital-user"></i>
    </div>
    <div class="brand-text">
      <span class="brand-name">MediCare</span>
      <span class="brand-tagline">HMS v1.0</span>
    </div>
    <button class="btn-collapse d-lg-none" onclick="HMS.closeSidebar()">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <!-- User card -->
  <div class="sidebar-user">
    <div class="user-avatar">
      <?php if ($user['avatar']): ?>
        <img src="<?= APP_URL . '/assets/uploads/' . $user['avatar'] ?>" alt="avatar"/>
      <?php else: ?>
        <span><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
      <?php endif; ?>
      <span class="status-dot"></span>
    </div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="user-role badge-role badge-<?= $role ?>"><?= ucfirst(str_replace('_', ' ', $role)) ?></div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav" id="sidebarNav">
    <ul class="nav flex-column">

      <?php if ($role === 'admin'): ?>
      <li class="nav-section-title">Main</li>
      <?= navLink(APP_URL.'/admin/dashboard.php',      'fa-solid fa-gauge-high',      'Dashboard',      $currentDir, $currentPage) ?>
      <li class="nav-section-title">Manage</li>
      <?= navLink(APP_URL.'/admin/manage_patients.php','fa-solid fa-hospital-user',    'Patients',       $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/admin/manage_doctors.php', 'fa-solid fa-user-doctor',      'Doctors',        $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/admin/appointments.php',   'fa-solid fa-calendar-check',   'Appointments',   $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/admin/billing.php',        'fa-solid fa-file-invoice-dollar','Billing',      $currentDir, $currentPage) ?>
      <li class="nav-section-title">Clinical</li>
      <?= navLink(APP_URL.'/admin/pharmacy.php',       'fa-solid fa-pills',             'Pharmacy',       $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/admin/laboratory.php',     'fa-solid fa-flask',             'Laboratory',     $currentDir, $currentPage) ?>
      <li class="nav-section-title">Reports</li>
      <?= navLink(APP_URL.'/admin/reports.php',        'fa-solid fa-chart-line',        'Analytics',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/admin/settings.php',       'fa-solid fa-gear',              'Settings',       $currentDir, $currentPage) ?>
      <li class="nav-section-title">Users</li>
      <?= navLink(APP_URL.'/register.php',             'fa-solid fa-user-plus',         'Register User',  $currentDir, $currentPage) ?>

      <?php elseif ($role === 'doctor'): ?>
      <li class="nav-section-title">Main</li>
      <?= navLink(APP_URL.'/doctor/dashboard.php',     'fa-solid fa-gauge-high',        'Dashboard',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/doctor/appointments.php',  'fa-solid fa-calendar-check',    'Appointments',   $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/doctor/patients.php',      'fa-solid fa-hospital-user',     'My Patients',    $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/doctor/prescriptions.php', 'fa-solid fa-prescription',      'Prescriptions',  $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/doctor/schedule.php',      'fa-solid fa-clock',             'My Schedule',    $currentDir, $currentPage) ?>

      <?php elseif ($role === 'receptionist'): ?>
      <li class="nav-section-title">Main</li>
      <?= navLink(APP_URL.'/receptionist/dashboard.php',    'fa-solid fa-gauge-high',   'Dashboard',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/receptionist/registration.php', 'fa-solid fa-user-plus',    'Register Patient',$currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/receptionist/appointments.php', 'fa-solid fa-calendar-check','Appointments',  $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/receptionist/billing.php',      'fa-solid fa-receipt',      'Billing',        $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/register.php?role=patient',    'fa-solid fa-id-card-clip',  'New Patient Form',$currentDir, $currentPage) ?>

      <?php elseif ($role === 'pharmacist'): ?>
      <li class="nav-section-title">Main</li>
      <?= navLink(APP_URL.'/pharmacist/dashboard.php',  'fa-solid fa-gauge-high',       'Dashboard',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/pharmacist/medicines.php',  'fa-solid fa-capsules',         'Medicines',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/pharmacist/inventory.php',  'fa-solid fa-boxes-stacked',    'Inventory',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/pharmacist/sales.php',      'fa-solid fa-cart-shopping',    'Sales',          $currentDir, $currentPage) ?>

      <?php elseif ($role === 'lab_technician'): ?>
      <li class="nav-section-title">Main</li>
      <?= navLink(APP_URL.'/laboratory/dashboard.php',  'fa-solid fa-gauge-high',       'Dashboard',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/laboratory/tests.php',      'fa-solid fa-vials',            'Tests',          $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/laboratory/reports.php',    'fa-solid fa-file-medical',     'Reports',        $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/laboratory/uploads.php',    'fa-solid fa-upload',           'Upload Reports', $currentDir, $currentPage) ?>

      <?php elseif ($role === 'patient'): ?>
      <li class="nav-section-title">My Health</li>
      <?= navLink(APP_URL.'/patient/dashboard.php',     'fa-solid fa-gauge-high',       'Dashboard',      $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/patient/profile.php',       'fa-solid fa-id-card',          'My Profile',     $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/patient/appointments.php',  'fa-solid fa-calendar-check',   'Appointments',   $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/patient/prescriptions.php', 'fa-solid fa-prescription',     'Prescriptions',  $currentDir, $currentPage) ?>
      <?= navLink(APP_URL.'/patient/reports.php',       'fa-solid fa-file-waveform',    'Lab Reports',    $currentDir, $currentPage) ?>
      <?php endif; ?>

    </ul>
  </nav>

  <!-- Sidebar footer -->
  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/authentication/logout.php" class="logout-btn">
      <i class="fa-solid fa-right-from-bracket"></i>
      <span>Sign Out</span>
    </a>
  </div>
</aside>
