<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - Lecturer Grade Management
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pdo = get_db_connection();
$page_title = 'Enter & Manage Student Grades';
$user = current_user();

// Fetch teacher_id
$tStmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :uid LIMIT 1");
$tStmt->execute(['uid' => $user['user_id']]);
$teacher_id = (int)$tStmt->fetchColumn();

// Fetch teacher's assigned courses only
$courses_stmt = $pdo->prepare("SELECT course_id, course_name, course_code FROM courses WHERE teacher_id = :tid ORDER BY course_code ASC");
$courses_stmt->execute(['tid' => $teacher_id]);
$courses = $courses_stmt->fetchAll();

$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['course_id'] ?? 0);

// Handle Grade Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('teacher/grades.php?course_id=' . $selected_course_id));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Save/Update Batch Grades
    if ($action === 'save_grades') {
        $course_id = (int)($_POST['course_id'] ?? 0);
        $scores    = $_POST['scores'] ?? []; // student_id => score
        $letters   = $_POST['letters'] ?? []; // student_id => letter_grade
        $remarks   = $_POST['remarks'] ?? []; // student_id => remarks

        // Verify course belongs to this teacher!
        $chk = $pdo->prepare("SELECT course_id, credit_hours, academic_year_id, semester_id FROM courses WHERE course_id = :cid AND teacher_id = :tid");
        $chk->execute(['cid' => $course_id, 'tid' => $teacher_id]);
        $course_info = $chk->fetch();
        if (!$course_info) {
            set_flash('danger', 'Unauthorized: You are not assigned to this course.');
            header('Location: ' . base_url('teacher/grades.php'));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $upsert = $pdo->prepare("
                INSERT INTO grades (student_id, course_id, score, letter_grade, grade_point, credit_hours, academic_year_id, semester_id, remarks) 
                VALUES (:sid, :cid, :sc, :lg, :gp, :ch, :ay, :sem, :rem)
                ON DUPLICATE KEY UPDATE 
                    score = VALUES(score),
                    letter_grade = VALUES(letter_grade),
                    grade_point = VALUES(grade_point),
                    credit_hours = VALUES(credit_hours),
                    academic_year_id = VALUES(academic_year_id),
                    semester_id = VALUES(semester_id),
                    remarks = VALUES(remarks)
            ");

            $upCgpa = $pdo->prepare("UPDATE students SET cgpa = :cg WHERE student_id = :sid");

            $updated_count = 0;
            foreach ($scores as $sid => $score_val) {
                $sid = (int)$sid;
                if ($sid > 0 && $score_val !== '' && $score_val !== null) {
                    $score_num = (float)$score_val;
                    if ($score_num >= 0 && $score_num <= 100) {
                        $let = trim($letters[$sid] ?? '');
                        if (empty($let)) {
                            $let = calculate_letter_grade($score_num);
                        }
                        $gp = calculate_grade_point($score_num);
                        $ch = (int)($course_info['credit_hours'] ?? 3);
                        $rem = trim($remarks[$sid] ?? '');

                        $upsert->execute([
                            'sid' => $sid,
                            'cid' => $course_id,
                            'sc'  => $score_num,
                            'lg'  => $let,
                            'gp'  => $gp,
                            'ch'  => $ch,
                            'ay'  => $course_info['academic_year_id'] ?? null,
                            'sem' => $course_info['semester_id'] ?? null,
                            'rem' => $rem
                        ]);

                        // Recalculate CGPA for this student
                        $gpaData = calculate_student_gpa($pdo, $sid);
                        $upCgpa->execute(['cg' => $gpaData['gpa'], 'sid' => $sid]);

                        $updated_count++;
                    }
                }
            }

            $pdo->commit();
            set_flash('success', 'Grades updated successfully for ' . $updated_count . ' students.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Error updating grades: ' . $e->getMessage());
        }

        header('Location: ' . base_url('teacher/grades.php?course_id=' . $course_id));
        exit;
    }
}

// Fetch enrolled students and their current grades for the selected course
$students = [];
$active_course = null;

