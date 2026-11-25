<?php
// ==========================================================
// SCHOOL MANAGER - Student Personal Dashboard
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = get_db_connection();
$page_title = 'Student Academic Portal';
$user = current_user();

// Fetch student profile record
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $user['user_id']]);
$student = $sStmt->fetch();

if (!$student) {
    set_flash('danger', 'Student profile record not found. Please contact administration.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$student_id = (int)$student['student_id'];

// 1. Fetch Enrolled Courses
$cStmt = $pdo->prepare("
    SELECT c.*, e.enrollment_date,
           u.full_name AS teacher_name, t.department
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE e.student_id = :sid
    ORDER BY c.course_code ASC
");
$cStmt->execute(['sid' => $student_id]);
$enrolled_courses = $cStmt->fetchAll();
$total_enrolled = count($enrolled_courses);

// 2. Fetch Attendance Summary
$attStmt = $pdo->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM attendance 
    WHERE student_id = :sid 
    GROUP BY status
");
$attStmt->execute(['sid' => $student_id]);
$att_counts = $attStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_sessions = array_sum($att_counts) ?: 0;
$present_count  = $att_counts['Present'] ?? 0;
$att_rate = ($total_sessions > 0) ? round(($present_count / $total_sessions) * 100) : 100;

// 3. Fetch Grades Summary & GPA
$gStmt = $pdo->prepare("
    SELECT g.*, c.course_name, c.course_code,
           u.full_name AS teacher_name
    FROM grades g
    JOIN courses c ON g.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE g.student_id = :sid
    ORDER BY c.course_code ASC
");
$gStmt->execute(['sid' => $student_id]);
$grades = $gStmt->fetchAll();

$grade_count = count($grades);
$total_points = 0;
$total_score_sum = 0;
foreach ($grades as $g) {
    $total_points += grade_point($g['letter_grade']);
    $total_score_sum += (float)$g['score'];
}
$gpa = $grade_count > 0 ? round($total_points / $grade_count, 2) : 0.00;
$avg_score = $grade_count > 0 ? round($total_score_sum / $grade_count, 1) : 0.0;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-modern p-4" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: #fff;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-primary fw-bold mb-2">STUDENT PORTAL &bull; <?php echo htmlspecialchars($student['student_number']); ?></span>
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                    <p class="mb-0 text-white-50">Track your academic progress, verify course attendance, and check your official term grades.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?php echo base_url('student/grades.php'); ?>" class="btn btn-warning text-dark fw-bold me-2 shadow-sm">
                        <i class="bi bi-award-fill me-1"></i> View Report Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Enrolled Courses</div>
                <div class="card-stat-value"><?php echo $total_enrolled; ?></div>
                <small class="text-primary fw-semibold"><i class="bi bi-journal-check me-1"></i>Active Subjects</small>
            </div>
            <div class="card-stat-icon icon-blue">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Attendance Rate</div>
                <div class="card-stat-value text-success"><?php echo $att_rate; ?>%</div>
                <small class="text-muted"><?php echo $present_count; ?> of <?php echo $total_sessions; ?> Sessions</small>
            </div>
            <div class="card-stat-icon icon-emerald">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Cumulative GPA</div>
                <div class="card-stat-value text-primary"><?php echo number_format($gpa, 2); ?></div>
                <small class="text-muted">4.0 Scale Standard</small>
            </div>
            <div class="card-stat-icon icon-amber">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Mean Score</div>
                <div class="card-stat-value text-info"><?php echo $avg_score; ?>%</div>
                <small class="text-muted"><?php echo $grade_count; ?> Courses Evaluated</small>
            </div>
            <div class="card-stat-icon icon-purple">
                <i class="bi bi-award-fill"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Student Profile & Enrolled Courses -->
    <div class="col-lg-7">
        <!-- Enrolled Courses -->
        <div class="card-modern mb-4">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-journal-bookmark text-primary"></i> My Enrolled Courses
                </h2>
                <a href="<?php echo base_url('student/courses.php'); ?>" class="btn btn-sm btn-light border">
                    All Courses <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Title</th>
                            <th>Instructor</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrolled_courses)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">You are not currently enrolled in any courses.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrolled_courses as $c): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($c['course_code']); ?></span></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($c['course_name']); ?></div>
                                    </td>
                                    <td class="small">
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($c['teacher_name'] ?? 'Instructor Pending'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['department'] ?? ''); ?></small>
                                    </td>
                                    <td class="small text-muted"><?php echo format_date($c['enrollment_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Results -->
        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-award text-warning"></i> Recent Academic Results
                </h2>
                <a href="<?php echo base_url('student/grades.php'); ?>" class="btn btn-sm btn-light border">
                    Full Report <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grades)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No course grades recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grades as $g): 
                                $badge_cls = 'badge-grade-b';
                                $l = substr($g['letter_grade'], 0, 1);
                                if ($l === 'A') $badge_cls = 'badge-grade-a';
                                elseif ($l === 'C') $badge_cls = 'badge-grade-c';
                                elseif ($l === 'D') $badge_cls = 'badge-grade-d';
                                elseif ($l === 'F') $badge_cls = 'badge-grade-f';
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($g['course_code']); ?></span>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($g['course_name']); ?></span>
                                    </td>
                                    <td><strong class="fs-6"><?php echo $g['score']; ?>%</strong></td>
                                    <td><span class="badge-grade <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($g['letter_grade']); ?></span></td>
                                    <td class="small text-muted" style="max-width: 180px;"><?php echo htmlspecialchars($g['remarks'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Student Info & Attendance Summary -->
    <div class="col-lg-5">
        <!-- Profile Card -->
        <div class="card-modern p-4 mb-4 text-center">
            <div class="user-avatar-mini mx-auto mb-3" style="width: 72px; height: 72px; font-size: 2rem; background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
            <div class="text-primary fw-bold mb-1"><?php echo htmlspecialchars($student['student_number']); ?></div>
            <div class="text-muted small mb-3"><?php echo htmlspecialchars($user['email']); ?></div>

            <hr>

            <div class="text-start small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted"><i class="bi bi-telephone me-2"></i>Phone:</span>
                    <strong><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted"><i class="bi bi-calendar me-2"></i>Date of Birth:</span>
                    <strong><?php echo format_date($student['date_of_birth']); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted"><i class="bi bi-geo-alt me-2"></i>Address:</span>
                    <span class="text-end" style="max-width: 170px;"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <a href="<?php echo base_url('student/profile.php'); ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                <i class="bi bi-pencil-square me-1"></i> Update Contact Details
            </a>
        </div>

        <!-- Attendance Breakdown -->
        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-pie-chart-fill text-success"></i> Attendance Overview
                </h2>
                <a href="<?php echo base_url('student/attendance.php'); ?>" class="btn btn-sm btn-light border">
                    History <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-modern-body p-4">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Overall Presence:</span>
                        <span class="text-success"><?php echo $att_rate; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $att_rate; ?>%;"></div>
                    </div>
                </div>

                <div class="row g-2 text-center pt-2">
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <small class="text-muted d-block">Present</small>
                            <strong class="text-success fs-5"><?php echo $att_counts['Present'] ?? 0; ?></strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <small class="text-muted d-block">Late</small>
                            <strong class="text-warning fs-5"><?php echo $att_counts['Late'] ?? 0; ?></strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <small class="text-muted d-block">Absent</small>
                            <strong class="text-danger fs-5"><?php echo $att_counts['Absent'] ?? 0; ?></strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <small class="text-muted d-block">Excused</small>
                            <strong class="text-info fs-5"><?php echo $att_counts['Excused'] ?? 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
