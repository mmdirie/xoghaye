<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - Lecturer Assigned Courses & Rosters
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pdo = get_db_connection();
$page_title = 'My Assigned Courses & Class Rosters';
$user = current_user();

// Fetch Teacher record
$tStmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :uid LIMIT 1");
$tStmt->execute(['uid' => $user['user_id']]);
$teacher_id = (int)$tStmt->fetchColumn();

// Fetch Assigned Courses only
$cStmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrolled_count
    FROM courses c
    WHERE c.teacher_id = :tid
    ORDER BY c.course_code ASC
");
$cStmt->execute(['tid' => $teacher_id]);
$courses = $cStmt->fetchAll();

// Active course filter if specified
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['course_id'] ?? 0);

// Fetch enrolled students for the selected course (with attendance % and current grade)
$students = [];
$active_course = null;

if ($selected_course_id > 0) {
    // Verify this course belongs to this teacher!
    $chkStmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :cid AND teacher_id = :tid LIMIT 1");
    $chkStmt->execute(['cid' => $selected_course_id, 'tid' => $teacher_id]);
    $active_course = $chkStmt->fetch();

    if ($active_course) {
        $stuStmt = $pdo->prepare("
            SELECT s.student_id, s.student_number, s.phone,
                   u.full_name, u.email,
                   e.enrollment_date,
                   g.score, g.letter_grade, g.grade_point, g.remarks,
                   (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.student_id AND a.course_id = :cid) as total_sessions,
                   (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.student_id AND a.course_id = :cid AND a.status = 'Present') as present_sessions
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            LEFT JOIN grades g ON (g.student_id = s.student_id AND g.course_id = :cid)
            WHERE e.course_id = :cid
            ORDER BY u.full_name ASC
        ");
        $stuStmt->execute(['cid' => $selected_course_id]);
        $students = $stuStmt->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">My Assigned Courses & Class Rosters</h4>
        <p class="text-muted small mb-0">Review student enrollments, academic performance, and attendance records for your assigned subjects.</p>
    </div>
    <?php if ($active_course): ?>
        <div class="d-flex gap-2">
            <a href="<?php echo base_url('teacher/attendance.php?course_id=' . $active_course['course_id']); ?>" class="btn btn-outline-success">
                <i class="bi bi-calendar-check me-1"></i> Take Attendance
            </a>
            <a href="<?php echo base_url('teacher/grades.php?course_id=' . $active_course['course_id']); ?>" class="btn btn-outline-warning text-dark">
                <i class="bi bi-award me-1"></i> Enter Grades
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($courses)): ?>
    <div class="card-modern text-center py-5">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
        <h5>No Assigned Courses</h5>
        <p class="text-muted">You have not been assigned to teach any courses yet. Please contact your system administrator.</p>
    </div>
<?php else: ?>
    <!-- Course Selection Nav Pills -->
    <ul class="nav nav-pills mb-4 gap-2 flex-wrap bg-white p-2 rounded-3 border shadow-sm">
        <?php foreach ($courses as $c): ?>
            <li class="nav-item">
                <a class="nav-link py-2 px-3 fw-semibold <?php echo ($selected_course_id === (int)$c['course_id']) ? 'active bg-primary' : 'text-dark'; ?>" 
                   href="<?php echo base_url('teacher/courses.php?course_id=' . $c['course_id']); ?>">
                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($c['course_code']); ?></span>
                    <?php echo htmlspecialchars($c['course_name']); ?>
                    <span class="badge bg-white text-primary ms-1"><?php echo (int)$c['enrolled_count']; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($active_course): ?>
        <!-- Course Details Banner -->
        <div class="card-modern p-4 mb-4 bg-light border">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($active_course['course_code']); ?></span>
                        <span class="badge bg-secondary"><?php echo (int)($active_course['credit_hours'] ?? 3); ?> Credit Hours</span>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($active_course['course_name']); ?></h3>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($active_course['description'] ?? 'Curriculum syllabus module.'); ?></p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-block text-start bg-white p-3 rounded border">
                        <div class="small text-muted">Class Enrollment:</div>
                        <div class="fw-bold fs-4 text-primary"><?php echo count($students); ?> Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Roster Table -->
        <div class="card-modern">
            <div class="card-modern-header flex-column flex-md-row gap-3">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-people-fill text-primary"></i> Class Roster (<?php echo count($students); ?>)
                </h2>
                <div class="input-group" style="max-width: 280px;">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search roster...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern table-searchable align-middle">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student #</th>
                            <th>Contact Phone</th>
                            <th>Course Attendance</th>
                            <th>Current Grade</th>
                            <th>Grade Point</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                    No students are enrolled in this course yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $st): 
                                $rate = ($st['total_sessions'] > 0) ? round(($st['present_sessions'] / $st['total_sessions']) * 100) : null;
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-mini me-2" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                                <?php echo strtoupper(substr($st['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['full_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($st['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($st['student_number']); ?></span>
                                    </td>
                                    <td class="small">
                                        <?php echo htmlspecialchars($st['phone'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php if ($rate !== null): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold <?php echo ($rate >= 75) ? 'text-success' : 'text-danger'; ?>"><?php echo $rate; ?>%</span>
                                                <small class="text-muted">(<?php echo $st['present_sessions']; ?>/<?php echo $st['total_sessions']; ?>)</small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">No logs yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($st['score'] !== null): ?>
                                            <span class="fw-bold"><?php echo $st['score']; ?>%</span>
                                            <span class="badge-grade badge-grade-<?php echo strtolower(substr($st['letter_grade'], 0, 1)); ?> ms-1">
                                                <?php echo htmlspecialchars($st['letter_grade']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Not Graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($st['score'] !== null): ?>
                                            <span class="fw-bold text-dark"><?php echo number_format($st['grade_point'] ?? calculate_grade_point($st['score']), 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted" style="max-width: 200px;">
                                        <?php echo htmlspecialchars($st['remarks'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
