<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Portal Sign In
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect_dashboard();
}

$error = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Security session expired. Please submit the form again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $email_val = $email;

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your institutional email and password.';
        } else {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id']   = (int)$user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['profile_id'] = null;
                $_SESSION['profile_extra'] = null;

                if ($user['role'] === 'student') {
                    $stuStmt = $pdo->prepare("SELECT student_id, student_number FROM students WHERE user_id = :uid LIMIT 1");
                    $stuStmt->execute(['uid' => $user['user_id']]);
                    $stu = $stuStmt->fetch();
                    if ($stu) {
                        $_SESSION['profile_id'] = (int)$stu['student_id'];
                        $_SESSION['profile_extra'] = $stu['student_number'];
                    }
                } elseif ($user['role'] === 'teacher') {
                    $tchStmt = $pdo->prepare("SELECT teacher_id, department, title FROM teachers WHERE user_id = :uid LIMIT 1");
                    $tchStmt->execute(['uid' => $user['user_id']]);
                    $tch = $tchStmt->fetch();
                    if ($tch) {
                        $_SESSION['profile_id'] = (int)$tch['teacher_id'];
                        $_SESSION['profile_extra'] = $tch['department'];
                    }
                }

                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect_dashboard($user['role']);
            } else {
                $error = 'Invalid email address or password. Please verify your credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?php echo APP_NAME; ?></title>
    
    <!-- Google Fonts & Bootstrap 5 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h2 class="login-title"><?php echo APP_NAME; ?></h2>
        <p class="login-tagline"><?php echo APP_TAGLINE; ?></p>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1 small">
            UNIVERSITY PORTAL
        </span>
    </div>

    <div class="login-body-content">
        <!-- Flash alerts -->
        <?php echo render_flash(); ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="small"><?php echo htmlspecialchars($error); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation">
            <?php echo csrf_field(); ?>

            <div class="mb-3">
                <label for="loginEmail" class="form-label fw-semibold small text-dark">Institutional Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0 ps-0" id="loginEmail" name="email" 
                           placeholder="user@xoghaye.edu" value="<?php echo htmlspecialchars($email_val); ?>" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="loginPassword" class="form-label fw-semibold small text-dark mb-0">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="loginPassword" name="password" 
                           placeholder="Enter password" required>
                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="loginPassword" title="Show/Hide Password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg fs-6 py-2 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Access Portal
                </button>
            </div>
        </form>

        <!-- Official Administrator Access Quick Fill -->
        <div class="mt-4 pt-3 border-top">
            <div class="text-muted text-center small fw-semibold mb-2">
                <i class="bi bi-shield-check text-primary me-1"></i> Official System Access:
            </div>
            <div class="d-flex justify-content-center">
                <button type="button" class="quick-demo-pill" 
                        data-email="admin@xoghaye.edu" data-pass="Admin@123">
                    <i class="bi bi-shield-lock-fill text-danger me-1"></i> Administrator: admin@xoghaye.edu
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo base_url('assets/js/main.js'); ?>"></script>
</body>
</html>
