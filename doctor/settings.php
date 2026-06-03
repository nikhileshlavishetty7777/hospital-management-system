<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'Settings';

$user = Auth::user();

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="mainContent">

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="main-inner">

<div class="page-header">
    <h1>
        <i class="fa-solid fa-gear me-2 text-primary"></i>
        Account Settings
    </h1>
</div>

<div class="row">

<!-- Profile Settings -->
<div class="col-lg-6">

<div class="card">
<div class="card-header">
<i class="fa-solid fa-user me-2"></i>
Profile Settings
</div>

<div class="card-body">

<form id="profileForm">

<div class="mb-3">
<label class="form-label">Full Name</label>
<input
type="text"
class="form-control"
name="name"
value="<?= htmlspecialchars($user['name']) ?>">
</div>

<div class="mb-3">
<label class="form-label">Email</label>
<input
type="email"
class="form-control"
name="email"
value="<?= htmlspecialchars($user['email']) ?>">
</div>

<div class="mb-3">
<label class="form-label">Phone</label>
<input
type="text"
class="form-control"
name="phone"
value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
</div>

<button type="button"
class="btn btn-primary">
<i class="fa-solid fa-save me-2"></i>
Save Changes
</button>

</form>

</div>
</div>

</div>

<!-- Password Settings -->
<div class="col-lg-6">

<div class="card">
<div class="card-header">
<i class="fa-solid fa-lock me-2"></i>
Change Password
</div>

<div class="card-body">

<form id="passwordForm">

<div class="mb-3">
<label class="form-label">Current Password</label>
<input
type="password"
class="form-control"
name="current_password">
</div>

<div class="mb-3">
<label class="form-label">New Password</label>
<input
type="password"
class="form-control"
name="new_password">
</div>

<div class="mb-3">
<label class="form-label">Confirm Password</label>
<input
type="password"
class="form-control"
name="confirm_password">
</div>

<button
type="button"
class="btn btn-danger">
<i class="fa-solid fa-key me-2"></i>
Update Password
</button>

</form>

</div>
</div>

</div>

</div>

<!-- System Settings -->
<div class="card mt-4">

<div class="card-header">
<i class="fa-solid fa-sliders me-2"></i>
Preferences
</div>

<div class="card-body">

<div class="form-check form-switch mb-3">
<input class="form-check-input"
type="checkbox"
id="darkMode">
<label class="form-check-label" for="darkMode">
Enable Dark Mode
</label>
</div>

<div class="form-check form-switch">
<input class="form-check-input"
type="checkbox"
id="emailNotif" checked>
<label class="form-check-label" for="emailNotif">
Receive Email Notifications
</label>
</div>

</div>

</div>

</main>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>