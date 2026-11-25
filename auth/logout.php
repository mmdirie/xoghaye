<?php
// ==========================================================
// SCHOOL MANAGER - User Authentication (Logout)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';

// Clear session data
$_SESSION = [];

// Delete session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh temporary session to store flash message
session_start();
set_flash('info', 'You have been signed out successfully.');
header('Location: ' . base_url('auth/login.php'));
exit;
