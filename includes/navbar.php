<?php
// ============================================================
// includes/navbar.php — Top navigation bar
// ============================================================
$user = Auth::user();
$unread = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
    [$user['id']]
)['cnt'] ?? 0;
?>

<header class="top-navbar" id="topNavbar">
  <!-- Hamburger -->
  <button class="navbar-toggler-btn" onclick="HMS.toggleSidebar()" aria-label="Toggle sidebar">
    <i class="fa-solid fa-bars"></i>
  </button>

  <!-- Page title slot (filled by each page via JS) -->
  <div class="navbar-title" id="navbarTitle">
    <span><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></span>
  </div>

  <!-- Right actions -->
  <div class="navbar-actions">

    <!-- Dark/light toggle -->
    <button class="nav-action-btn" id="themeToggle" onclick="HMS.toggleTheme()" title="Toggle theme">
      <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>

    <!-- Notifications -->
    <div class="dropdown">
      <button class="nav-action-btn position-relative" data-bs-toggle="dropdown" aria-expanded="false" id="notifBell">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unread > 0): ?>
        <span class="notif-badge"><?= $unread ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end notif-dropdown" id="notifDropdown">
        <div class="notif-header d-flex justify-content-between align-items-center">
          <span class="fw-600">Notifications</span>
          <a href="#" class="small text-primary mark-all-read" onclick="HMS.markAllRead(event)">Mark all read</a>
        </div>
        <div class="notif-list" id="notifList">
          <div class="notif-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>
        </div>
        <div class="notif-footer">
          <a href="#">View all notifications</a>
        </div>
      </div>
    </div>

    <!-- User menu -->
    <div class="dropdown">
      <button class="user-menu-btn" data-bs-toggle="dropdown" aria-expanded="false">
        <div class="user-avatar-sm">
          <?php if ($user['avatar']): ?>
            <img src="<?= APP_URL . '/assets/uploads/' . $user['avatar'] ?>" alt="avatar"/>
          <?php else: ?>
            <span><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
          <?php endif; ?>
        </div>
        <div class="user-details d-none d-md-block">
          <div class="user-name-sm"><?= htmlspecialchars($user['name']) ?></div>
          <div class="user-role-sm"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></div>
        </div>
        <i class="fa-solid fa-chevron-down ms-2 small"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end user-dropdown">
    <li>
        <div class="dropdown-user-info">
            <strong><?= htmlspecialchars($user['name']) ?></strong>
            <small class="text-muted d-block">
                <?= htmlspecialchars($user['email']) ?>
            </small>
        </div>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
        <a class="dropdown-item"
           href="<?= APP_URL ?>/<?= $user['role'] ?>/profile.php">
            <i class="fa-solid fa-user me-2"></i>My Profile
        </a>
    </li>

    <li>
        <a class="dropdown-item"
           href="<?= APP_URL ?>/<?= $user['role'] ?>/settings.php">
            <i class="fa-solid fa-gear me-2"></i>Settings
        </a>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
        <a class="dropdown-item text-danger"
           href="<?= APP_URL ?>/authentication/logout.php">
            <i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out
        </a>
    </li>
</ul>
    </div>
  </div>
</header>
