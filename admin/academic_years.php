<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Academic Years Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Academic Years Management';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/academic_years.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Academic Year
    if ($action === 'create') {
        $year_name  = trim($_POST['year_name'] ?? '');
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        $errors = [];
        if (empty($year_name)) $errors[] = 'Academic Year title is required (e.g. 2024/2025).';

        // Check duplicate
        $chk = $pdo->prepare("SELECT year_id FROM academic_years WHERE year_name = :yn LIMIT 1");
        $chk->execute(['yn' => $year_name]);
        if ($chk->fetch()) {
            $errors[] = 'Academic Year "' . htmlspecialchars($year_name) . '" already exists.';
        }

        if (empty($errors)) {
            if ($is_current === 1) {
                $pdo->exec("UPDATE academic_years SET is_current = 0");
            }
            $ins = $pdo->prepare("INSERT INTO academic_years (year_name, is_current) VALUES (:yn, :ic)");
            $ins->execute(['yn' => $year_name, 'ic' => $is_current]);
            set_flash('success', 'Academic Year "' . htmlspecialchars($year_name) . '" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/academic_years.php'));
        exit;
    }

    // Action 2: Update Academic Year
    if ($action === 'update') {
        $year_id    = (int)($_POST['year_id'] ?? 0);
        $year_name  = trim($_POST['year_name'] ?? '');
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        $errors = [];
        if ($year_id <= 0) $errors[] = 'Invalid Academic Year ID.';
        if (empty($year_name)) $errors[] = 'Academic Year title is required.';

        $chk = $pdo->prepare("SELECT year_id FROM academic_years WHERE year_name = :yn AND year_id != :yid LIMIT 1");
        $chk->execute(['yn' => $year_name, 'yid' => $year_id]);
        if ($chk->fetch()) {
            $errors[] = 'Academic Year "' . htmlspecialchars($year_name) . '" is already in use.';
        }

        if (empty($errors)) {
            if ($is_current === 1) {
                $pdo->exec("UPDATE academic_years SET is_current = 0");
            }
            $up = $pdo->prepare("UPDATE academic_years SET year_name = :yn, is_current = :ic WHERE year_id = :yid");
            $up->execute(['yn' => $year_name, 'ic' => $is_current, 'yid' => $year_id]);
            set_flash('success', 'Academic Year updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/academic_years.php'));
        exit;
    }

    // Action 3: Set as Active Current Year
    if ($action === 'set_active') {
        $year_id = (int)($_POST['year_id'] ?? 0);
        if ($year_id > 0) {
            $pdo->exec("UPDATE academic_years SET is_current = 0");
            $up = $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE year_id = :yid");
            $up->execute(['yid' => $year_id]);
            set_flash('success', 'Active academic year updated.');
        }
        header('Location: ' . base_url('admin/academic_years.php'));
        exit;
    }

    // Action 4: Delete Academic Year
    if ($action === 'delete') {
        $year_id = (int)($_POST['year_id'] ?? 0);
        if ($year_id <= 0) {
            set_flash('danger', 'Invalid academic year ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $yearCheck = $pdo->prepare("SELECT year_id FROM academic_years WHERE year_id = :yid FOR UPDATE");
                $yearCheck->execute(['yid' => $year_id]);
                if (!$yearCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Academic year not found.');
                } else {
                    $related = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM semesters WHERE academic_year_id = :yid1) +
                            (SELECT COUNT(*) FROM courses WHERE academic_year_id = :yid2) +
                            (SELECT COUNT(*) FROM enrollments WHERE academic_year_id = :yid3) +
                            (SELECT COUNT(*) FROM attendance WHERE academic_year_id = :yid4) +
                            (SELECT COUNT(*) FROM grades WHERE academic_year_id = :yid5)
                    ");
                    $related->execute(['yid1' => $year_id, 'yid2' => $year_id, 'yid3' => $year_id, 'yid4' => $year_id, 'yid5' => $year_id]);
                    if ((int)$related->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this academic year: semesters or academic records are linked to it.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM academic_years WHERE year_id = :yid");
                        $del->execute(['yid' => $year_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Academic year deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Academic year not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this academic year: related records prevent deletion.'
                    : 'Academic year deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/academic_years.php'));
        exit;
    }
}

// Fetch all academic years
$years = $pdo->query("
    SELECT y.*,
           (SELECT COUNT(*) FROM semesters s WHERE s.academic_year_id = y.year_id) as semester_count
    FROM academic_years y
    ORDER BY y.year_name DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Academic Years</h4>
        <p class="text-muted small mb-0">Configure university academic cycles, calendar terms, and current active year.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addYearModal">
        <i class="bi bi-calendar-plus me-1"></i> Add Academic Year
    </button>
</div>

<!-- Academic Years Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($years); ?> Academic Years
            </span>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search year...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th>Status</th>
                    <th>Linked Semesters</th>
                    <th>Created On</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($years)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar3-range fs-1 text-muted d-block mb-2"></i>
                            No academic years defined. Click <strong>Add Academic Year</strong> to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($years as $y): ?>
                        <tr>
                            <td>
                                <strong class="fs-6 text-dark"><?php echo htmlspecialchars($y['year_name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($y['is_current'] == 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i> Active Academic Year
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="year_id" value="<?php echo $y['year_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            Make Active
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo base_url('admin/semesters.php?year_id=' . $y['year_id']); ?>" class="badge bg-info-subtle text-info-emphasis border text-decoration-none">
                                    <?php echo (int)$y['semester_count']; ?> Semesters
                                </a>
                            </td>
                            <td class="small text-muted">
                                <?php echo format_date($y['created_at']); ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-year"
                                            data-bs-toggle="modal" data-bs-target="#editYearModal"
                                            data-id="<?php echo $y['year_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($y['year_name']); ?>"
                                            data-current="<?php echo $y['is_current']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/academic_years.php'); ?>"
                                            data-id-field="year_id"
                                            data-record-id="<?php echo $y['year_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Academic Year: <?php echo htmlspecialchars($y['year_name']); ?>"
                                            data-message="Are you sure you want to remove this academic year?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="year_id" value="<?php echo $y['year_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Year -->
<div class="modal fade" id="addYearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i> Add Academic Year</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Year Title <span class="text-danger">*</span></label>
                        <input type="text" name="year_name" class="form-control" placeholder="e.g. 2024/2025" required>
                        <div class="form-text">Format recommendation: <code>YYYY/YYYY</code></div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_current" id="isCurrentSwitch" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="isCurrentSwitch">Set as Current University Academic Year</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Year -->
<div class="modal fade" id="editYearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Academic Year</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="year_id" id="edit_year_id">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Academic Year Title <span class="text-danger">*</span></label>
                        <input type="text" name="year_name" id="edit_year_name" class="form-control" required>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_current" id="edit_is_current" value="1">
                        <label class="form-check-label fw-semibold small" for="edit_is_current">Set as Current University Academic Year</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-year').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_year_id').value = this.getAttribute('data-id');
            document.getElementById('edit_year_name').value = this.getAttribute('data-name');
            document.getElementById('edit_is_current').checked = (this.getAttribute('data-current') === '1');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
