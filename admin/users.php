<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - User Accounts Management (Admin)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'User Accounts Management';
$current_admin_id = current_user()['user_id'];

// Handle User Actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create New User Account
    if ($action === 'create') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'student';

        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (empty($password) || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['admin', 'teacher', 'student'])) $errors[] = 'Invalid role specified.';

        // Check email uniqueness
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :em LIMIT 1");
        $chk->execute(['em' => $email]);
        if ($chk->fetch()) {
            $errors[] = 'Email address is already in use.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (:fn, :em, :pwd, :role)");
                $ins->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash, 'role' => $role]);
                $new_uid = (int)$pdo->lastInsertId();

                // If teacher or student created here, create minimal corresponding record
                if ($role === 'teacher') {
                    $department = trim($_POST['department'] ?? 'General Academics');
                    $tIns = $pdo->prepare("INSERT INTO teachers (user_id, department) VALUES (:uid, :dept)");
                    $tIns->execute(['uid' => $new_uid, 'dept' => $department]);
                } elseif ($role === 'student') {
                    $stu_count = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                    $snum = 'XOG-' . date('Y') . '-' . str_pad($stu_count + 1, 3, '0', STR_PAD_LEFT);
                    $sIns = $pdo->prepare("INSERT INTO students (user_id, student_number) VALUES (:uid, :snum)");
                    $sIns->execute(['uid' => $new_uid, 'snum' => $snum]);
                }

                $pdo->commit();
                set_flash('success', 'User account for "' . $full_name . '" created successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Error creating user: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }

    // Action 2: Update User Account
    if ($action === 'update') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $new_pass  = $_POST['new_password'] ?? '';
        $role      = $_POST['role'] ?? '';

        $errors = [];
        if ($target_id <= 0) $errors[] = 'Invalid user ID.';
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!in_array($role, ['admin', 'teacher', 'student'])) $errors[] = 'Invalid role.';

        // Check email uniqueness excluding target
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :em AND user_id != :uid LIMIT 1");
        $chk->execute(['em' => $email, 'uid' => $target_id]);
        if ($chk->fetch()) {
            $errors[] = 'Email is already used by another user.';
        }

        if (empty($errors)) {
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    set_flash('danger', 'Password must be at least 6 characters.');
                    header('Location: ' . base_url('admin/users.php'));
                    exit;
                }
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $up = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, password = :pwd, role = :role WHERE user_id = :uid");
                $up->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash, 'role' => $role, 'uid' => $target_id]);
            } else {
                $up = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, role = :role WHERE user_id = :uid");
                $up->execute(['fn' => $full_name, 'em' => $email, 'role' => $role, 'uid' => $target_id]);
            }
            set_flash('success', 'User account updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }

    // Action 3: Delete User
    if ($action === 'delete') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id <= 0) {
            set_flash('danger', 'Invalid user ID.');
        } elseif ($target_id === (int)$current_admin_id) {
            set_flash('danger', 'Self-deletion blocked: You cannot delete your currently logged-in account.');
        } else {
            try {
                $pdo->beginTransaction();
                $userCheck = $pdo->prepare("SELECT role FROM users WHERE user_id = :uid FOR UPDATE");
                $userCheck->execute(['uid' => $target_id]);
                $target_role = $userCheck->fetchColumn();

                if (!$target_role) {
                    $pdo->rollBack();
                    set_flash('danger', 'User not found.');
                } else {
                    $related_count = 0;
                    if ($target_role === 'student') {
                        $related = $pdo->prepare("
                            SELECT
                                (SELECT COUNT(*) FROM enrollments e JOIN students s ON s.student_id = e.student_id WHERE s.user_id = :uid1) +
                                (SELECT COUNT(*) FROM attendance a JOIN students s ON s.student_id = a.student_id WHERE s.user_id = :uid2) +
                                (SELECT COUNT(*) FROM grades g JOIN students s ON s.student_id = g.student_id WHERE s.user_id = :uid3)
                        ");
                        $related->execute(['uid1' => $target_id, 'uid2' => $target_id, 'uid3' => $target_id]);
                        $related_count = (int)$related->fetchColumn();
                    } elseif ($target_role === 'teacher') {
                        $related = $pdo->prepare("
                            SELECT COUNT(*) FROM courses c
                            JOIN teachers t ON t.teacher_id = c.teacher_id
                            WHERE t.user_id = :uid
                        ");
                        $related->execute(['uid' => $target_id]);
                        $related_count = (int)$related->fetchColumn();
                    }

                    if ($related_count > 0) {
                        $pdo->rollBack();
                        $message = $target_role === 'student'
                            ? 'Cannot delete this user: the student profile has enrollment, attendance, or examination records. Preserve the academic history and remove those records only through their authorized management pages.'
                            : 'Cannot delete this user: one or more courses are assigned to this lecturer. Reassign the courses first.';
                        set_flash('danger', $message);
                    } else {
                        $del = $pdo->prepare("DELETE FROM users WHERE user_id = :uid");
                        $del->execute(['uid' => $target_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'User account deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'User not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this user: related records prevent deletion.'
                    : 'User deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }
}

// Role Filter
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$where = [];
$params = [];
if (!empty($role_filter) && in_array($role_filter, ['admin', 'teacher', 'student'])) {
    $where[] = "role = :role";
    $params['role'] = $role_filter;
}
$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$uStmt = $pdo->prepare("SELECT * FROM users $where_sql ORDER BY user_id DESC");
$uStmt->execute($params);
$users = $uStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">User Account Security & Directory</h4>
        <p class="text-muted small mb-0">Manage credentials, permissions, and roles for institutional administrators, lecturers, and students.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill me-1"></i> Create User Account
    </button>
</div>

<!-- Users Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-sm <?php echo empty($role_filter) ? 'btn-primary' : 'btn-light border'; ?>">
                All (<?php echo count($users); ?>)
            </a>
            <a href="<?php echo base_url('admin/users.php?role=admin'); ?>" class="btn btn-sm <?php echo ($role_filter === 'admin') ? 'btn-danger' : 'btn-light border'; ?>">
                Admins
            </a>
            <a href="<?php echo base_url('admin/users.php?role=teacher'); ?>" class="btn btn-sm <?php echo ($role_filter === 'teacher') ? 'btn-info text-white' : 'btn-light border'; ?>">
                Lecturers
            </a>
            <a href="<?php echo base_url('admin/users.php?role=student'); ?>" class="btn btn-sm <?php echo ($role_filter === 'student') ? 'btn-success' : 'btn-light border'; ?>">
                Students
            </a>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search accounts...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Account Holder</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Created On</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): 
                    $role_badge = 'badge-role-student';
                    if ($u['role'] === 'admin') $role_badge = 'badge-role-admin';
                    elseif ($u['role'] === 'teacher') $role_badge = 'badge-role-teacher';
                ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar-mini me-2" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                    <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($u['full_name']); ?>
                                        <?php if ((int)$u['user_id'] === $current_admin_id): ?>
                                            <span class="badge bg-secondary-subtle text-secondary small ms-1">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">ID: #<?php echo $u['user_id']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo htmlspecialchars($u['email']); ?></span>
                        </td>
                        <td>
                            <span class="user-role-badge <?php echo $role_badge; ?>">
                                <?php echo ($u['role'] === 'teacher') ? 'Lecturer' : ucfirst($u['role']); ?>
                            </span>
                        </td>
                        <td class="small text-muted">
                            <?php echo format_date($u['created_at']); ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-action-group">
                                <button type="button" class="btn btn-outline-secondary btn-edit-user"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                                        data-id="<?php echo $u['user_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($u['full_name']); ?>"
                                        data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                        data-role="<?php echo $u['role']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ((int)$u['user_id'] !== $current_admin_id): ?>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/users.php'); ?>"
                                            data-id-field="user_id"
                                            data-record-id="<?php echo $u['user_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="User Account: <?php echo htmlspecialchars($u['full_name']); ?>"
                                            data-message="Are you sure you want to delete this user? Their associated profiles and data will be deleted.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ((int)$u['user_id'] !== $current_admin_id): ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i> Create User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="Dr. Ahmed Hassan" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="user@xoghaye.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">System Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" id="new_user_role" required>
                            <option value="student">Student</option>
                            <option value="teacher">Lecturer / Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" value="Welcome@123" minlength="6" required>
                        <div class="form-text">Default: <code>Welcome@123</code></div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="edit_user_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_user_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Account Role <span class="text-danger">*</span></label>
                        <select name="role" id="edit_user_role" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Lecturer / Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reset Password (Optional)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep unchanged">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Populate Edit User Modal
    document.querySelectorAll('.btn-edit-user').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.getAttribute('data-id');
            document.getElementById('edit_user_name').value = this.getAttribute('data-name');
            document.getElementById('edit_user_email').value = this.getAttribute('data-email');
            document.getElementById('edit_user_role').value = this.getAttribute('data-role');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
