<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - University Attendance Records (Admin)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Attendance Records';

// Handle Attendance Operations (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/attendance.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Create New Attendance Entry
    if ($action === 'create') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $course_id  = (int)($_POST['course_id'] ?? 0);
        $adate      = trim($_POST['attendance_date'] ?? date('Y-m-d'));
        $status     = $_POST['status'] ?? 'Present';
        $ay_id      = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
        $sem_id     = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;

        $errors = [];
        if ($student_id <= 0) $errors[] = 'Please select a student.';
        if ($course_id <= 0)  $errors[] = 'Please select a course.';
        if (empty($adate))    $errors[] = 'Session date is required.';
        if (!in_array($status, ['Present', 'Absent', 'Late', 'Excused'])) $errors[] = 'Invalid attendance status.';

        if (empty($errors)) {
            try {
                $ins = $pdo->prepare("
                    INSERT INTO attendance (student_id, course_id, attendance_date, status, academic_year_id, semester_id)
                    VALUES (:sid, :cid, :adate, :stat, :ay, :sem)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $ins->execute([
                    'sid'   => $student_id,
                    'cid'   => $course_id,
                    'adate' => $adate,
                    'stat'  => $status,
                    'ay'    => $ay_id,
                    'sem'   => $sem_id
                ]);
                set_flash('success', 'Attendance record saved successfully.');
            } catch (Exception $e) {
                set_flash('danger', 'Error saving attendance: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
    }
    // Update Status
    elseif ($action === 'update_status') {
        $attendance_id = (int)($_POST['attendance_id'] ?? 0);
        $new_status = $_POST['status'] ?? 'Present';
        if ($attendance_id > 0 && in_array($new_status, ['Present', 'Absent', 'Late', 'Excused'])) {
            $up = $pdo->prepare("UPDATE attendance SET status = :st WHERE attendance_id = :aid");
            $up->execute(['st' => $new_status, 'aid' => $attendance_id]);
            set_flash('success', 'Attendance status updated successfully.');
        }
    } 
    // Delete Record
    elseif ($action === 'delete') {
        $attendance_id = (int)($_POST['attendance_id'] ?? 0);
        if ($attendance_id <= 0) {
            set_flash('danger', 'Invalid attendance record ID.');
        } else {
            try {
                $del = $pdo->prepare("DELETE FROM attendance WHERE attendance_id = :aid");
                $del->execute(['aid' => $attendance_id]);
                if ($del->rowCount() === 1) {
                    set_flash('success', 'Attendance record deleted successfully.');
                } else {
                    set_flash('danger', 'Attendance record not found; no record was deleted.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Attendance deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }
    }

    $redirect_url = base_url('admin/attendance.php');
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('Location: ' . $redirect_url);
    exit;
}

// Filters
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$date_filter = isset($_GET['attendance_date']) ? trim($_GET['attendance_date']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$year_filter = isset($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : 0;
$sem_filter = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

// Build Query
$where = [];
$params = [];

if ($course_id > 0) {
    $where[] = "a.course_id = :cid";
    $params['cid'] = $course_id;
}
if (!empty($date_filter)) {
    $where[] = "a.attendance_date = :adate";
    $params['adate'] = $date_filter;
}
if (!empty($status_filter) && in_array($status_filter, ['Present', 'Absent', 'Late', 'Excused'])) {
    $where[] = "a.status = :astat";
    $params['astat'] = $status_filter;
}
if ($year_filter > 0) {
    $where[] = "a.academic_year_id = :yid";
    $params['yid'] = $year_filter;
}
if ($sem_filter > 0) {
    $where[] = "a.semester_id = :smid";
    $params['smid'] = $sem_filter;
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT a.*, 
           s.student_number, u.full_name AS student_name, u.email AS student_email,
           c.course_name, c.course_code
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN courses c ON a.course_id = c.course_id
    $where_sql
    ORDER BY a.attendance_date DESC, c.course_code ASC, u.full_name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Stats calculation
$total_records = count($records);
$counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0];
foreach ($records as $r) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']]++;
    }
}
$present_pct = $total_records > 0 ? round(($counts['Present'] / $total_records) * 100) : 0;

// Dropdowns data
$courses = $pdo->query("SELECT course_id, course_name, course_code FROM courses ORDER BY course_code ASC")->fetchAll();
$all_students = $pdo->query("
    SELECT s.student_id, s.student_number, u.full_name 
    FROM students s 
    JOIN users u ON s.user_id = u.user_id 
    ORDER BY u.full_name ASC
")->fetchAll();
$academic_years = $pdo->query("SELECT year_id AS academic_year_id, year_name AS year_label FROM academic_years ORDER BY year_id DESC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name FROM semesters ORDER BY semester_id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">University Attendance Records</h4>
        <p class="text-muted small mb-0">Review, filter, audit, and log attendance sessions across all university faculties and courses.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
        <i class="bi bi-calendar-plus-fill me-1"></i> Log Attendance Entry
    </button>
</div>

<!-- Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Present</div>
                <div class="card-stat-value text-success fs-3"><?php echo $counts['Present']; ?></div>
                <small class="text-muted"><?php echo $present_pct; ?>% of selected</small>
            </div>
            <div class="card-stat-icon icon-emerald"><i class="bi bi-check-lg"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Absent</div>
                <div class="card-stat-value text-danger fs-3"><?php echo $counts['Absent']; ?></div>
                <small class="text-muted">Unexcused Misses</small>
            </div>
            <div class="card-stat-icon icon-rose"><i class="bi bi-x-lg"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Late</div>
                <div class="card-stat-value text-warning fs-3"><?php echo $counts['Late']; ?></div>
                <small class="text-muted">Tardy Arrivals</small>
            </div>
            <div class="card-stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Excused</div>
                <div class="card-stat-value text-info fs-3"><?php echo $counts['Excused']; ?></div>
                <small class="text-muted">Approved Leaves</small>
            </div>
            <div class="card-stat-icon icon-blue"><i class="bi bi-file-earmark-medical"></i></div>
        </div>
    </div>
</div>

<!-- Filter Box -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Course:</label>
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Courses --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['course_id']; ?>" <?php echo ($course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Session Date:</label>
                <input type="date" name="attendance_date" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Status:</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Statuses --</option>
                    <option value="Present" <?php echo ($status_filter === 'Present') ? 'selected' : ''; ?>>Present</option>
                    <option value="Absent" <?php echo ($status_filter === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                    <option value="Late" <?php echo ($status_filter === 'Late') ? 'selected' : ''; ?>>Late</option>
                    <option value="Excused" <?php echo ($status_filter === 'Excused') ? 'selected' : ''; ?>>Excused</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Academic Year:</label>
                <select name="academic_year_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Years --</option>
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?php echo $ay['academic_year_id']; ?>" <?php echo ($year_filter === (int)$ay['academic_year_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ay['year_label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($course_id > 0 || !empty($date_filter) || !empty($status_filter) || $year_filter > 0 || $sem_filter > 0): ?>
                    <a href="<?php echo base_url('admin/attendance.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total Logs: <?php echo $total_records; ?>
            </span>
        </div>
        <div class="input-group" style="max-width: 300px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Filter current view...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student Details</th>
                    <th>Student #</th>
                    <th>Course Code & Title</th>
                    <th>Attendance Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                            No attendance records match the selected filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): 
                        $badge_class = 'badge-present';
                        if ($r['status'] === 'Absent') $badge_class = 'badge-absent';
                        elseif ($r['status'] === 'Late') $badge_class = 'badge-late';
                        elseif ($r['status'] === 'Excused') $badge_class = 'badge-excused';
                    ?>
                        <tr>
                            <td class="fw-semibold text-dark">
                                <?php echo format_date($r['attendance_date']); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['student_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($r['student_email']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($r['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($r['course_code']); ?></span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($r['course_name']); ?></span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <!-- Inline Status Switcher -->
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="attendance_id" value="<?php echo $r['attendance_id']; ?>">
                                        
                                        <select name="status" class="form-select form-select-sm" style="width: 105px;" onchange="this.form.submit()">
                                            <option value="Present" <?php echo ($r['status'] === 'Present') ? 'selected' : ''; ?>>Present</option>
                                            <option value="Absent" <?php echo ($r['status'] === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                            <option value="Late" <?php echo ($r['status'] === 'Late') ? 'selected' : ''; ?>>Late</option>
                                            <option value="Excused" <?php echo ($r['status'] === 'Excused') ? 'selected' : ''; ?>>Excused</option>
                                        </select>
                                    </form>

                                    <!-- Delete Button -->
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/attendance.php'); ?>"
                                            data-id-field="attendance_id"
                                            data-record-id="<?php echo $r['attendance_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Attendance Log for <?php echo htmlspecialchars($r['student_name']); ?> on <?php echo format_date($r['attendance_date']); ?>"
                                            data-message="Are you sure you want to delete this attendance log?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <form method="POST" action="" class="d-none">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="attendance_id" value="<?php echo $r['attendance_id']; ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Attendance -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus-fill me-2"></i> Log Attendance Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($all_students as $st): ?>
                                <option value="<?php echo $st['student_id']; ?>">
                                    <?php echo htmlspecialchars($st['full_name'] . ' (' . $st['student_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Course <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>">
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Session Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Excused">Excused</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Academic Year</label>
                            <select name="academic_year_id" class="form-select">
                                <option value="">-- Select Year --</option>
                                <?php foreach ($academic_years as $ay): ?>
                                    <option value="<?php echo $ay['academic_year_id']; ?>">
                                        <?php echo htmlspecialchars($ay['year_label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $sm): ?>
                                    <option value="<?php echo $sm['semester_id']; ?>">
                                        <?php echo htmlspecialchars($sm['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
