<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// View University Student Academic Profile & Transcript
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'teacher']);

$pdo = get_db_connection();
$page_title = 'Student Academic Profile & Transcript';

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    set_flash('danger', 'Invalid student identifier.');
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// 1. Fetch Student & Academic Placement
$s_stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.created_at AS account_created,
           f.faculty_name, f.faculty_code,
           d.department_name, d.department_code,
           p.program_name, p.program_code, p.degree_type,
           y.year_name,
           sem.semester_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN faculties f ON s.faculty_id = f.faculty_id
    LEFT JOIN departments d ON s.department_id = d.department_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN academic_years y ON s.academic_year_id = y.year_id
    LEFT JOIN semesters sem ON s.semester_id = sem.semester_id
    WHERE s.student_id = :sid
    LIMIT 1
");
$s_stmt->execute(['sid' => $student_id]);
$student = $s_stmt->fetch();

if (!$student) {
    set_flash('danger', 'Student record not found.');
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// 2. Fetch Enrolled Courses
$courses_stmt = $pdo->prepare("
    SELECT c.course_id, c.course_name, c.course_code, c.credit_hours, e.enrollment_date,
           u_t.full_name AS teacher_name, t.title AS teacher_title, t.department
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    WHERE e.student_id = :sid
    ORDER BY c.course_code ASC
");
$courses_stmt->execute(['sid' => $student_id]);
$enrolled_courses = $courses_stmt->fetchAll();

// 3. Fetch Examination Results & Quality Points
$grades_stmt = $pdo->prepare("
    SELECT g.*, c.course_name, c.course_code, c.credit_hours,
           y.year_name, sem.semester_name,
           u_t.full_name AS teacher_name
    FROM grades g
    JOIN courses c ON g.course_id = c.course_id
    LEFT JOIN academic_years y ON g.academic_year_id = y.year_id
    LEFT JOIN semesters sem ON g.semester_id = sem.semester_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    WHERE g.student_id = :sid
    ORDER BY g.grade_id ASC
");
$grades_stmt->execute(['sid' => $student_id]);
$grades = $grades_stmt->fetchAll();

// Calculate CGPA & Quality Points
$total_credits = 0;
$total_quality_points = 0;
foreach ($grades as $g) {
    $cr = (int)($g['credit_hours'] ?: 3);
    $gp = (float)($g['grade_point'] ?: grade_point($g['letter_grade']));
    $total_credits += $cr;
    $total_quality_points += ($cr * $gp);
}
$cgpa = $total_credits > 0 ? round($total_quality_points / $total_credits, 2) : 0.00;
$standing = get_academic_standing($cgpa);

// 4. Attendance Summary
$att_summary_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as cnt
    FROM attendance
    WHERE student_id = :sid
    GROUP BY status
");
$att_summary_stmt->execute(['sid' => $student_id]);
$att_counts = $att_summary_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_att = array_sum($att_counts) ?: 0;
$present_cnt = $att_counts['Present'] ?? 0;
$att_rate = ($total_att > 0) ? round(($present_cnt / $total_att) * 100) : 100;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb and Top Navigation -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo base_url('admin/students.php'); ?>">Students</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($student['full_name']); ?></li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">University Academic Record & Transcript</h4>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Transcript
        </button>
        <a href="<?php echo base_url('admin/edit_student.php?id=' . $student_id); ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Edit Student
        </a>
        <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-light border">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Student Information Card -->
    <div class="col-lg-4">
        <div class="card-modern p-4 mb-4 text-center">
            <div class="user-avatar-mini mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.2rem; background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
            <div class="badge bg-primary fs-6 mb-1"><?php echo htmlspecialchars($student['student_number']); ?></div>
            <div class="text-muted small mb-2">Reg: <?php echo htmlspecialchars($student['registration_number'] ?? $student['student_number']); ?></div>
            <div class="text-muted small mb-3"><?php echo htmlspecialchars($student['email']); ?></div>

            <hr>

            <div class="text-start small">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Degree Program:</span>
                    <strong class="text-end" style="max-width: 180px;"><?php echo htmlspecialchars($student['program_name'] ?? 'Undergraduate'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Department:</span>
                    <strong><?php echo htmlspecialchars($student['department_name'] ?? 'General'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Faculty:</span>
                    <strong><?php echo htmlspecialchars($student['faculty_name'] ?? 'General'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Academic Term:</span>
                    <strong><?php echo htmlspecialchars($student['year_name'] ?? 'Current'); ?> &bull; <?php echo htmlspecialchars($student['semester_name'] ?? ''); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Contact Phone:</span>
                    <strong><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Date of Birth:</span>
                    <span><?php echo format_date($student['date_of_birth']); ?></span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Residential Address:</span>
                    <span class="text-end" style="max-width: 170px;"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Academic Performance Summary -->
        <div class="card-modern p-4 text-center">
            <h6 class="text-uppercase small fw-bold text-muted mb-3">Academic Performance Index</h6>
            <div class="bg-light p-3 rounded-3 border mb-3">
                <div class="small text-muted mb-1">Cumulative Grade Point Average (CGPA)</div>
                <div class="display-6 fw-bold text-primary"><?php echo number_format($cgpa, 2); ?></div>
                <div class="small text-muted">Scale of 4.00</div>
            </div>

            <div class="small mb-3">
                <span class="text-muted d-block mb-1">Academic Classification:</span>
                <span class="<?php echo $standing['class']; ?> fs-6"><?php echo $standing['title']; ?></span>
            </div>

            <div class="row g-2 text-center pt-2 border-top">
                <div class="col-6">
                    <small class="text-muted d-block">Total Credits</small>
                    <strong class="fs-5 text-dark"><?php echo $total_credits; ?> Units</strong>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block">Quality Points</small>
                    <strong class="fs-5 text-dark"><?php echo number_format($total_quality_points, 1); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Official Academic Transcript & Enrolled Courses -->
    <div class="col-lg-8">
        <!-- Official Academic Transcript -->
        <div class="card-modern mb-4">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-award-fill text-warning"></i> Official Academic Transcript
                </h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    Official Grade Sheet
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Course Code & Title</th>
                            <th>Credits</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th>Points</th>
                            <th>Quality Pts</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grades)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-award fs-1 text-muted d-block mb-2"></i>
                                    No examination results recorded for this student yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grades as $g): 
                                $cr = (int)($g['credit_hours'] ?: 3);
                                $gp = (float)($g['grade_point'] ?: grade_point($g['letter_grade']));
                                $qp = $cr * $gp;

                                $badge_cls = 'badge-grade-b';
                                $l = substr($g['letter_grade'], 0, 1);
                                if ($l === 'A') $badge_cls = 'badge-grade-a';
                                elseif ($l === 'C') $badge_cls = 'badge-grade-c';
                                elseif ($l === 'D') $badge_cls = 'badge-grade-d';
                                elseif ($l === 'F') $badge_cls = 'badge-grade-f';
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary me-1"><?php echo htmlspecialchars($g['course_code']); ?></span>
                                        <strong><?php echo htmlspecialchars($g['course_name']); ?></strong>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $cr; ?></span></td>
                                    <td><strong class="text-dark"><?php echo $g['score']; ?>%</strong></td>
                                    <td>
                                        <span class="badge-grade <?php echo $badge_cls; ?>">
                                            <?php echo htmlspecialchars($g['letter_grade']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($gp, 1); ?></td>
                                    <td><strong><?php echo number_format($qp, 1); ?></strong></td>
                                    <td class="small text-muted" style="max-width: 160px;"><?php echo htmlspecialchars($g['remarks'] ?? 'Completed'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enrolled Courses -->
        <div class="card-modern mb-4">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-journal-bookmark text-primary"></i> Currently Enrolled Courses (<?php echo count($enrolled_courses); ?>)
                </h2>
                <a href="<?php echo base_url('admin/enrollments.php?student_id=' . $student_id); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle me-1"></i> Manage Enrollments
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Title</th>
                            <th>Credit Units</th>
                            <th>Lecturer</th>
                            <th>Enrolled Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrolled_courses)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Student is not currently enrolled in any courses.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrolled_courses as $c): ?>
                                <tr>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($c['course_code']); ?></span></td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($c['course_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo (int)$c['credit_hours']; ?> Credits</span></td>
                                    <td class="small">
                                        <?php echo htmlspecialchars($c['teacher_name'] ?? 'Instructor Pending'); ?>
                                    </td>
                                    <td class="small text-muted"><?php echo format_date($c['enrollment_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
