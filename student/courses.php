<?php
// ==========================================================
// SCHOOL MANAGER - Student Enrolled Courses
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = get_db_connection();
$page_title = 'My Enrolled Courses';
$user = current_user();

// Fetch student record
$sStmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $user['user_id']]);
$student_id = (int)$sStmt->fetchColumn();

// Fetch courses enrolled by this student
$cStmt = $pdo->prepare("
    SELECT c.*, e.enrollment_date,
           u_t.full_name AS teacher_name, u_t.email AS teacher_email,
           t.department,
           g.score, g.letter_grade, g.remarks,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = :sid AND a.course_id = c.course_id) as total_sessions,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = :sid AND a.course_id = c.course_id AND a.status = 'Present') as present_sessions
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    LEFT JOIN grades g ON (g.student_id = :sid AND g.course_id = c.course_id)
    WHERE e.student_id = :sid
    ORDER BY c.course_code ASC
");
$cStmt->execute(['sid' => $student_id]);
$courses = $cStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">My Enrolled Courses</h4>
        <p class="text-muted small mb-0">Overview of your current academic curriculum, instructors, attendance standing, and course marks.</p>
    </div>
    <span class="badge bg-primary fs-6 px-3 py-2">
        <?php echo count($courses); ?> Active Courses
    </span>
</div>

<?php if (empty($courses)): ?>
    <div class="card-modern text-center py-5">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
        <h5>No Courses Enrolled</h5>
        <p class="text-muted">You are not currently registered for any courses. Please contact the administrator or your academic advisor.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($courses as $c): 
            $rate = ($c['total_sessions'] > 0) ? round(($c['present_sessions'] / $c['total_sessions']) * 100) : 100;
        ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-modern h-100 d-flex flex-column mb-0">
                    <div class="card-modern-header">
                        <span class="badge bg-primary fs-6 fw-bold"><?php echo htmlspecialchars($c['course_code']); ?></span>
                        <small class="text-muted">Enrolled: <?php echo format_date($c['enrollment_date']); ?></small>
                    </div>

                    <div class="card-modern-body flex-grow-1 p-4">
                        <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($c['course_name']); ?></h5>
                        <p class="small text-muted mb-4"><?php echo htmlspecialchars($c['description'] ?? 'Fundamental study module.'); ?></p>

                        <!-- Instructor snippet -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <small class="text-muted d-block mb-1">Course Instructor:</small>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['teacher_name'] ?? 'Instructor Unassigned'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($c['department'] ?? 'Department'); ?></div>
                            <?php if (!empty($c['teacher_email'])): ?>
                                <small class="text-primary"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($c['teacher_email']); ?></small>
                            <?php endif; ?>
                        </div>

                        <!-- Attendance in this course -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small fw-semibold mb-1">
                                <span>Course Attendance:</span>
                                <span class="<?php echo ($rate >= 75) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $rate; ?>% (<?php echo $c['present_sessions']; ?>/<?php echo $c['total_sessions']; ?>)
                                </span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?php echo ($rate >= 75) ? 'bg-success' : 'bg-danger'; ?>" style="width: <?php echo $rate; ?>%;"></div>
                            </div>
                        </div>

                        <!-- Current Grade Status -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <span class="small text-muted">Current Evaluation:</span>
                            <?php if ($c['score'] !== null): ?>
                                <div>
                                    <strong class="text-dark me-1"><?php echo $c['score']; ?>%</strong>
                                    <span class="badge-grade badge-grade-<?php echo strtolower(substr($c['letter_grade'], 0, 1)); ?>">
                                        <?php echo htmlspecialchars($c['letter_grade']); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">In Progress</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
