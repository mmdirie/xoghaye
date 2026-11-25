<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Authentication, Session Security & University Calculation Utilities
// ==========================================================

require_once __DIR__ . '/../config/database.php';

// Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Check if a user is logged in
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user
 * 
 * @return array|null
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'    => $_SESSION['user_id'] ?? null,
        'full_name'  => $_SESSION['full_name'] ?? 'User',
        'email'      => $_SESSION['email'] ?? '',
        'role'       => $_SESSION['role'] ?? 'guest',
        'profile_id' => $_SESSION['profile_id'] ?? null,
        'extra'      => $_SESSION['profile_extra'] ?? null
    ];
}

/**
 * Require login route guard
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access the university management portal.');
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

/**
 * Restrict page access by role
 * 
 * @param string|array $allowed_roles
 */
function require_role($allowed_roles) {
    require_login();
    
    $user = current_user();
    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    
    if (!in_array($user['role'], $roles, true)) {
        set_flash('danger', 'Access denied. You do not have permission to access that academic area.');
        redirect_dashboard($user['role']);
        exit;
    }
}

/**
 * Redirect user to their respective dashboard
 * 
 * @param string|null $role
 */
function redirect_dashboard($role = null) {
    if (!$role && is_logged_in()) {
        $role = $_SESSION['role'];
    }

    switch ($role) {
        case 'admin':
            header('Location: ' . base_url('admin/dashboard.php'));
            break;
        case 'teacher':
            header('Location: ' . base_url('teacher/dashboard.php'));
            break;
        case 'student':
            header('Location: ' . base_url('student/dashboard.php'));
            break;
        default:
            header('Location: ' . base_url('auth/login.php'));
            break;
    }
    exit;
}

/**
 * Generate CSRF Token
 * 
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF field
 * 
 * @return string
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify CSRF Token
 * 
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash notification alerts
 * 
 * @param string $type success|danger|warning|info
 * @param string $message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render Flash Alert HTML
 * 
 * @return string
 */
function render_flash() {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }
    $icons = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        'info'    => 'bi-info-circle-fill'
    ];
    $icon = $icons[$flash['type']] ?? 'bi-info-circle-fill';

    return '
    <div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
        <i class="bi ' . $icon . ' me-2 fs-5"></i>
        <div>' . $flash['message'] . '</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

/**
 * Clean & sanitize user string input
 * 
 * @param mixed $input
 * @return string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return trim(htmlspecialchars((string)$input, ENT_QUOTES, 'UTF-8'));
}

/**
 * Standard University Grading Scale: Score (0-100) -> Letter Grade
 * 
 * @param float|int $score
 * @return string
 */
function calculate_letter_grade($score) {
    $score = (float)$score;
    if ($score >= 90) return 'A';
    if ($score >= 85) return 'A-';
    if ($score >= 80) return 'B+';
    if ($score >= 75) return 'B';
    if ($score >= 70) return 'B-';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'C-';
    if ($score >= 50) return 'D';
    return 'F';
}

/**
 * Standard University Grade Point (4.0 Scale)
 * 
 * @param string $letter
 * @return float
 */
function grade_point($letter) {
    $scale = [
        'A+' => 4.0, 'A'  => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B'  => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C'  => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D'  => 1.0, 'F'  => 0.0
    ];
    $letter = strtoupper(trim($letter));
    return $scale[$letter] ?? 0.0;
}

/**
 * Calculate University GPA for an array of graded courses
 * Each element should have: 'credit_hours' and 'letter_grade' (or 'grade_point')
 * GPA = Sum(Credit Hours * Grade Point) / Sum(Credit Hours)
 * 
 * @param array $grades
 * @return float
 */
function calculate_gpa($grades) {
    $total_quality_points = 0.0;
    $total_credits = 0;

    foreach ($grades as $g) {
        $credits = isset($g['credit_hours']) ? (int)$g['credit_hours'] : 3;
        if ($credits <= 0) $credits = 3;

        $gp = isset($g['grade_point']) ? (float)$g['grade_point'] : grade_point($g['letter_grade'] ?? 'F');
        $total_quality_points += ($credits * $gp);
        $total_credits += $credits;
    }

    if ($total_credits <= 0) {
        return 0.00;
    }

    return round($total_quality_points / $total_credits, 2);
}

/**
 * University Academic Standing / Honours Classification
 * 
 * @param float $cgpa
 * @return array ['title' => string, 'class' => string]
 */
function get_academic_standing($cgpa) {
    $cgpa = (float)$cgpa;
    if ($cgpa >= 3.80) {
        return ['title' => 'First Class Honours (Summa Cum Laude)', 'class' => 'text-success fw-bold'];
    } elseif ($cgpa >= 3.50) {
        return ['title' => 'Second Class Honours - Upper Division (Magna Cum Laude)', 'class' => 'text-primary fw-bold'];
    } elseif ($cgpa >= 3.00) {
        return ['title' => 'Second Class Honours - Lower Division (Cum Laude)', 'class' => 'text-info fw-bold'];
    } elseif ($cgpa >= 2.00) {
        return ['title' => 'Pass (Good Academic Standing)', 'class' => 'text-secondary fw-semibold'];
    } else {
        return ['title' => 'Academic Warning / Probation', 'class' => 'text-danger fw-bold'];
    }
}

/**
 * Format date display safely
 * 
 * @param string|null $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}
