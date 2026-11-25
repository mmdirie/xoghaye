<?php
// ==========================================================
// SCHOOL MANAGER - Application Entry Point
// ==========================================================
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_dashboard();
} else {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}
