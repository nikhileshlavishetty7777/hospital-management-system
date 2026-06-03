<?php
require_once __DIR__ . '/../config/config.php';
Auth::requireRole('admin');

$pageTitle = 'My Profile';
$user = Auth::user();

require_once __DIR__ . '/../includes/header.php';
?>

<div id="appWrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div id="mainContent">
        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

        <main class="main-inner">
            <div class="container-fluid">

                <div class="page-header mb-4">
                    <h1>
                        <i class="fa-solid fa-user-shield me-2 text-primary"></i>
                        Admin Profile
                    </h1>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body text-center">

                        <div class="mb-3">
                            <div style="
                                width:100px;
                                height:100px;
                                border-radius:50%;
                                background:#0d6efd;
                                color:#fff;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:32px;
                                font-weight:bold;
                                margin:auto;
                            ">
                                <?= strtoupper(substr($user['name'],0,2)) ?>
                            </div>
                        </div>

                        <h3><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="text-muted">
                            <?= htmlspecialchars($user['email']) ?>
                        </p>

                        <hr>

                        <div class="row text-start">
                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Name</label>
                                <input type="text"
                                       class="form-control"
                                       value="<?= htmlspecialchars($user['name']) ?>"
                                       readonly>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Email</label>
                                <input type="text"
                                       class="form-control"
                                       value="<?= htmlspecialchars($user['email']) ?>"
                                       readonly>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Role</label>
                                <input type="text"
                                       class="form-control"
                                       value="<?= ucfirst($user['role']) ?>"
                                       readonly>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="fw-bold">Status</label>
                                <input type="text"
                                       class="form-control"
                                       value="<?= ucfirst($user['status'] ?? 'Active') ?>"
                                       readonly>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>