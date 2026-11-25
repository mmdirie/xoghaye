<?php
// ==========================================================
// SCHOOL MANAGER - Student Grades & Academic Report Card
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = get_db_connection();
$page_title = 'My Grades & Academic Report';
$user = current_user();

// Fetch student profile record
$sStmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = :uid
    LIMIT 1
");
$sStmt->execute(['uid' => $user['user_id']]);
$student = $sStmt->fetch();

$student_id = (int)$student['student_id'];

// Fetch official grades for this student only
$gStmt = $pdo->prepare("
    SELECT g.*, c.course_code, c.course_name, c.description,
           u_t.full_name AS teacher_name, t.department
    FROM grades g
    JOIN courses c ON g.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    WHERE g.student_id = :sid
    ORDER BY c.course_code ASC
");
$gStmt->execute(['sid' => $student_id]);
$grades = $gStmt->fetchAll();

// GPA and Performance Stats
$total_points = 0;
$total_score_sum = 0;
$grade_count = count($grades);

foreach ($grades as $g) {
    $total_points += grade_point($g['letter_grade']);
    $total_score_sum += (float)$g['score'];
}

$gpa = $grade_count > 0 ? round($total_points / $grade_count, 2) : 0.00;
$mean_score = $grade_count > 0 ? round($total_score_sum / $grade_count, 1) : 0.0;

// Academic Standing determination
$standing = 'Good Academic Standing';
$standing_class = 'text-success';
if ($gpa >= 3.7) {
    $standing = "Dean's Honor List";
    $standing_class = 'text-primary';
} elseif ($gpa < 2.0 && $grade_count > 0) {
    $standing = 'Academic Probation';
    $standing_class = 'text-danger';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Official Academic Report Card</h4>
        <p class="text-muted small mb-0">Consolidated grade sheet, subject marks, semester evaluations, and GPA calculations.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Print Report Card
    </button>
</div>

<!-- Academic Summary Card -->
<div class="card-modern p-4 mb-4 bg-light border">
    <div class="row align-items-center">
        <div class="col-md-7">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-primary fs-6 me-2"><?php echo htmlspecialchars($student['student_number']); ?></span>
                <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h4>
            </div>
            <div class="text-muted small mb-2">
                <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($student['email']); ?> &bull; 
                <i class="bi bi-calendar me-1"></i> Term: <?php echo date('Y'); ?> Academic Year
            </div>
            <div class="fw-semibold small <?php echo $standing_class; ?>">
                <i class="bi bi-shield-check me-1"></i> Standing: <strong><?php echo $standing; ?></strong>
            </div>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <div class="d-inline-flex gap-3 text-start">
                <div class="bg-white p-3 rounded-3 border shadow-sm text-center">
                    <small class="text-muted d-block">Cumulative GPA</small>
                    <span class="fw-bold fs-3 text-primary"><?php echo number_format($gpa, 2); ?></span>
                    <small class="text-muted d-block">/ 4.0 Scale</small>
                </div>
                <div class="bg-white p-3 rounded-3 border shadow-sm text-center">
                    <small class="text-muted d-block">Average Mark</small>
                    <span class="fw-bold fs-3 text-dark"><?php echo $mean_score; ?>%</span>
                    <small class="text-muted d-block"><?php echo $grade_count; ?> Courses</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grades Breakdown Table -->
<div class="card-modern">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-award-fill text-warning"></i> Course Grades & Evaluations
        </h2>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th>Instructor</th>
                    <th>Score</th>
                    <th>Letter Grade</th>
                    <th>Grade Points</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
                            No official grades have been published for your account yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($grades as $g): 
                        $badge_cls = 'badge-grade-b';
                        $l = substr($g['letter_grade'], 0, 1);
                        if ($l === 'A') $badge_cls = 'badge-grade-a';
                        elseif ($l === 'C') $badge_cls = 'badge-grade-c';
                        elseif ($l === 'D') $badge_cls = 'badge-grade-d';
                        elseif ($l === 'F') $badge_cls = 'badge-grade-f';

                        $is_pass = ($g['score'] >= 60 && $g['letter_grade'] !== 'F');
                        $pts = number_format(grade_point($g['letter_grade']), 1);
                    ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($g['course_code']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($g['course_name']); ?></div>
                            </td>
                            <td class="small">
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($g['teacher_name'] ?? 'Instructor'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($g['department'] ?? ''); ?></small>
                            </td>
                            <td>
                                <strong class="fs-6"><?php echo $g['score']; ?>%</strong>
                            </td>
                            <td>
                                <span class="badge-grade <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($g['letter_grade']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-muted"><?php echo $pts; ?></span>
                            </td>
                            <td>
                                <?php if ($is_pass): ?>
                                    <span class="badge bg-success-subtle text-success border">Passed</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted" style="max-width: 220px;">
                                <?php echo htmlspecialchars($g['remarks'] ?? 'Good participation and course completion.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Grading Standard Scale Reference Card -->
<div class="card-modern mt-4">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-info-circle text-primary"></i> Academic Grading Scale Reference
        </h2>
    </div>
    <div class="card-modern-body p-3">
        <div class="row g-2 text-center small">
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>93% - 100%</strong>: A (4.0)
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>90% - 92%</strong>: A- (3.7)
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>87% - 89%</strong>: B+ (3.3)
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>83% - 86%</strong>: B (3.0)
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>80% - 82%</strong>: B- (2.7)
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="p-2 border rounded bg-light">
                    <strong>&lt; 60%</strong>: F (0.0)
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
