<?php
// ============================================================
// includes/header.php — HTML <head> + opening <body>
// ============================================================
// Variables expected from the including page:
//   $pageTitle  (string)  — shown in <title>
//   $bodyClass  (string)  — optional extra classes on <body>
// ============================================================
$pageTitle = $pageTitle ?? 'MediCare HMS';
$bodyClass  = $bodyClass  ?? '';
$currentUser = Auth::user();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="MediCare Hospital Management System"/>
  <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"/>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/animations.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dashboard.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/responsive.css"/>

  <!-- Favicon -->
  <link rel="icon" href="<?= APP_URL ?>/assets/images/favicon.svg" type="image/svg+xml"/>
</head>
<body class="<?= $bodyClass ?>">

<?php if ($flash): ?>
<!-- Flash message rendered via JS after DOM ready -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  HMS.toast(<?= json_encode($flash['msg']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>
