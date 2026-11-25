<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Academic Degree Programs Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Academic Degree Programs';

$selected_dept_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/programs.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Program
    if ($action === 'create') {
        $department_id = (int)($_POST['department_id'] ?? 0);
        $program_name  = trim($_POST['program_name'] ?? '');
        $program_code  = strtoupper(trim($_POST['program_code'] ?? ''));
        $degree_type   = trim($_POST['degree_type'] ?? 'Bachelor');

        $errors = [];
        if (empty($program_name)) $errors[] = 'Program name is required.';
        if (empty($program_code)) $errors[] = 'Program code is required (e.g. BSCS).';
        if ($department_id <= 0) $errors[] = 'Please select a parent Department.';

        // Check uniqueness
        $chk = $pdo->prepare("SELECT program_id FROM programs WHERE program_code = :pc LIMIT 1");
        $chk->execute(['pc' => $program_code]);
        if ($chk->fetch()) {
            $errors[] = 'Program code "' . htmlspecialchars($program_code) . '" already exists.';
        }

        if (empty($errors)) {
            $ins = $pdo->prepare("INSERT INTO programs (department_id, program_name, program_code, degree_type) VALUES (:did, :pn, :pc, :dt)");
            $ins->execute(['did' => $department_id, 'pn' => $program_name, 'pc' => $program_code, 'dt' => $degree_type]);
            set_flash('success', 'Program "' . htmlspecialchars($program_name) . '" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/programs.php' . ($department_id > 0 ? '?department_id=' . $department_id : '')));
        exit;
    }

    // Action 2: Update Program
    if ($action === 'update') {
        $program_id    = (int)($_POST['program_id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $program_name  = trim($_POST['program_name'] ?? '');
        $program_code  = strtoupper(trim($_POST['program_code'] ?? ''));
        $degree_type   = trim($_POST['degree_type'] ?? 'Bachelor');

        $errors = [];
        if ($program_id <= 0) $errors[] = 'Invalid program ID.';
        if (empty($program_name)) $errors[] = 'Program name is required.';
        if (empty($program_code)) $errors[] = 'Program code is required.';
        if ($department_id <= 0) $errors[] = 'Please select a Department.';

        $chk = $pdo->prepare("SELECT program_id FROM programs WHERE program_code = :pc AND program_id != :pid LIMIT 1");
        $chk->execute(['pc' => $program_code, 'pid' => $program_id]);
        if ($chk->fetch()) {
            $errors[] = 'Program code "' . htmlspecialchars($program_code) . '" is already in use.';
        }

        if (empty($errors)) {
            $up = $pdo->prepare("UPDATE programs SET department_id = :did, program_name = :pn, program_code = :pc, degree_type = :dt WHERE program_id = :pid");
            $up->execute(['did' => $department_id, 'pn' => $program_name, 'pc' => $program_code, 'dt' => $degree_type, 'pid' => $program_id]);
            set_flash('success', 'Degree program updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/programs.php'));
        exit;
    }

    // Action 3: Delete Program
    if ($action === 'delete') {
        $program_id = (int)($_POST['program_id'] ?? 0);
        if ($program_id <= 0) {
            set_flash('danger', 'Invalid program ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $programCheck = $pdo->prepare("SELECT program_id FROM programs WHERE program_id = :pid FOR UPDATE");
                $programCheck->execute(['pid' => $program_id]);
                if (!$programCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Degree program not found.');
                } else {
                    $studentCheck = $pdo->prepare("SELECT COUNT(*) FROM students WHERE program_id = :pid");
                    $studentCheck->execute(['pid' => $program_id]);
                    if ((int)$studentCheck->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this program: students are registered in it.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM programs WHERE program_id = :pid");
                        $del->execute(['pid' => $program_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Degree program deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Degree program not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this program: related records prevent deletion.'
                    : 'Program deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/programs.php'));
        exit;
    }
}

// Fetch departments for dropdown & filter
$departments = $pdo->query("
    SELECT d.department_id, d.department_name, d.department_code, f.faculty_code 
    FROM departments d
    LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
    ORDER BY d.department_name ASC
")->fetchAll();

// Build query
$where = [];
$params = [];
if ($selected_dept_id > 0) {
    $where[] = "p.department_id = :did";
    $params['did'] = $selected_dept_id;
}
$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT p.*, d.department_name, d.department_code, f.faculty_name, f.faculty_code,
           (SELECT COUNT(*) FROM students s WHERE s.program_id = p.program_id) as student_count
    FROM programs p
    LEFT JOIN departments d ON p.department_id = d.department_id
    LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
    $where_sql
    ORDER BY p.program_name ASC
";
$prog_stmt = $pdo->prepare($query);
$prog_stmt->execute($params);
$programs = $prog_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Degree & Academic Programs</h4>
        <p class="text-muted small mb-0">Manage university undergraduate, graduate, and professional academic programs.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProgModal">
        <i class="bi bi-mortarboard-fill me-1"></i> Add Degree Program
    </button>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1">Filter by Department:</label>
                <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Departments (<?php echo count($departments); ?>) --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['department_id']; ?>" <?php echo ($selected_dept_id === (int)$d['department_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['department_name'] . ' (' . $d['department_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Search within Table:</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter programs...">
            </div>
            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($selected_dept_id > 0): ?>
                    <a href="<?php echo base_url('admin/programs.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Programs Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($programs); ?> Degree Programs
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Code</th>
                    <th>Program Name</th>
                    <th>Qualification Level</th>
                    <th>Department & Faculty</th>
                    <th>Enrolled Students</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($programs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-mortarboard fs-1 text-muted d-block mb-2"></i>
                            No degree programs found. Click <strong>Add Degree Program</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($programs as $p): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fw-bold fs-6"><?php echo htmlspecialchars($p['program_code']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['program_name']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?php echo htmlspecialchars($p['degree_type']); ?> Degree
                                </span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?php echo htmlspecialchars($p['department_name'] ?? 'Department'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($p['faculty_name'] ?? ''); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border">
                                    <?php echo (int)$p['student_count']; ?> Students
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-prog"
                                            data-bs-toggle="modal" data-bs-target="#editProgModal"
                                            data-id="<?php echo $p['program_id']; ?>"
                                            data-dept="<?php echo $p['department_id'] ?? ''; ?>"
                                            data-name="<?php echo htmlspecialchars($p['program_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($p['program_code']); ?>"
                                            data-type="<?php echo htmlspecialchars($p['degree_type']); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/programs.php'); ?>"
                                            data-id-field="program_id"
                                            data-record-id="<?php echo $p['program_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Program: <?php echo htmlspecialchars($p['program_name']); ?>"
                                            data-message="Are you sure you want to delete this degree program?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="program_id" value="<?php echo $p['program_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Program -->
<div class="modal fade" id="addProgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-mortarboard-fill me-2"></i> Add Degree Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Department <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['department_id']; ?>" <?php echo ($selected_dept_id === (int)$d['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['department_name'] . ' (' . $d['department_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Program Code <span class="text-danger">*</span></label>
                        <input type="text" name="program_code" class="form-control text-uppercase" placeholder="e.g. BSCS, BIT" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Program Title <span class="text-danger">*</span></label>
                        <input type="text" name="program_name" class="form-control" placeholder="e.g. Bachelor of Science in Computer Science" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Qualification Level <span class="text-danger">*</span></label>
                        <select name="degree_type" class="form-select" required>
                            <option value="Bachelor">Bachelor Degree</option>
                            <option value="Master">Master Degree</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Doctorate">Doctorate / PhD</option>
                            <option value="Certificate">Certificate</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Program -->
<div class="modal fade" id="editProgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Program</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="program_id" id="edit_prog_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Department <span class="text-danger">*</span></label>
                        <select name="department_id" id="edit_prog_dept" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['department_id']; ?>">
                                    <?php echo htmlspecialchars($d['department_name'] . ' (' . $d['department_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Program Code <span class="text-danger">*</span></label>
                        <input type="text" name="program_code" id="edit_prog_code" class="form-control text-uppercase" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Program Title <span class="text-danger">*</span></label>
                        <input type="text" name="program_name" id="edit_prog_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Qualification Level <span class="text-danger">*</span></label>
                        <select name="degree_type" id="edit_prog_type" class="form-select" required>
                            <option value="Bachelor">Bachelor Degree</option>
                            <option value="Master">Master Degree</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Doctorate">Doctorate / PhD</option>
                            <option value="Certificate">Certificate</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-prog').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_prog_id').value = this.getAttribute('data-id');
            document.getElementById('edit_prog_dept').value = this.getAttribute('data-dept');
            document.getElementById('edit_prog_name').value = this.getAttribute('data-name');
            document.getElementById('edit_prog_code').value = this.getAttribute('data-code');
            document.getElementById('edit_prog_type').value = this.getAttribute('data-type');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
