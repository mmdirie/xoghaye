<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Department Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Department Management';

$selected_faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/departments.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Department
    if ($action === 'create') {
        $faculty_id      = (int)($_POST['faculty_id'] ?? 0);
        $department_name = trim($_POST['department_name'] ?? '');
        $department_code = strtoupper(trim($_POST['department_code'] ?? ''));

        $errors = [];
        if (empty($department_name)) $errors[] = 'Department name is required.';
        if (empty($department_code)) $errors[] = 'Department code is required.';
        if ($faculty_id <= 0) $errors[] = 'Please select a parent faculty.';

        // Check code uniqueness
        $chk = $pdo->prepare("SELECT department_id FROM departments WHERE department_code = :dc LIMIT 1");
        $chk->execute(['dc' => $department_code]);
        if ($chk->fetch()) {
            $errors[] = 'Department code "' . htmlspecialchars($department_code) . '" already exists.';
        }

        if (empty($errors)) {
            $ins = $pdo->prepare("INSERT INTO departments (faculty_id, department_name, department_code) VALUES (:fid, :dn, :dc)");
            $ins->execute(['fid' => $faculty_id, 'dn' => $department_name, 'dc' => $department_code]);
            set_flash('success', 'Department "' . htmlspecialchars($department_name) . '" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/departments.php' . ($faculty_id > 0 ? '?faculty_id=' . $faculty_id : '')));
        exit;
    }

    // Action 2: Update Department
    if ($action === 'update') {
        $department_id   = (int)($_POST['department_id'] ?? 0);
        $faculty_id      = (int)($_POST['faculty_id'] ?? 0);
        $department_name = trim($_POST['department_name'] ?? '');
        $department_code = strtoupper(trim($_POST['department_code'] ?? ''));

        $errors = [];
        if ($department_id <= 0) $errors[] = 'Invalid department ID.';
        if (empty($department_name)) $errors[] = 'Department name is required.';
        if (empty($department_code)) $errors[] = 'Department code is required.';
        if ($faculty_id <= 0) $errors[] = 'Please select a parent faculty.';

        // Check uniqueness excluding self
        $chk = $pdo->prepare("SELECT department_id FROM departments WHERE department_code = :dc AND department_id != :did LIMIT 1");
        $chk->execute(['dc' => $department_code, 'did' => $department_id]);
        if ($chk->fetch()) {
            $errors[] = 'Department code "' . htmlspecialchars($department_code) . '" is already in use.';
        }

        if (empty($errors)) {
            $up = $pdo->prepare("UPDATE departments SET faculty_id = :fid, department_name = :dn, department_code = :dc WHERE department_id = :did");
            $up->execute(['fid' => $faculty_id, 'dn' => $department_name, 'dc' => $department_code, 'did' => $department_id]);
            set_flash('success', 'Department updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/departments.php'));
        exit;
    }

    // Action 3: Delete Department
    if ($action === 'delete') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        if ($department_id <= 0) {
            set_flash('danger', 'Invalid department ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $departmentCheck = $pdo->prepare("SELECT department_id FROM departments WHERE department_id = :did FOR UPDATE");
                $departmentCheck->execute(['did' => $department_id]);
                if (!$departmentCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Department not found.');
                } else {
                    $related = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM programs WHERE department_id = :did1) +
                            (SELECT COUNT(*) FROM courses WHERE department_id = :did2) +
                            (SELECT COUNT(*) FROM teachers WHERE department_id = :did3) +
                            (SELECT COUNT(*) FROM students WHERE department_id = :did4)
                    ");
                    $related->execute(['did1' => $department_id, 'did2' => $department_id, 'did3' => $department_id, 'did4' => $department_id]);
                    if ((int)$related->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this department: programs, courses, lecturers, or students are linked to it.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM departments WHERE department_id = :did");
                        $del->execute(['did' => $department_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Department deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Department not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this department: related records prevent deletion.'
                    : 'Department deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/departments.php'));
        exit;
    }
}

// Fetch faculties for dropdown & filter
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();

// Build Departments Query
$where = [];
$params = [];
if ($selected_faculty_id > 0) {
    $where[] = "d.faculty_id = :fid";
    $params['fid'] = $selected_faculty_id;
}
$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT d.*, f.faculty_name, f.faculty_code,
           (SELECT COUNT(*) FROM programs p WHERE p.department_id = d.department_id) as program_count,
           (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.department_id) as course_count
    FROM departments d
    LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
    $where_sql
    ORDER BY d.department_name ASC
";
$dept_stmt = $pdo->prepare($query);
$dept_stmt->execute($params);
$departments = $dept_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Academic Departments</h4>
        <p class="text-muted small mb-0">Manage teaching departments, specialized fields, and school divisions.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
        <i class="bi bi-plus-circle me-1"></i> Add New Department
    </button>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1">Filter by Faculty:</label>
                <select name="faculty_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Faculties (<?php echo count($faculties); ?>) --</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo $f['faculty_id']; ?>" <?php echo ($selected_faculty_id === (int)$f['faculty_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Search within Table:</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter by code or title...">
            </div>
            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($selected_faculty_id > 0): ?>
                    <a href="<?php echo base_url('admin/departments.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Departments Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($departments); ?> Departments
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Code</th>
                    <th>Department Name</th>
                    <th>Parent Faculty</th>
                    <th>Programs</th>
                    <th>Courses</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-diagram-3 fs-1 text-muted d-block mb-2"></i>
                            No departments found. Click <strong>Add New Department</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fw-bold fs-6"><?php echo htmlspecialchars($d['department_code']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($d['department_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($d['faculty_name'] ?? 'Unassigned Faculty'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border">
                                    <?php echo (int)$d['program_count']; ?> Programs
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?php echo (int)$d['course_count']; ?> Courses
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-dept"
                                            data-bs-toggle="modal" data-bs-target="#editDeptModal"
                                            data-id="<?php echo $d['department_id']; ?>"
                                            data-faculty="<?php echo $d['faculty_id'] ?? ''; ?>"
                                            data-name="<?php echo htmlspecialchars($d['department_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($d['department_code']); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/departments.php'); ?>"
                                            data-id-field="department_id"
                                            data-record-id="<?php echo $d['department_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Department: <?php echo htmlspecialchars($d['department_name']); ?>"
                                            data-message="Are you sure you want to delete this department? Courses and degree programs linked to it will be affected.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="department_id" value="<?php echo $d['department_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"></i> Add New Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Parent Faculty <span class="text-danger">*</span></label>
                        <select name="faculty_id" class="form-select" required>
                            <option value="">-- Select Faculty --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo $f['faculty_id']; ?>" <?php echo ($selected_faculty_id === (int)$f['faculty_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Department Code <span class="text-danger">*</span></label>
                        <input type="text" name="department_code" class="form-control text-uppercase" placeholder="e.g. CS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="department_name" class="form-control" placeholder="e.g. Computer Science" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="department_id" id="edit_dept_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Parent Faculty <span class="text-danger">*</span></label>
                        <select name="faculty_id" id="edit_dept_faculty" class="form-select" required>
                            <option value="">-- Select Faculty --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo $f['faculty_id']; ?>">
                                    <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Department Code <span class="text-danger">*</span></label>
                        <input type="text" name="department_code" id="edit_dept_code" class="form-control text-uppercase" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="department_name" id="edit_dept_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-dept').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_dept_id').value = this.getAttribute('data-id');
            document.getElementById('edit_dept_faculty').value = this.getAttribute('data-faculty');
            document.getElementById('edit_dept_name').value = this.getAttribute('data-name');
            document.getElementById('edit_dept_code').value = this.getAttribute('data-code');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
