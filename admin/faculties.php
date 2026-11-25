<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Faculty Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Faculty Management';

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/faculties.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Faculty
    if ($action === 'create') {
        $faculty_name = trim($_POST['faculty_name'] ?? '');
        $faculty_code = strtoupper(trim($_POST['faculty_code'] ?? ''));
        $description  = trim($_POST['description'] ?? '');

        $errors = [];
        if (empty($faculty_name)) $errors[] = 'Faculty name is required.';
        if (empty($faculty_code)) $errors[] = 'Faculty code is required.';

        // Check uniqueness
        $chk = $pdo->prepare("SELECT faculty_id FROM faculties WHERE faculty_code = :fc OR faculty_name = :fn LIMIT 1");
        $chk->execute(['fc' => $faculty_code, 'fn' => $faculty_name]);
        if ($chk->fetch()) {
            $errors[] = 'A faculty with that name or code already exists.';
        }

        if (empty($errors)) {
            $ins = $pdo->prepare("INSERT INTO faculties (faculty_name, faculty_code, description) VALUES (:fn, :fc, :descr)");
            $ins->execute(['fn' => $faculty_name, 'fc' => $faculty_code, 'descr' => $description]);
            set_flash('success', 'Faculty "' . htmlspecialchars($faculty_name) . '" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/faculties.php'));
        exit;
    }

    // Action 2: Update Faculty
    if ($action === 'update') {
        $faculty_id   = (int)($_POST['faculty_id'] ?? 0);
        $faculty_name = trim($_POST['faculty_name'] ?? '');
        $faculty_code = strtoupper(trim($_POST['faculty_code'] ?? ''));
        $description  = trim($_POST['description'] ?? '');

        $errors = [];
        if ($faculty_id <= 0) $errors[] = 'Invalid faculty ID.';
        if (empty($faculty_name)) $errors[] = 'Faculty name is required.';
        if (empty($faculty_code)) $errors[] = 'Faculty code is required.';

        // Check duplicate excluding self
        $chk = $pdo->prepare("SELECT faculty_id FROM faculties WHERE (faculty_code = :fc OR faculty_name = :fn) AND faculty_id != :fid LIMIT 1");
        $chk->execute(['fc' => $faculty_code, 'fn' => $faculty_name, 'fid' => $faculty_id]);
        if ($chk->fetch()) {
            $errors[] = 'Another faculty already uses that name or code.';
        }

        if (empty($errors)) {
            $up = $pdo->prepare("UPDATE faculties SET faculty_name = :fn, faculty_code = :fc, description = :descr WHERE faculty_id = :fid");
            $up->execute(['fn' => $faculty_name, 'fc' => $faculty_code, 'descr' => $description, 'fid' => $faculty_id]);
            set_flash('success', 'Faculty updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/faculties.php'));
        exit;
    }

    // Action 3: Delete Faculty
    if ($action === 'delete') {
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        if ($faculty_id <= 0) {
            set_flash('danger', 'Invalid faculty ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $facultyCheck = $pdo->prepare("SELECT faculty_id FROM faculties WHERE faculty_id = :fid FOR UPDATE");
                $facultyCheck->execute(['fid' => $faculty_id]);
                if (!$facultyCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Faculty not found.');
                } else {
                    $related = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM departments WHERE faculty_id = :fid1) +
                            (SELECT COUNT(*) FROM courses WHERE faculty_id = :fid2) +
                            (SELECT COUNT(*) FROM teachers WHERE faculty_id = :fid3) +
                            (SELECT COUNT(*) FROM students WHERE faculty_id = :fid4)
                    ");
                    $related->execute(['fid1' => $faculty_id, 'fid2' => $faculty_id, 'fid3' => $faculty_id, 'fid4' => $faculty_id]);
                    if ((int)$related->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this faculty: departments, courses, lecturers, or students are linked to it.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM faculties WHERE faculty_id = :fid");
                        $del->execute(['fid' => $faculty_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Faculty deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Faculty not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this faculty: related records prevent deletion.'
                    : 'Faculty deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/faculties.php'));
        exit;
    }
}

// Fetch all faculties with department count
$faculties_stmt = $pdo->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM departments d WHERE d.faculty_id = f.faculty_id) as dept_count,
           (SELECT COUNT(*) FROM courses c WHERE c.faculty_id = f.faculty_id) as course_count
    FROM faculties f
    ORDER BY f.faculty_name ASC
");
$faculties = $faculties_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">University Faculties</h4>
        <p class="text-muted small mb-0">Organize academic faculties, colleges, and schools across the university.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
        <i class="bi bi-plus-circle me-1"></i> Add New Faculty
    </button>
</div>

<!-- Faculties Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($faculties); ?> Faculties
            </span>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search faculties...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Code</th>
                    <th>Faculty Name</th>
                    <th>Departments</th>
                    <th>Courses</th>
                    <th>Description</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($faculties)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-buildings fs-1 text-muted d-block mb-2"></i>
                            No faculties defined yet. Click <strong>Add New Faculty</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($faculties as $f): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fw-bold fs-6"><?php echo htmlspecialchars($f['faculty_code']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($f['faculty_name']); ?></div>
                            </td>
                            <td>
                                <a href="<?php echo base_url('admin/departments.php?faculty_id=' . $f['faculty_id']); ?>" class="badge bg-info-subtle text-info-emphasis border text-decoration-none">
                                    <?php echo (int)$f['dept_count']; ?> Departments
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?php echo (int)$f['course_count']; ?> Courses
                                </span>
                            </td>
                            <td class="small text-muted" style="max-width: 250px;">
                                <?php echo htmlspecialchars($f['description'] ?? '-'); ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-faculty"
                                            data-bs-toggle="modal" data-bs-target="#editFacultyModal"
                                            data-id="<?php echo $f['faculty_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($f['faculty_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($f['faculty_code']); ?>"
                                            data-desc="<?php echo htmlspecialchars($f['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/faculties.php'); ?>"
                                            data-id-field="faculty_id"
                                            data-record-id="<?php echo $f['faculty_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Faculty: <?php echo htmlspecialchars($f['faculty_name']); ?>"
                                            data-message="Are you sure you want to delete this faculty? Sub-departments and course links will be affected.">
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

<!-- Modal: Add Faculty -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-buildings-fill me-2"></i> Add New Faculty</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Faculty Code <span class="text-danger">*</span></label>
                        <input type="text" name="faculty_code" class="form-control text-uppercase" placeholder="e.g. FCI" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Faculty Name <span class="text-danger">*</span></label>
                        <input type="text" name="faculty_name" class="form-control" placeholder="e.g. Faculty of Computing & Informatics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Description / Dean's Office</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Faculty -->
<div class="modal fade" id="editFacultyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Faculty</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="faculty_id" id="edit_faculty_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Faculty Code <span class="text-danger">*</span></label>
                        <input type="text" name="faculty_code" id="edit_faculty_code" class="form-control text-uppercase" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Faculty Name <span class="text-danger">*</span></label>
                        <input type="text" name="faculty_name" id="edit_faculty_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Description / Dean's Office</label>
                        <textarea name="description" id="edit_faculty_desc" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-faculty').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_faculty_id').value = this.getAttribute('data-id');
            document.getElementById('edit_faculty_name').value = this.getAttribute('data-name');
            document.getElementById('edit_faculty_code').value = this.getAttribute('data-code');
            document.getElementById('edit_faculty_desc').value = this.getAttribute('data-desc');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
