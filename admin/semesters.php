<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Semester Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Semester Management';

$selected_year_id = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/semesters.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Semester
    if ($action === 'create') {
        $semester_name = trim($_POST['semester_name'] ?? '');
        $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        $errors = [];
        if (empty($semester_name)) $errors[] = 'Semester title is required (e.g. Semester 1, Semester 2).';
        if ($academic_year_id <= 0) $errors[] = 'Please select a parent Academic Year.';

        // Check duplicate within the same academic year
        $chk = $pdo->prepare("SELECT semester_id FROM semesters WHERE semester_name = :sn AND academic_year_id = :yid LIMIT 1");
        $chk->execute(['sn' => $semester_name, 'yid' => $academic_year_id]);
        if ($chk->fetch()) {
            $errors[] = 'That semester already exists in the selected academic year.';
        }

        if (empty($errors)) {
            if ($is_current === 1) {
                $pdo->exec("UPDATE semesters SET is_current = 0");
            }
            $ins = $pdo->prepare("INSERT INTO semesters (semester_name, academic_year_id, is_current) VALUES (:sn, :yid, :ic)");
            $ins->execute(['sn' => $semester_name, 'yid' => $academic_year_id, 'ic' => $is_current]);
            set_flash('success', 'Semester "' . htmlspecialchars($semester_name) . '" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/semesters.php' . ($academic_year_id > 0 ? '?year_id=' . $academic_year_id : '')));
        exit;
    }

    // Action 2: Update Semester
    if ($action === 'update') {
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $semester_name = trim($_POST['semester_name'] ?? '');
        $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        $errors = [];
        if ($semester_id <= 0) $errors[] = 'Invalid semester ID.';
        if (empty($semester_name)) $errors[] = 'Semester title is required.';
        if ($academic_year_id <= 0) $errors[] = 'Please select an Academic Year.';

        $chk = $pdo->prepare("SELECT semester_id FROM semesters WHERE semester_name = :sn AND academic_year_id = :yid AND semester_id != :sid LIMIT 1");
        $chk->execute(['sn' => $semester_name, 'yid' => $academic_year_id, 'sid' => $semester_id]);
        if ($chk->fetch()) {
            $errors[] = 'That semester name is already used in this academic year.';
        }

        if (empty($errors)) {
            if ($is_current === 1) {
                $pdo->exec("UPDATE semesters SET is_current = 0");
            }
            $up = $pdo->prepare("UPDATE semesters SET semester_name = :sn, academic_year_id = :yid, is_current = :ic WHERE semester_id = :sid");
            $up->execute(['sn' => $semester_name, 'yid' => $academic_year_id, 'ic' => $is_current, 'sid' => $semester_id]);
            set_flash('success', 'Semester updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/semesters.php'));
        exit;
    }

    // Action 3: Set as Active Current Semester
    if ($action === 'set_active') {
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        if ($semester_id > 0) {
            $pdo->exec("UPDATE semesters SET is_current = 0");
            $up = $pdo->prepare("UPDATE semesters SET is_current = 1 WHERE semester_id = :sid");
            $up->execute(['sid' => $semester_id]);
            set_flash('success', 'Active current semester updated.');
        }
        header('Location: ' . base_url('admin/semesters.php'));
        exit;
    }

    // Action 4: Delete Semester
    if ($action === 'delete') {
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        if ($semester_id <= 0) {
            set_flash('danger', 'Invalid semester ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $semesterCheck = $pdo->prepare("SELECT semester_id FROM semesters WHERE semester_id = :sid FOR UPDATE");
                $semesterCheck->execute(['sid' => $semester_id]);
                if (!$semesterCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Semester not found.');
                } else {
                    $related = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM courses WHERE semester_id = :sid1) +
                            (SELECT COUNT(*) FROM enrollments WHERE semester_id = :sid2) +
                            (SELECT COUNT(*) FROM attendance WHERE semester_id = :sid3) +
                            (SELECT COUNT(*) FROM grades WHERE semester_id = :sid4) +
                            (SELECT COUNT(*) FROM students WHERE semester_id = :sid5)
                    ");
                    $related->execute(['sid1' => $semester_id, 'sid2' => $semester_id, 'sid3' => $semester_id, 'sid4' => $semester_id, 'sid5' => $semester_id]);
                    if ((int)$related->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this semester: courses, students, or academic records are linked to it.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM semesters WHERE semester_id = :sid");
                        $del->execute(['sid' => $semester_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Semester deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Semester not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this semester: related records prevent deletion.'
                    : 'Semester deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/semesters.php'));
        exit;
    }
}

// Fetch academic years for dropdown
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();

// Build query
$where = [];
$params = [];
if ($selected_year_id > 0) {
    $where[] = "s.academic_year_id = :yid";
    $params['yid'] = $selected_year_id;
}
$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT s.*, y.year_name,
           (SELECT COUNT(*) FROM courses c WHERE c.semester_id = s.semester_id) as course_count
    FROM semesters s
    LEFT JOIN academic_years y ON s.academic_year_id = y.year_id
    $where_sql
    ORDER BY y.year_name DESC, s.semester_name ASC
";
$sem_stmt = $pdo->prepare($query);
$sem_stmt->execute($params);
$semesters = $sem_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Academic Semesters</h4>
        <p class="text-muted small mb-0">Manage study semesters, examination sessions, and current operational term.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSemModal">
        <i class="bi bi-plus-circle me-1"></i> Add New Semester
    </button>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1">Filter by Academic Year:</label>
                <select name="year_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Academic Years (<?php echo count($years); ?>) --</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['year_id']; ?>" <?php echo ($selected_year_id === (int)$y['year_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($y['year_name']); ?> <?php echo ($y['is_current'] == 1) ? '(Current Year)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Search within Table:</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter semester...">
            </div>
            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($selected_year_id > 0): ?>
                    <a href="<?php echo base_url('admin/semesters.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Semesters Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($semesters); ?> Semesters
            </span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Semester Name</th>
                    <th>Academic Year</th>
                    <th>Active Term</th>
                    <th>Scheduled Courses</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($semesters)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-week fs-1 text-muted d-block mb-2"></i>
                            No semesters found. Click <strong>Add New Semester</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($semesters as $s): ?>
                        <tr>
                            <td>
                                <strong class="fs-6 text-dark"><?php echo htmlspecialchars($s['semester_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['year_name'] ?? 'Unassigned Year'); ?></span>
                            </td>
                            <td>
                                <?php if ($s['is_current'] == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i> Current Active Semester
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="semester_id" value="<?php echo $s['semester_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            Set Current
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis border">
                                    <?php echo (int)$s['course_count']; ?> Courses
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-sem"
                                            data-bs-toggle="modal" data-bs-target="#editSemModal"
                                            data-id="<?php echo $s['semester_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($s['semester_name']); ?>"
                                            data-year="<?php echo $s['academic_year_id'] ?? ''; ?>"
                                            data-current="<?php echo $s['is_current']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/semesters.php'); ?>"
                                            data-id-field="semester_id"
                                            data-record-id="<?php echo $s['semester_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Semester: <?php echo htmlspecialchars($s['semester_name']); ?>"
                                            data-message="Are you sure you want to delete this semester?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="semester_id" value="<?php echo $s['semester_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Semester -->
<div class="modal fade" id="addSemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-week-fill me-2"></i> Add New Semester</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-select" required>
                            <option value="">-- Select Academic Year --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y['year_id']; ?>" <?php echo ($selected_year_id === (int)$y['year_id'] || $y['is_current'] == 1) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($y['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Semester Title <span class="text-danger">*</span></label>
                        <input type="text" name="semester_name" class="form-control" placeholder="e.g. Semester 1, Semester 2" required>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_current" id="isCurSem" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="isCurSem">Set as Current Active University Semester</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Semester -->
<div class="modal fade" id="editSemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Semester</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="semester_id" id="edit_sem_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" id="edit_sem_year" class="form-select" required>
                            <option value="">-- Select Academic Year --</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y['year_id']; ?>">
                                    <?php echo htmlspecialchars($y['year_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Semester Title <span class="text-danger">*</span></label>
                        <input type="text" name="semester_name" id="edit_sem_name" class="form-control" required>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_current" id="edit_sem_current" value="1">
                        <label class="form-check-label fw-semibold small" for="edit_sem_current">Set as Current Active University Semester</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-sem').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_sem_id').value = this.getAttribute('data-id');
            document.getElementById('edit_sem_name').value = this.getAttribute('data-name');
            document.getElementById('edit_sem_year').value = this.getAttribute('data-year');
            document.getElementById('edit_sem_current').checked = (this.getAttribute('data-current') === '1');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