if ($selected_course_id > 0) {
    // Verify course belongs to this teacher
    $chkStmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :cid AND teacher_id = :tid LIMIT 1");
    $chkStmt->execute(['cid' => $selected_course_id, 'tid' => $teacher_id]);
    $active_course = $chkStmt->fetch();

    if ($active_course) {
        $sStmt = $pdo->prepare("
            SELECT s.student_id, s.student_number, u.full_name, u.email,
                   g.grade_id, g.score, g.letter_grade, g.remarks
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            LEFT JOIN grades g ON (g.student_id = s.student_id AND g.course_id = :cid)
            WHERE e.course_id = :cid
            ORDER BY u.full_name ASC
        ");
        $sStmt->execute(['cid' => $selected_course_id]);
        $students = $sStmt->fetchAll();
    }
}

// Calculate Course Metrics
$total_students = count($students);
$graded_count = 0;
$sum_scores = 0;
foreach ($students as $st) {
    if ($st['score'] !== null) {
        $graded_count++;
        $sum_scores += (float)$st['score'];
    }
}
$avg_class_score = $graded_count > 0 ? round($sum_scores / $graded_count, 1) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Enter & Update Course Grades</h4>
        <p class="text-muted small mb-0">Record exam marks, assignments, and semester evaluations for your assigned subjects.</p>
    </div>
</div>

<?php if (empty($courses)): ?>
    <div class="card-modern text-center py-5">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
        <h5>No Assigned Courses</h5>
        <p class="text-muted">You do not have any courses assigned to manage grades.</p>
    </div>
<?php else: ?>
    <!-- Course Selector -->
    <div class="card-modern mb-4">
        <div class="card-modern-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Select Course to Grade:</label>
                    <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['course_id']; ?>" <?php echo ($selected_course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 text-md-end mt-md-4">
                    <span class="badge bg-light text-dark border px-3 py-2 me-2">
                        Class Avg: <strong class="text-primary"><?php echo $avg_class_score; ?>%</strong>
                    </span>
                    <span class="badge bg-light text-dark border px-3 py-2">
                        Graded: <strong><?php echo $graded_count; ?>/<?php echo $total_students; ?></strong>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Grade Sheet Table Form -->
    <div class="card-modern">
        <div class="card-modern-header flex-column flex-md-row gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary text-white fs-6 px-3 py-2">
                    <?php echo htmlspecialchars($active_course['course_code'] ?? ''); ?>
                </span>
                <span class="fw-bold text-dark">
                    <?php echo htmlspecialchars($active_course['course_name'] ?? ''); ?>
                </span>
            </div>
            <div class="small text-muted">
                <i class="bi bi-info-circle text-primary me-1"></i> Letter grade updates automatically as you type numeric score.
            </div>
        </div>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_grades">
            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">

            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Student Details</th>
                            <th>Student #</th>
                            <th style="width: 140px;">Score (0-100)</th>
                            <th style="width: 120px;">Letter Grade</th>
                            <th>Instructor Feedback / Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                    No students are enrolled in this course yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($students as $st): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $i++; ?></td>
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
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" max="100" 
                                                   name="scores[<?php echo $st['student_id']; ?>]" 
                                                   class="form-control score-calc" 
                                                   data-target="letter_<?php echo $st['student_id']; ?>"
                                                   placeholder="0 - 100"
                                                   value="<?php echo ($st['score'] !== null) ? htmlspecialchars($st['score']) : ''; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="letters[<?php echo $st['student_id']; ?>]" 
                                               id="letter_<?php echo $st['student_id']; ?>"
                                               class="form-control form-control-sm fw-bold text-center" 
                                               style="max-width: 80px;"
                                               placeholder="e.g. A"
                                               value="<?php echo htmlspecialchars($st['letter_grade'] ?? ''); ?>">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="remarks[<?php echo $st['student_id']; ?>]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Remarks, exam feedback..."
                                               value="<?php echo htmlspecialchars($st['remarks'] ?? ''); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card-footer bg-light p-3 text-end border-top">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Save All Grades
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic score to letter grade calculation per row
    document.querySelectorAll('.score-calc').forEach(function(input) {
        input.addEventListener('input', function() {
            const targetId = this.getAttribute('data-target');
            const targetLetter = document.getElementById(targetId);
            if (!targetLetter) return;

            const val = parseFloat(this.value);
            if (isNaN(val)) {
                targetLetter.value = '';
                return;
            }
            let grade = 'F';
            if (val >= 93) grade = 'A';
            else if (val >= 90) grade = 'A-';
            else if (val >= 87) grade = 'B+';
            else if (val >= 83) grade = 'B';
            else if (val >= 80) grade = 'B-';
            else if (val >= 77) grade = 'C+';
            else if (val >= 73) grade = 'C';
            else if (val >= 70) grade = 'C-';
            else if (val >= 65) grade = 'D+';
            else if (val >= 60) grade = 'D';
            else grade = 'F';

            targetLetter.value = grade;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
