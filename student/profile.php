<?php
// ==========================================================
// SCHOOL MANAGER - Student Personal Profile
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = get_db_connection();
$page_title = 'My Student Profile';
$user = current_user();

// Fetch student profile record
$sStmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.created_at AS account_date
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = :uid
    LIMIT 1
");
$sStmt->execute(['uid' => $user['user_id']]);
$student = $sStmt->fetch();

if (!$student) {
    set_flash('danger', 'Profile record not found.');
    header('Location: ' . base_url('student/dashboard.php'));
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('student/profile.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Update Contact Information
    if ($action === 'update_contact') {
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $up = $pdo->prepare("UPDATE students SET phone = :phone, address = :addr WHERE student_id = :sid");
        $up->execute(['phone' => $phone, 'addr' => $address, 'sid' => $student['student_id']]);

        set_flash('success', 'Your contact details have been updated successfully.');
        header('Location: ' . base_url('student/profile.php'));
        exit;
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        // Fetch password hash
        $pwdStmt = $pdo->prepare("SELECT password FROM users WHERE user_id = :uid");
        $pwdStmt->execute(['uid' => $user['user_id']]);
        $curr_hash = $pwdStmt->fetchColumn();

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            set_flash('danger', 'All password fields are required.');
        } elseif (!password_verify($current_pass, $curr_hash)) {
            set_flash('danger', 'Current password entered is incorrect.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('danger', 'New passwords do not match.');
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $upPwd = $pdo->prepare("UPDATE users SET password = :pwd WHERE user_id = :uid");
            $upPwd->execute(['pwd' => $new_hash, 'uid' => $user['user_id']]);

            set_flash('success', 'Password successfully changed.');
            header('Location: ' . base_url('student/profile.php'));
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Profile Card Summary -->
    <div class="col-lg-4">
        <div class="card-modern text-center p-4">
            <div class="user-avatar-mini mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.2rem; background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
            <div class="badge bg-primary fs-6 mb-2"><?php echo htmlspecialchars($student['student_number']); ?></div>
            <div class="text-muted small mb-3"><?php echo htmlspecialchars($student['email']); ?></div>

            <hr>

            <div class="text-start small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Student Number:</span>
                    <strong class="text-primary"><?php echo htmlspecialchars($student['student_number']); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Date of Birth:</span>
                    <strong><?php echo format_date($student['date_of_birth']); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Phone:</span>
                    <strong><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Account Created:</span>
                    <span><?php echo format_date($student['account_date']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Forms -->
    <div class="col-lg-8">
        <!-- Contact Information Form -->
        <div class="card-modern mb-4">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-person-lines-fill text-primary"></i> Personal Contact Details
                </h2>
            </div>
            <div class="card-modern-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_contact">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['full_name']); ?>" disabled>
                            <div class="form-text">Name changes must be requested through the Registrar.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Student Number</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($student['student_number']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Institutional Email</label>
                            <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Residential Address</label>
                            <textarea name="address" rows="2" class="form-control"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Update Contact Details
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security / Password Form -->
        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-shield-lock-fill text-warning"></i> Change Password
                </h2>
            </div>
            <div class="card-modern-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">New Password</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                            <div class="form-text">Must be at least 6 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
