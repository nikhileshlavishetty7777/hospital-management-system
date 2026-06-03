<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('doctor');

$pageTitle = 'My Profile';

$user = Auth::user();

$doctor = Database::fetchOne("
    SELECT d.*, dep.name AS department_name
    FROM doctors d
    LEFT JOIN departments dep ON dep.id = d.department_id
    WHERE d.user_id = ?
", [$user['id']]);

require_once __DIR__ . '/../includes/header.php';
?>
<div id="appWrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="mainContent">

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="main-inner">

<div class="page-header">
    <h1>
        <i class="fa-solid fa-user-doctor text-primary me-2"></i>
        Doctor Profile
    </h1>
</div>

<div class="row">

<div class="col-lg-4">

<div class="card">
<div class="card-body text-center">

<div style="
width:100px;
height:100px;
border-radius:50%;
background:#0d6efd;
color:#fff;
font-size:36px;
display:flex;
align-items:center;
justify-content:center;
margin:auto;
">
<?= strtoupper(substr($user['name'],0,2)) ?>
</div>

<h4 class="mt-3">
<?= htmlspecialchars($user['name']) ?>
</h4>

<p class="text-muted">
<?= htmlspecialchars($doctor['specialization']) ?>
</p>

<span class="badge bg-success">
<?= ucfirst($doctor['status']) ?>
</span>

<hr>

<p>
<strong>Doctor ID:</strong><br>
<?= htmlspecialchars($doctor['doctor_code']) ?>
</p>

<p>
<strong>Department:</strong><br>
<?= htmlspecialchars($doctor['department_name']) ?>
</p>

</div>
</div>

</div>

<div class="col-lg-8">

<div class="card">
<div class="card-header">
Professional Information
</div>

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">
<label class="fw-bold">Email</label>
<input class="form-control"
value="<?= htmlspecialchars($user['email']) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">Qualification</label>
<input class="form-control"
value="<?= htmlspecialchars($doctor['qualification']) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">Specialization</label>
<input class="form-control"
value="<?= htmlspecialchars($doctor['specialization']) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">Experience</label>
<input class="form-control"
value="<?= $doctor['experience_years'] ?> Years"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">Consultation Fee</label>
<input class="form-control"
value="₹<?= number_format($doctor['consultation_fee'],2) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">License Number</label>
<input class="form-control"
value="<?= htmlspecialchars($doctor['license_number']) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="fw-bold">Available Days</label>
<input class="form-control"
value="<?= htmlspecialchars($doctor['available_days']) ?>"
readonly>
</div>

<div class="col-md-3 mb-3">
<label class="fw-bold">From</label>
<input class="form-control"
value="<?= $doctor['time_from'] ?>"
readonly>
</div>

<div class="col-md-3 mb-3">
<label class="fw-bold">To</label>
<input class="form-control"
value="<?= $doctor['time_to'] ?>"
readonly>
</div>

<div class="col-12">
<label class="fw-bold">Biography</label>
<textarea class="form-control"
rows="4"
readonly><?= htmlspecialchars($doctor['bio']) ?></textarea>
</div>

</div>

</div>
</div>

</div>

</div>

</main>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>