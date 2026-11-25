<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Examination Results & Gradebook Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Examination Results & Grades';

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/grades.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create New Grade Record
    if ($action === 'create_grade') {
        $student_id       = (int)($_POST['student_id'] ?? 0);
        $course_id        = (int)($_POST['course_id'] ?? 0);
        $score            = (float)($_POST['score'] ?? 0);
        $letter_grade     = trim($_POST['letter_grade'] ?? '');
        $credit_hours     = (int)($_POST['credit_hours'] ?? 3);
        $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
        $semester_id      = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
        $remarks          = trim($_POST['remarks'] ?? '');

        if ($student_id <= 0 || $course_id <= 0) {
            set_flash('danger', 'Please select both a student and a course.');
        } else {
            if (empty($letter_grade)) {
                $letter_grade = calculate_letter_grade($score);
            }
            $gp = grade_point($letter_grade);

            $upsert = $pdo->prepare("
                INSERT INTO grades 
                    (student_id, course_id, academic_year_id, semester_id, score, letter_grade, credit_hours, grade_point, remarks) 
                VALUES 
                    (:sid, :cid, :yid, :semid, :sc, :lg, :cr, :gp, :rem)
                ON DUPLICATE KEY UPDATE 
                    score = VALUES(score),
                    letter_grade = VALUES(letter_grade),
                    credit_hours = VALUES(credit_hours),
                    grade_point = VALUES(grade_point),
                    remarks = VALUES(remarks)
            ");
            $upsert->execute([
                'sid'   => $student_id,
                'cid'   => $course_id,
                'yid'   => $academic_year_id,
                'semid' => $semester_id,
                'sc'    => $score,
                'lg'    => $letter_grade,
                'cr'    => $credit_hours,
                'gp'    => $gp,
                'rem'   => $remarks
            ]);
            set_flash('success', 'Examination result recorded successfully.');
        }
        header('Location: ' . base_url('admin/grades.php' . ($course_id > 0 ? '?course_id=' . $course_id : '')));
        exit;
    }

    // Action 2: Update Grade
    if ($action === 'update_grade') {
        $grade_id     = (int)($_POST['grade_id'] ?? 0);
        $score        = (float)($_POST['score'] ?? 0);
        $letter_grade = trim($_POST['letter_grade'] ?? '');
        $credit_hours = (int)($_POST['credit_hours'] ?? 3);
        $remarks      = trim($_POST['remarks'] ?? '');

        if ($grade_id > 0) {
            if (empty($letter_grade)) {
                $letter_grade = calculate_letter_grade($score);
            }
            $gp = grade_point($letter_grade);

            $up = $pdo->prepare("
                UPDATE grades 
                SET score = :sc, letter_grade = :lg, credit_hours = :cr, grade_point = :gp, remarks = :rem 
                WHERE grade_id = :gid
            ");
            $up->execute([
                'sc'  => $score,
                'lg'  => $letter_grade,
                'cr'  => $credit_hours,
                'gp'  => $gp,
                'rem' => $remarks,
                'gid' => $grade_id
            ]);
            set_flash('success', 'Grade record updated successfully.');
        }
        header('Location: ' . base_url('admin/grades.php'));
        exit;
    }

    // Action 3: Delete Grade
    if ($action === 'delete') {
        $grade_id = (int)($_POST['grade_id'] ?? 0);
        if ($grade_id <= 0) {
            set_flash('danger', 'Invalid examination result ID.');
        } else {
            try {
                $del = $pdo->prepare("DELETE FROM grades WHERE grade_id = :gid");
                $del->execute(['gid' => $grade_id]);
                if ($del->rowCount() === 1) {
                    set_flash('success', 'Examination result deleted successfully.');
                } else {
                    set_flash('danger', 'Examination result not found; no record was deleted.');
                }
            } catch (PDOException $e) {
                set_flash('danger', 'Examination result deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }
        }
        header('Location: ' . base_url('admin/grades.php'));
        exit;
    }
}

// Filters
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$sem_id    = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

$where = [];
$params = [];
if ($course_id > 0) {
    $where[] = "g.course_id = :cid";
    $params['cid'] = $course_id;
}
if ($sem_id > 0) {
    $where[] = "g.semester_id = :semid";
    $params['semid'] = $sem_id;
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT g.*, 
           s.student_number, s.registration_number, u.full_name AS student_name, u.email AS student_email,
           c.course_name, c.course_code, c.credit_hours AS default_credits,
           t.title AS teacher_title, u_t.full_name AS teacher_name,
           y.year_name, sem.semester_name
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN courses c ON g.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    LEFT JOIN academic_years y ON g.academic_year_id = y.year_id
    LEFT JOIN semesters sem ON g.semester_id = sem.semester_id
    $where_sql
    ORDER BY g.grade_id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$grades = $stmt->fetchAll();

// Distribution & CGPA Stats
$total_grades = count($grades);
$grade_dist = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
$sum_score = 0;
$total_credits = 0;
$total_quality_points = 0;

foreach ($grades as $g) {
    $sum_score += (float)$g['score'];
    $first_char = substr($g['letter_grade'], 0, 1);
    if (isset($grade_dist[$first_char])) {
        $grade_dist[$first_char]++;
    }
    $cr = (int)($g['credit_hours'] ?: 3);
    $gp = (float)($g['grade_point'] ?: grade_point($g['letter_grade']));
    $total_credits += $cr;
    $total_quality_points += ($cr * $gp);
}

$avg_score = $total_grades > 0 ? round($sum_score / $total_grades, 1) : 0;
$overall_gpa = $total_credits > 0 ? round($total_quality_points / $total_credits, 2) : 0.00;

// Dropdown data
$courses = $pdo->query("SELECT course_id, course_name, course_code, credit_hours FROM courses ORDER BY course_code ASC")->fetchAll();
$students = $pdo->query("SELECT s.student_id, s.student_number, u.full_name FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.full_name ASC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name FROM semesters ORDER BY semester_name ASC")->fetchAll();
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">University Examination Results & Gradebook</h4>
        <p class="text-muted small mb-0">Record exam marks, manage subject evaluations, and compute semester GPAs and CGPAs.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
        <i class="bi bi-award-fill me-1"></i> Enter Exam Result
    </button>
</div>

<!-- Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Mean Exam Score</div>
                <div class="card-stat-value text-primary fs-3"><?php echo $avg_score; ?>%</div>
                <small class="text-muted"><?php echo $total_grades; ?> Results Graded</small>
            </div>
            <div class="card-stat-icon icon-blue"><i class="bi bi-graph-up-arrow"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Overall GPA</div>
                <div class="card-stat-value text-success fs-3"><?php echo number_format($overall_gpa, 2); ?></div>
                <small class="text-muted">4.00 Scale Standard</small>
            </div>
            <div class="card-stat-icon icon-emerald"><i class="bi bi-mortarboard-fill"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Honor Roll (A & B)</div>
                <div class="card-stat-value text-info fs-3"><?php echo $grade_dist['A'] + $grade_dist['B']; ?></div>
                <small class="text-muted">Pass with Distinction</small>
            </div>
            <div class="card-stat-icon icon-amber"><i class="bi bi-award-fill"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Total Credit Units</div>
                <div class="card-stat-value text-dark fs-3"><?php echo $total_credits; ?></div>
                <small class="text-muted">Assessed Units</small>
            </div>
            <div class="card-stat-icon icon-purple"><i class="bi bi-journal-check"></i></div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Filter by Course:</label>
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Courses (<?php echo count($courses); ?>) --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['course_id']; ?>" <?php echo ($course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Filter by Semester:</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Semesters --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s['semester_id']; ?>" <?php echo ($sem_id === (int)$s['semester_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-md-end mt-md-4">
                <?php if ($course_id > 0 || $sem_id > 0): ?>
                    <a href="<?php echo base_url('admin/grades.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Grades Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Recorded Results: <?php echo $total_grades; ?>
            </span>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search results...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Student Details</th>
                    <th>Student ID</th>
                    <th>Course</th>
                    <th>Credits</th>
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Grade Point</th>
                    <th>Quality Pts</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-award fs-1 text-muted d-block mb-2"></i>
                            No examination results found. Click <strong>Enter Exam Result</strong> to record one.
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
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($g['student_name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($g['student_email']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($g['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($g['course_code']); ?></span>
                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($g['course_name']); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo $cr; ?> Units</span>
                            </td>
                            <td>
                                <strong class="fs-6"><?php echo $g['score']; ?>%</strong>
                            </td>
                            <td>
                                <span class="badge-grade <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($g['letter_grade']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($gp, 1); ?></td>
                            <td><strong><?php echo number_format($qp, 1); ?></strong></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-grade"
                                            data-bs-toggle="modal" data-bs-target="#editGradeModal"
                                            data-id="<?php echo $g['grade_id']; ?>"
                                            data-student="<?php echo htmlspecialchars($g['student_name']); ?>"
                                            data-course="<?php echo htmlspecialchars($g['course_code'] . ' - ' . $g['course_name']); ?>"
                                            data-credits="<?php echo $cr; ?>"
                                            data-score="<?php echo $g['score']; ?>"
                                            data-letter="<?php echo htmlspecialchars($g['letter_grade']); ?>"
                                            data-remarks="<?php echo htmlspecialchars($g['remarks'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/grades.php'); ?>"
                                            data-id-field="grade_id"
                                            data-record-id="<?php echo $g['grade_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Result for <?php echo htmlspecialchars($g['student_name'] . ' in ' . $g['course_code']); ?>"
                                            data-message="Are you sure you want to delete this examination result?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="grade_id" value="<?php echo $g['grade_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Result -->
<div class="modal fade" id="addGradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-award-fill me-2"></i> Record Examination Result</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_grade">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Student <span class="text-danger">*</span></label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $st): ?>
                                <option value="<?php echo $st['student_id']; ?>">
                                    <?php echo htmlspecialchars($st['full_name'] . ' (' . $st['student_number'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Course Unit <span class="text-danger">*</span></label>
                        <select name="course_id" id="addGradeCourse" class="form-select" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['course_id']; ?>" data-credits="<?php echo $c['credit_hours']; ?>">
                                    <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Numeric Score (0-100) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" max="100" name="score" id="addScoreInput" class="form-control" placeholder="85.5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Letter Grade</label>
                            <input type="text" name="letter_grade" id="addLetterInput" class="form-control fw-bold" placeholder="Auto">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Credit Hours</label>
                            <input type="number" min="1" max="10" name="credit_hours" id="addCreditInput" class="form-control" value="3">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>"><?php echo htmlspecialchars($s['semester_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Instructor Remarks / Feedback</label>
                        <textarea name="remarks" rows="2" class="form-control" placeholder="Optional comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Result -->
<div class="modal fade" id="editGradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Update Examination Result</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_grade">
                <input type="hidden" name="grade_id" id="edit_grade_id">

                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <div class="small text-muted">Student:</div>
                        <strong class="d-block text-dark" id="edit_grade_student">Student Name</strong>
                        <div class="small text-muted mt-1">Course:</div>
                        <strong class="d-block text-primary" id="edit_grade_course">Course Code</strong>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Score (0 - 100) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" max="100" name="score" id="scoreInput" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Letter Grade</label>
                            <input type="text" name="letter_grade" id="letterGradeInput" class="form-control fw-bold" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Credit Hours</label>
                        <input type="number" min="1" max="10" name="credit_hours" id="edit_grade_credits" class="form-control" value="3">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Instructor Remarks</label>
                        <textarea name="remarks" id="edit_grade_remarks" rows="2" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate letter grade for add modal
    const addScore = document.getElementById('addScoreInput');
    const addLetter = document.getElementById('addLetterInput');
    if (addScore && addLetter) {
        addScore.addEventListener('input', function() {
            const v = parseFloat(this.value);
            if (isNaN(v)) { addLetter.value = ''; return; }
            if (v >= 90) addLetter.value = 'A';
            else if (v >= 85) addLetter.value = 'A-';
            else if (v >= 80) addLetter.value = 'B+';
            else if (v >= 75) addLetter.value = 'B';
            else if (v >= 70) addLetter.value = 'B-';
            else if (v >= 65) addLetter.value = 'C+';
            else if (v >= 60) addLetter.value = 'C';
            else if (v >= 55) addLetter.value = 'C-';
            else if (v >= 50) addLetter.value = 'D';
            else addLetter.value = 'F';
        });
    }

    // Populate Edit Grade Modal
    document.querySelectorAll('.btn-edit-grade').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_grade_id').value = this.getAttribute('data-id');
            document.getElementById('edit_grade_student').textContent = this.getAttribute('data-student');
            document.getElementById('edit_grade_course').textContent = this.getAttribute('data-course');
            document.getElementById('scoreInput').value = this.getAttribute('data-score');
            document.getElementById('letterGradeInput').value = this.getAttribute('data-letter');
            document.getElementById('edit_grade_credits').value = this.getAttribute('data-credits') || 3;
            document.getElementById('edit_grade_remarks').value = this.getAttribute('data-remarks');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
