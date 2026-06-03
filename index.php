<?php
// ============================================================
// index.php — Entry point: redirect to dashboard or login
// ============================================================
require_once __DIR__ . '/config/config.php';

if (Auth::check()) {
    redirect(Auth::dashboardUrl());
} else {
    redirect(APP_URL . '/login.php');
}
