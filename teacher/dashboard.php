<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - Lecturer & Faculty Dashboard
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pdo = get_db_connection();
$page_title = 'Lecturer Academic Dashboard';
$user = current_user();

// Fetch Teacher / Lecturer Profile
$tStmt = $pdo->prepare("
    SELECT t.*, f.faculty_name, d.department_name 
    FROM teachers t
    LEFT JOIN faculties f ON t.faculty_id = f.faculty_id
    LEFT JOIN departments d ON t.department_id = d.department_id
    WHERE t.user_id = :uid 
    LIMIT 1
");
$tStmt->execute(['uid' => $user['user_id']]);
$teacher = $tStmt->fetch();

if (!$teacher) {
    set_flash('danger', 'Lecturer profile record not found. Please contact the administrator.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$teacher_id = (int)$teacher['teacher_id'];

// 1. Fetch Assigned Courses & Student counts
$cStmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrolled_count
    FROM courses c
    WHERE c.teacher_id = :tid
    ORDER BY c.course_code ASC
");
$cStmt->execute(['tid' => $teacher_id]);
$assigned_courses = $cStmt->fetchAll();

$assigned_course_ids = array_column($assigned_courses, 'course_id');
$total_assigned_courses = count($assigned_courses);

// Count unique students across all assigned courses
$total_students = 0;
if (!empty($assigned_course_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($assigned_course_ids), '?'));
    $stuCntStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE course_id IN ($in_placeholders)");
    $stuCntStmt->execute($assigned_course_ids);
    $total_students = (int)$stuCntStmt->fetchColumn();
}

// 2. Fetch Attendance and Grade Totals for this Lecturer
$total_attendance_sessions = 0;
$total_grades_submitted = 0;

if (!empty($assigned_course_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($assigned_course_ids), '?'));
    
    // Attendance count
    $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE course_id IN ($in_placeholders)");
    $attStmt->execute($assigned_course_ids);
    $total_attendance_sessions = (int)$attStmt->fetchColumn();

    // Grades count
    $grdStmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE course_id IN ($in_placeholders)");
    $grdStmt->execute($assigned_course_ids);
    $total_grades_submitted = (int)$grdStmt->fetchColumn();
}

// 3. Recent Attendance Entries in Lecturer's Courses
$recent_att = [];
if (!empty($assigned_course_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($assigned_course_ids), '?'));
    $recAttStmt = $pdo->prepare("
        SELECT a.*, c.course_code, c.course_name, s.student_number, u.full_name AS student_name
        FROM attendance a
        JOIN courses c ON a.course_id = c.course_id
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        WHERE a.course_id IN ($in_placeholders)
        ORDER BY a.attendance_date DESC, a.attendance_id DESC
        LIMIT 6
    ");
    $recAttStmt->execute($assigned_course_ids);
    $recent_att = $recAttStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-modern p-4" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: #fff;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-dark fw-bold mb-2">
                        <?php echo htmlspecialchars($teacher['department_name'] ?? $teacher['department'] ?? 'Academic Faculty'); ?>
                    </span>
                    <h2 class="fw-bold mb-1">
                        Welcome, <?php echo htmlspecialchars(($teacher['title'] ? $teacher['title'] . ' ' : '') . $user['full_name']); ?>!
                    </h2>
                    <p class="mb-0 text-white-50">Manage your university courses, track student attendance, and submit semester examination results.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?php echo base_url('teacher/attendance.php'); ?>" class="btn btn-light text-dark fw-semibold me-2 shadow-sm">
                        <i class="bi bi-calendar-check me-1"></i> Take Attendance
                    </a>
                    <a href="<?php echo base_url('teacher/grades.php'); ?>" class="btn btn-warning text-dark fw-semibold shadow-sm">
                        <i class="bi bi-award-fill me-1"></i> Enter Exam Grades
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Assigned Courses</div>
                <div class="card-stat-value"><?php echo $total_assigned_courses; ?></div>
                <small class="text-primary fw-semibold"><i class="bi bi-journal-bookmark me-1"></i>Taught Modules</small>
            </div>
            <div class="card-stat-icon icon-blue">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Enrolled Students</div>
                <div class="card-stat-value"><?php echo $total_students; ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-people me-1"></i>Active Students</small>
            </div>
            <div class="card-stat-icon icon-emerald">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Attendance Entries</div>
                <div class="card-stat-value"><?php echo $total_attendance_sessions; ?></div>
                <small class="text-amber fw-semibold"><i class="bi bi-check-circle me-1"></i>Sessions Recorded</small>
            </div>
            <div class="card-stat-icon icon-amber">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Grades Evaluated</div>
                <div class="card-stat-value"><?php echo $total_grades_submitted; ?></div>
                <small class="text-purple fw-semibold"><i class="bi bi-award me-1"></i>Graded Results</small>
            </div>
            <div class="card-stat-icon icon-purple">
                <i class="bi bi-award-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Assigned Courses Cards -->
<div class="card-modern mb-4">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-journal-check text-primary"></i> My University Courses
        </h2>
        <a href="<?php echo base_url('teacher/courses.php'); ?>" class="btn btn-sm btn-light border">
            View All Course Rosters <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-modern-body p-4">
        <?php if (empty($assigned_courses)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
                You do not have any courses assigned to you at the moment. Please contact the Dean or Administrator.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($assigned_courses as $c): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border rounded-3 shadow-sm hover-shadow">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary fs-6 fw-bold"><?php echo htmlspecialchars($c['course_code']); ?></span>
                                    <span class="badge bg-light text-secondary border">
                                        <i class="bi bi-mortarboard me-1"></i><?php echo (int)($c['credit_hours'] ?? 3); ?> Credits
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($c['course_name']); ?></h5>
                                <p class="small text-muted flex-grow-1"><?php echo htmlspecialchars($c['description'] ?? 'Curriculum module.'); ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-info-subtle text-info border">
                                        <i class="bi bi-people-fill me-1"></i><?php echo (int)$c['enrolled_count']; ?> Enrolled
                                    </span>
                                </div>

                                <div class="d-flex gap-2 pt-2 border-top">
                                    <a href="<?php echo base_url('teacher/attendance.php?course_id=' . $c['course_id']); ?>" class="btn btn-sm btn-outline-success flex-fill">
                                        <i class="bi bi-calendar-check me-1"></i> Attendance
                                    </a>
                                    <a href="<?php echo base_url('teacher/grades.php?course_id=' . $c['course_id']); ?>" class="btn btn-sm btn-outline-warning text-dark flex-fill">
                                        <i class="bi bi-award me-1"></i> Enter Grades
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Attendance Entries -->
<div class="card-modern">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-clock-history text-secondary"></i> Recent Attendance Submissions
        </h2>
        <a href="<?php echo base_url('teacher/attendance.php'); ?>" class="btn btn-sm btn-light border">
            Review Attendance <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Student #</th>
                    <th>Course</th>
                    <th>Recorded Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_att)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No attendance sessions recorded yet for your courses.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_att as $ra): 
                        $badge_class = 'badge-present';
                        if ($ra['status'] === 'Absent') $badge_class = 'badge-absent';
                        elseif ($ra['status'] === 'Late') $badge_class = 'badge-late';
                        elseif ($ra['status'] === 'Excused') $badge_class = 'badge-excused';
                    ?>
                        <tr>
                            <td><?php echo format_date($ra['attendance_date']); ?></td>
                            <td class="fw-semibold text-dark"><?php echo htmlspecialchars($ra['student_name']); ?></td>
                            <td><span class="badge bg-light text-primary border"><?php echo htmlspecialchars($ra['student_number']); ?></span></td>
                            <td>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($ra['course_code']); ?></span>
                                <span class="small fw-semibold"><?php echo htmlspecialchars($ra['course_name']); ?></span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($ra['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
