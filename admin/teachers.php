<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Lecturer & Academic Faculty Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Lecturer & Faculty Staff Management';

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/teachers.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Add New Lecturer
    if ($action === 'create') {
        $title         = trim($_POST['title'] ?? 'Lecturer');
        $full_name     = trim($_POST['full_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $password      = $_POST['password'] ?? '';
        $faculty_id    = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $department    = trim($_POST['department_name_text'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');

        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
        if (empty($password) || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

        // Check email uniqueness
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = :em LIMIT 1");
        $chk->execute(['em' => $email]);
        if ($chk->fetch()) {
            $errors[] = 'Email is already registered in system.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (:fn, :em, :pwd, 'teacher')");
                $uStmt->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash]);
                $user_id = (int)$pdo->lastInsertId();

                $tStmt = $pdo->prepare("
                    INSERT INTO teachers (user_id, title, faculty_id, department_id, department, phone) 
                    VALUES (:uid, :title, :fid, :did, :dept, :phone)
                ");
                $tStmt->execute([
                    'uid'   => $user_id,
                    'title' => $title,
                    'fid'   => $faculty_id,
                    'did'   => $department_id,
                    'dept'  => $department,
                    'phone' => $phone
                ]);

                $pdo->commit();
                set_flash('success', 'Lecturer "' . htmlspecialchars($title . ' ' . $full_name) . '" registered successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Error adding lecturer: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/teachers.php'));
        exit;
    }

    // Action 2: Update Existing Lecturer
    if ($action === 'update') {
        $teacher_id    = (int)($_POST['teacher_id'] ?? 0);
        $title         = trim($_POST['title'] ?? 'Lecturer');
        $full_name     = trim($_POST['full_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $new_pass      = $_POST['new_password'] ?? '';
        $faculty_id    = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $department    = trim($_POST['department_name_text'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');

        $tCheck = $pdo->prepare("SELECT user_id FROM teachers WHERE teacher_id = :tid LIMIT 1");
        $tCheck->execute(['tid' => $teacher_id]);
        $user_id = $tCheck->fetchColumn();

        if ($user_id) {
            $errors = [];
            if (empty($full_name)) $errors[] = 'Full name is required.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

            // Check email uniqueness excluding self
            $chkEm = $pdo->prepare("SELECT user_id FROM users WHERE email = :em AND user_id != :uid LIMIT 1");
            $chkEm->execute(['em' => $email, 'uid' => $user_id]);
            if ($chkEm->fetch()) {
                $errors[] = 'Email is already used by another account.';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();
                    if (!empty($new_pass)) {
                        if (strlen($new_pass) < 6) throw new Exception('Password must be at least 6 characters.');
                        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                        $uUp = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, password = :pwd WHERE user_id = :uid");
                        $uUp->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash, 'uid' => $user_id]);
                    } else {
                        $uUp = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em WHERE user_id = :uid");
                        $uUp->execute(['fn' => $full_name, 'em' => $email, 'uid' => $user_id]);
                    }

                    $tUp = $pdo->prepare("
                        UPDATE teachers 
                        SET title = :title, faculty_id = :fid, department_id = :did, department = :dept, phone = :phone 
                        WHERE teacher_id = :tid
                    ");
                    $tUp->execute([
                        'title' => $title,
                        'fid'   => $faculty_id,
                        'did'   => $department_id,
                        'dept'  => $department,
                        'phone' => $phone,
                        'tid'   => $teacher_id
                    ]);

                    $pdo->commit();
                    set_flash('success', 'Lecturer profile updated successfully.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    set_flash('danger', 'Error updating lecturer: ' . $e->getMessage());
                }
            } else {
                set_flash('danger', implode('<br>', $errors));
            }
        }
        header('Location: ' . base_url('admin/teachers.php'));
        exit;
    }

    // Action 3: Delete Lecturer
    if ($action === 'delete') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if ($teacher_id <= 0) {
            set_flash('danger', 'Invalid lecturer ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $tCheck = $pdo->prepare("SELECT user_id FROM teachers WHERE teacher_id = :tid FOR UPDATE");
                $tCheck->execute(['tid' => $teacher_id]);
                $user_id = $tCheck->fetchColumn();

                if (!$user_id) {
                    $pdo->rollBack();
                    set_flash('danger', 'Lecturer not found.');
                } else {
                    $courseCheck = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id = :tid");
                    $courseCheck->execute(['tid' => $teacher_id]);
                    if ((int)$courseCheck->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this lecturer: one or more courses are assigned to them. Reassign the courses first.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM users WHERE user_id = :uid");
                        $del->execute(['uid' => $user_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Lecturer account and profile deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Lecturer account not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this lecturer: related records prevent deletion.'
                    : 'Lecturer deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/teachers.php'));
        exit;
    }
}

// Fetch Dropdown options
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();
$departments = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name ASC")->fetchAll();

// Fetch all lecturers
$teachers_stmt = $pdo->query("
    SELECT t.*, u.full_name, u.email,
           f.faculty_name, f.faculty_code,
           d.department_name, d.department_code,
           (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = t.teacher_id) as assigned_courses_count
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    LEFT JOIN faculties f ON t.faculty_id = f.faculty_id
    LEFT JOIN departments d ON t.department_id = d.department_id
    ORDER BY t.teacher_id DESC
");
$teachers = $teachers_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Academic Lecturers & Faculty Staff</h4>
        <p class="text-muted small mb-0">Manage teaching staff, professorial appointments, and course assignments.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
        <i class="bi bi-person-plus-fill me-1"></i> Register New Lecturer
    </button>
</div>

<!-- Lecturers Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($teachers); ?> Lecturers
            </span>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search lecturers...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Title & Name</th>
                    <th>Institutional Email</th>
                    <th>Department & Faculty</th>
                    <th>Phone</th>
                    <th>Assigned Courses</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-person-badge fs-1 text-muted d-block mb-2"></i>
                            No lecturers registered yet. Click <strong>Register New Lecturer</strong> to add one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $tch): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar-mini me-2" style="width: 36px; height: 36px; font-size: 0.85rem; background: linear-gradient(135deg, #0d9488, #0f766e);">
                                        <?php echo strtoupper(substr($tch['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">
                                            <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($tch['title'] ?? 'Lecturer'); ?></span>
                                            <?php echo htmlspecialchars($tch['full_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted small"><?php echo htmlspecialchars($tch['email']); ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?php echo htmlspecialchars($tch['department_name'] ?? $tch['department'] ?? 'Department'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($tch['faculty_name'] ?? ''); ?></small>
                            </td>
                            <td class="small">
                                <?php echo htmlspecialchars($tch['phone'] ?? 'N/A'); ?>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border">
                                    <?php echo (int)$tch['assigned_courses_count']; ?> Courses
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-teacher"
                                            data-bs-toggle="modal" data-bs-target="#editTeacherModal"
                                            data-id="<?php echo $tch['teacher_id']; ?>"
                                            data-title="<?php echo htmlspecialchars($tch['title'] ?? 'Lecturer'); ?>"
                                            data-name="<?php echo htmlspecialchars($tch['full_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($tch['email']); ?>"
                                            data-faculty="<?php echo $tch['faculty_id'] ?? ''; ?>"
                                            data-dept="<?php echo $tch['department_id'] ?? ''; ?>"
                                            data-deptname="<?php echo htmlspecialchars($tch['department'] ?? ''); ?>"
                                            data-phone="<?php echo htmlspecialchars($tch['phone'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/teachers.php'); ?>"
                                            data-id-field="teacher_id"
                                            data-record-id="<?php echo $tch['teacher_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Lecturer: <?php echo htmlspecialchars($tch['title'] . ' ' . $tch['full_name']); ?>"
                                            data-message="Are you sure you want to delete this lecturer? Courses taught by them will become unassigned.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Lecturer -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i> Register New Lecturer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Academic Title</label>
                            <select name="title" class="form-select">
                                <option value="Lecturer">Lecturer</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                                <option value="Eng.">Eng.</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Ms.">Ms.</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="e.g. Abdirahman Omar" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">University Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="lecturer@xoghaye.edu" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" value="Teacher@123" minlength="6" required>
                        <div class="form-text">Default: <code>Teacher@123</code></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>"><?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Contact Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+252 ... / +1 ...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Register Lecturer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Lecturer -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Lecturer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="teacher_id" id="edit_teacher_id">

                <div class="modal-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold small">Academic Title</label>
                            <select name="title" id="edit_title" class="form-select">
                                <option value="Lecturer">Lecturer</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                                <option value="Eng.">Eng.</option>
                                <option value="Mr.">Mr.</option>
                                <option value="Ms.">Ms.</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">University Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" id="edit_faculty_id" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>"><?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Department</label>
                            <select name="department_id" id="edit_department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Contact Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reset Password (Optional)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep unchanged">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-teacher').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_teacher_id').value = this.getAttribute('data-id');
            document.getElementById('edit_title').value = this.getAttribute('data-title');
            document.getElementById('edit_full_name').value = this.getAttribute('data-name');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_faculty_id').value = this.getAttribute('data-faculty');
            document.getElementById('edit_department_id').value = this.getAttribute('data-dept');
            document.getElementById('edit_phone').value = this.getAttribute('data-phone');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
