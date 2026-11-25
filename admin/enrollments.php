<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Student Course Enrollment Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Student Course Enrollments';

$selected_course_id  = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_sem_id     = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

// Handle Form Submissions (Enroll, Drop)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/enrollments.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Enroll Student
    if ($action === 'enroll') {
        $student_id       = (int)($_POST['student_id'] ?? 0);
        $course_id        = (int)($_POST['course_id'] ?? 0);
        $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
        $semester_id      = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;

        if ($student_id > 0 && $course_id > 0) {
            // Check if already enrolled in this course
            $chk = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = :sid AND course_id = :cid");
            $chk->execute(['sid' => $student_id, 'cid' => $course_id]);
            if ($chk->fetch()) {
                set_flash('warning', 'This student is already enrolled in that course.');
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO enrollments (student_id, course_id, academic_year_id, semester_id, enrollment_date) 
                    VALUES (:sid, :cid, :yid, :semid, CURRENT_DATE)
                ");
                $ins->execute([
                    'sid'   => $student_id,
                    'cid'   => $course_id,
                    'yid'   => $academic_year_id,
                    'semid' => $semester_id
                ]);
                set_flash('success', 'Student successfully enrolled in course.');
            }
        } else {
            set_flash('danger', 'Please select both a student and a course.');
        }
        header('Location: ' . base_url('admin/enrollments.php' . ($course_id > 0 ? '?course_id=' . $course_id : '')));
        exit;
    }

    // Action 2: Drop / Delete Enrollment
    if ($action === 'drop') {
        $enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
        if ($enrollment_id <= 0) {
            set_flash('danger', 'Invalid enrollment ID.');
        } else {
            try {
                $del = $pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = :eid");
                $del->execute(['eid' => $enrollment_id]);
                if ($del->rowCount() === 1) {
                    set_flash('success', 'Course enrollment dropped successfully.');
                } else {
                    set_flash('danger', 'Enrollment not found; no record was deleted.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Enrollment deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }
        $redirect_cid = (int)($_POST['redirect_cid'] ?? 0);
        header('Location: ' . base_url('admin/enrollments.php' . ($redirect_cid > 0 ? '?course_id=' . $redirect_cid : '')));
        exit;
    }
}

// Fetch Dropdown data
$courses = $pdo->query("SELECT course_id, course_name, course_code, credit_hours FROM courses ORDER BY course_code ASC")->fetchAll();
$students = $pdo->query("
    SELECT s.student_id, s.student_number, u.full_name, u.email 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY u.full_name ASC
")->fetchAll();
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name, is_current FROM semesters ORDER BY semester_name ASC")->fetchAll();

// Build query
$where_clauses = [];
$params = [];

if ($selected_course_id > 0) {
    $where_clauses[] = "e.course_id = :cid";
    $params['cid'] = $selected_course_id;
}
if ($selected_student_id > 0) {
    $where_clauses[] = "e.student_id = :sid";
    $params['sid'] = $selected_student_id;
}
if ($selected_sem_id > 0) {
    $where_clauses[] = "e.semester_id = :semid";
    $params['semid'] = $selected_sem_id;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$enr_stmt = $pdo->prepare("
    SELECT e.enrollment_id, e.enrollment_date,
           s.student_id, s.student_number, u.full_name AS student_name, u.email AS student_email,
           c.course_id, c.course_code, c.course_name, c.credit_hours,
           t.title AS teacher_title, u_t.full_name AS teacher_name,
           y.year_name, sem.semester_name
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    LEFT JOIN academic_years y ON e.academic_year_id = y.year_id
    LEFT JOIN semesters sem ON e.semester_id = sem.semester_id
    $where_sql
    ORDER BY c.course_code ASC, u.full_name ASC
");
$enr_stmt->execute($params);
$enrollments = $enr_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Student Course Enrollments</h4>
        <p class="text-muted small mb-0">Register students into degree course units and manage academic class rosters.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#enrollModal">
        <i class="bi bi-person-plus-fill me-1"></i> Enroll Student in Course
    </button>
</div>

<!-- Filters Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Filter by Course:</label>
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Courses (<?php echo count($courses); ?>) --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['course_id']; ?>" <?php echo ($selected_course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?> (<?php echo $c['credit_hours']; ?> cr)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Filter by Semester:</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Semesters --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s['semester_id']; ?>" <?php echo ($selected_sem_id === (int)$s['semester_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Search within Table:</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter student or unit...">
            </div>
            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($selected_course_id > 0 || $selected_student_id > 0 || $selected_sem_id > 0): ?>
                    <a href="<?php echo base_url('admin/enrollments.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Enrollments Table -->
<div class="card-modern">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-people-fill text-primary"></i> Active Course Enrollments (<?php echo count($enrollments); ?>)
        </h2>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Course Code & Title</th>
                    <th>Credits</th>
                    <th>Lecturer Assigned</th>
                    <th>Enrollment Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrollments)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-ui-checks fs-1 text-muted d-block mb-2"></i>
                            No enrollments found. Click <strong>Enroll Student in Course</strong> to add one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $en): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar-mini me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($en['student_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($en['student_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($en['student_email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($en['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($en['course_code']); ?></span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($en['course_name']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo (int)$en['credit_hours']; ?> Units</span>
                            </td>
                            <td>
                                <span class="small text-muted"><?php echo htmlspecialchars(($en['teacher_title'] ?? '') . ' ' . ($en['teacher_name'] ?? 'Unassigned')); ?></span>
                            </td>
                            <td class="small text-muted">
                                <?php echo format_date($en['enrollment_date']); ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-confirm-delete"
                                        data-action="<?php echo base_url('admin/enrollments.php'); ?>"
                                        data-submit-action="drop"
                                        data-id-field="enrollment_id"
                                        data-record-id="<?php echo $en['enrollment_id']; ?>"
                                        data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                        data-item-name="<?php echo htmlspecialchars($en['student_name'] . ' from ' . $en['course_code']); ?>"
                                        data-message="Are you sure you want to drop this student from the course?">
                                    <i class="bi bi-person-dash me-1"></i> Drop
                                </button>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="drop">
                                    <input type="hidden" name="enrollment_id" value="<?php echo $en['enrollment_id']; ?>">
                                    <input type="hidden" name="redirect_cid" value="<?php echo $selected_course_id; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Enroll Student -->
<div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i> Enroll Student in Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="enroll">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Select Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['student_id']; ?>">
                                    <?php echo htmlspecialchars($s['full_name'] . ' (' . $s['student_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Select Course Unit <span class="text-danger">*</span></label>
                        <select name="course_id" class="form-select" required>
                            <option value="">-- Choose Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>" <?php echo ($selected_course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?> (<?php echo $c['credit_hours']; ?> cr)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Academic Year</label>
                            <select name="academic_year_id" class="form-select">
                                <option value="">-- Academic Year --</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y['year_id']; ?>" <?php echo ($y['is_current'] == 1) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($y['year_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>" <?php echo ($s['is_current'] == 1) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Enroll Student</button>
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
