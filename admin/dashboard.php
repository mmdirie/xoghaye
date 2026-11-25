<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Administrator Dashboard
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'University Dashboard';

// 1. Fetch Key University Metrics
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_teachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$total_courses  = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_faculties = (int)$pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
$total_departments = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$total_programs = (int)$pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();

// 2. Fetch Active Academic Year & Semester
$active_year = $pdo->query("SELECT year_name FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn() ?: 'Academic Year Pending';
$active_semester = $pdo->query("SELECT semester_name FROM semesters WHERE is_current = 1 LIMIT 1")->fetchColumn() ?: 'Semester Pending';

// 3. Fetch Recent Student Registrations
$recent_students_stmt = $pdo->query("
    SELECT s.student_id, s.student_number, s.registration_number, u.full_name, u.email, s.created_at,
           p.program_name, d.department_code
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON s.department_id = d.department_id
    ORDER BY s.student_id DESC
    LIMIT 6
");
$recent_students = $recent_students_stmt->fetchAll();

// 4. Fetch Recent Exam Results
$recent_grades_stmt = $pdo->query("
    SELECT g.*, u.full_name AS student_name, s.student_number,
           c.course_code, c.course_name
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN courses c ON g.course_id = c.course_id
    ORDER BY g.grade_id DESC
    LIMIT 6
");
$recent_grades = $recent_grades_stmt->fetchAll();

// 5. Total Attendance Summary
$att_stats_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM attendance GROUP BY status");
$att_stats = $att_stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_att_records = array_sum($att_stats) ?: 1;
$present_pct = round((($att_stats['Present'] ?? 0) / $total_att_records) * 100);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- University Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card-modern p-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%);">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white text-primary fw-bold mb-2">
                        <i class="bi bi-mortarboard-fill me-1"></i> UNIVERSITY RESULT MANAGER &bull; REGISTRAR PORTAL
                    </span>
                    <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h2>
                    <p class="mb-0 text-white-50">
                        Active Session: <strong><?php echo htmlspecialchars($active_year); ?></strong> &bull; 
                        Current Term: <strong><?php echo htmlspecialchars($active_semester); ?></strong>
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="<?php echo base_url('admin/add_student.php'); ?>" class="btn btn-light text-primary fw-bold shadow-sm me-2">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Student
                    </a>
                    <a href="<?php echo base_url('admin/grades.php'); ?>" class="btn btn-warning text-dark fw-bold shadow-sm">
                        <i class="bi bi-award-fill me-1"></i> Exam Results
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
                <div class="card-stat-title">University Students</div>
                <div class="card-stat-value"><?php echo $total_students; ?></div>
                <small class="text-primary fw-semibold"><i class="bi bi-mortarboard me-1"></i>Enrolled Learners</small>
            </div>
            <div class="card-stat-icon icon-blue">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Lecturers & Faculty</div>
                <div class="card-stat-value"><?php echo $total_teachers; ?></div>
                <small class="text-success fw-semibold"><i class="bi bi-person-badge me-1"></i>Academic Staff</small>
            </div>
            <div class="card-stat-icon icon-emerald">
                <i class="bi bi-person-badge-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Courses & Units</div>
                <div class="card-stat-value"><?php echo $total_courses; ?></div>
                <small class="text-amber fw-semibold"><i class="bi bi-journal-bookmark me-1"></i>Curriculum Units</small>
            </div>
            <div class="card-stat-icon icon-amber">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div>
                <div class="card-stat-title">Faculties & Depts</div>
                <div class="card-stat-value"><?php echo $total_faculties; ?> / <?php echo $total_departments; ?></div>
                <small class="text-purple fw-semibold"><i class="bi bi-buildings me-1"></i><?php echo $total_programs; ?> Degree Programs</small>
            </div>
            <div class="card-stat-icon icon-purple">
                <i class="bi bi-buildings-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Quick Navigation Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-lightning-charge-fill text-warning"></i> Academic Administration Shortcuts
        </h2>
    </div>
    <div class="card-modern-body py-3">
        <div class="row g-2">
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/faculties.php'); ?>" class="btn btn-outline-primary w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-buildings-fill fs-5 me-2"></i>
                    <span>Faculties</span>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/departments.php'); ?>" class="btn btn-outline-info w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-diagram-3-fill fs-5 me-2"></i>
                    <span>Departments</span>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/programs.php'); ?>" class="btn btn-outline-success w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-mortarboard-fill fs-5 me-2"></i>
                    <span>Programs</span>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/courses.php'); ?>" class="btn btn-outline-warning text-dark w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-journal-plus fs-5 me-2"></i>
                    <span>Courses</span>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/enrollments.php'); ?>" class="btn btn-outline-dark w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-ui-checks fs-5 me-2"></i>
                    <span>Enrollments</span>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?php echo base_url('admin/academic_years.php'); ?>" class="btn btn-outline-secondary w-100 py-2 text-start d-flex align-items-center">
                    <i class="bi bi-calendar3-range fs-5 me-2"></i>
                    <span>Terms</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Two-Column Information Grid -->
<div class="row g-4">
    <!-- Left Column: Recent Students -->
    <div class="col-lg-7">
        <div class="card-modern h-100 mb-0">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-people text-primary"></i> Registered University Students
                </h2>
                <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-sm btn-light border">
                    All Students <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Student Details</th>
                            <th>Student ID</th>
                            <th>Program</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_students)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                    No students registered yet. Click <strong>Register Student</strong> to add one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_students as $st): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-mini me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
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
                                    <td>
                                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($st['program_name'] ?? 'General Undergraduate'); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo base_url('admin/view_student.php?id=' . $st['student_id']); ?>" class="btn btn-sm btn-outline-primary" title="View Transcript & Profile">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo base_url('admin/edit_student.php?id=' . $st['student_id']); ?>" class="btn btn-sm btn-outline-secondary" title="Edit Student">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Examination Results -->
    <div class="col-lg-5">
        <div class="card-modern h-100 mb-0">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-award text-warning"></i> Recent Examination Results
                </h2>
                <a href="<?php echo base_url('admin/grades.php'); ?>" class="btn btn-sm btn-light border">
                    All Results <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Score</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_grades)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-award fs-1 text-muted d-block mb-2"></i>
                                    No examination results recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_grades as $g): 
                                $badge_cls = 'badge-grade-b';
                                $l = substr($g['letter_grade'], 0, 1);
                                if ($l === 'A') $badge_cls = 'badge-grade-a';
                                elseif ($l === 'C') $badge_cls = 'badge-grade-c';
                                elseif ($l === 'D') $badge_cls = 'badge-grade-d';
                                elseif ($l === 'F') $badge_cls = 'badge-grade-f';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($g['student_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($g['student_number']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($g['course_code']); ?></span>
                                    </td>
                                    <td><strong><?php echo $g['score']; ?>%</strong></td>
                                    <td>
                                        <span class="badge-grade <?php echo $badge_cls; ?>">
                                            <?php echo htmlspecialchars($g['letter_grade']); ?>
                                        </span>
                                    </td>
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
