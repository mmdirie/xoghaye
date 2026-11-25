<?php
// ==========================================================
// SCHOOL MANAGER - User Profile & Security Settings
// ==========================================================
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$pdo = get_db_connection();
$page_title = 'Account Profile & Security';

// Fetch full user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid LIMIT 1");
$stmt->execute(['uid' => $user['user_id']]);
$user_record = $stmt->fetch();

// Fetch role-specific record
$extra_data = [];
if ($user['role'] === 'student') {
    $s_stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
    $s_stmt->execute(['uid' => $user['user_id']]);
    $extra_data = $s_stmt->fetch() ?: [];
} elseif ($user['role'] === 'teacher') {
    $t_stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
    $t_stmt->execute(['uid' => $user['user_id']]);
    $extra_data = $t_stmt->fetch() ?: [];
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('profile.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Details
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($full_name)) {
            set_flash('danger', 'Full name cannot be empty.');
        } else {
            // Update users table
            $upStmt = $pdo->prepare("UPDATE users SET full_name = :fn WHERE user_id = :uid");
            $upStmt->execute(['fn' => $full_name, 'uid' => $user['user_id']]);
            $_SESSION['full_name'] = $full_name;

            // Update role specific table
            if ($user['role'] === 'student') {
                $address = trim($_POST['address'] ?? '');
                $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $upStu = $pdo->prepare("UPDATE students SET phone = :phone, address = :addr, date_of_birth = :dob WHERE user_id = :uid");
                $upStu->execute(['phone' => $phone, 'addr' => $address, 'dob' => $dob, 'uid' => $user['user_id']]);
            } elseif ($user['role'] === 'teacher') {
                $department = trim($_POST['department'] ?? '');
                $upTch = $pdo->prepare("UPDATE teachers SET phone = :phone, department = :dept WHERE user_id = :uid");
                $upTch->execute(['phone' => $phone, 'dept' => $department, 'uid' => $user['user_id']]);
                $_SESSION['profile_extra'] = $department;
            }

            set_flash('success', 'Profile details updated successfully.');
            header('Location: ' . base_url('profile.php'));
            exit;
        }
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            set_flash('danger', 'All password fields are required.');
        } elseif (!password_verify($current_pass, $user_record['password'])) {
            set_flash('danger', 'Incorrect current password entered.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('danger', 'The new passwords do not match.');
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $passStmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE user_id = :uid");
            $passStmt->execute(['pwd' => $hashed, 'uid' => $user['user_id']]);

            set_flash('success', 'Your password has been changed securely.');
            header('Location: ' . base_url('profile.php'));
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <!-- User Overview Card -->
    <div class="col-lg-4">
        <div class="card-modern text-center p-4">
            <div class="user-avatar-mini mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <?php echo strtoupper(substr($user_record['full_name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user_record['full_name']); ?></h4>
            <div class="text-muted small mb-2"><?php echo htmlspecialchars($user_record['email']); ?></div>
            <div>
                <span class="user-role-badge <?php 
                    echo ($user_record['role'] === 'admin') ? 'badge-role-admin' : (($user_record['role'] === 'teacher') ? 'badge-role-teacher' : 'badge-role-student'); 
                ?> px-3 py-1 fs-6">
                    <?php echo ucfirst($user_record['role']); ?>
                </span>
            </div>

            <hr class="my-4">

            <div class="text-start small">
                <?php if ($user['role'] === 'student' && !empty($extra_data['student_number'])): ?>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Student Number:</span>
                        <strong class="text-primary"><?php echo htmlspecialchars($extra_data['student_number']); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($user['role'] === 'teacher' && !empty($extra_data['department'])): ?>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Department:</span>
                        <strong><?php echo htmlspecialchars($extra_data['department']); ?></strong>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Account ID:</span>
                    <span>#<?php echo (int)$user_record['user_id']; ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Member Since:</span>
                    <span><?php echo format_date($user_record['created_at']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile & Password Form Panels -->
    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="card-modern mb-4">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-person-gear text-primary"></i> Edit Profile Information
                </h2>
            </div>
            <div class="card-modern-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_record['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" class="form-control bg-light" 
                                   value="<?php echo htmlspecialchars($user_record['email']); ?>" disabled>
                            <div class="form-text">Institutional emails are fixed by the system.</div>
                        </div>

                        <?php if ($user['role'] === 'student'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Phone Number</label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($extra_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" 
                                       value="<?php echo htmlspecialchars($extra_data['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Residential Address</label>
                                <textarea name="address" rows="2" class="form-control"><?php echo htmlspecialchars($extra_data['address'] ?? ''); ?></textarea>
                            </div>
                        <?php elseif ($user['role'] === 'teacher'): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Department</label>
                                <input type="text" name="department" class="form-control" 
                                       value="<?php echo htmlspecialchars($extra_data['department'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Phone Number</label>
                                <input type="text" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($extra_data['phone'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-key-fill text-warning"></i> Change Password
                </h2>
            </div>
            <div class="card-modern-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-md-12">
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
                            <i class="bi bi-shield-lock me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
