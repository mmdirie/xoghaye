<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Course & Unit Management (CRUD)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Course & Curriculum Units';

$selected_dept = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$selected_sem  = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

// Handle Course Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/courses.php'));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Create Course
    if ($action === 'create') {
        $course_name      = trim($_POST['course_name'] ?? '');
        $course_code      = strtoupper(trim($_POST['course_code'] ?? ''));
        $credit_hours     = max(1, (int)($_POST['credit_hours'] ?? 3));
        $faculty_id       = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $department_id    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $semester_id      = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
        $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
        $teacher_id       = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $description      = trim($_POST['description'] ?? '');

        $errors = [];
        if (empty($course_name)) $errors[] = 'Course title is required.';
        if (empty($course_code)) $errors[] = 'Course code is required (e.g. CS101).';

        // Check course_code uniqueness
        $chk = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = :cc LIMIT 1");
        $chk->execute(['cc' => $course_code]);
        if ($chk->fetch()) {
            $errors[] = 'Course code "' . htmlspecialchars($course_code) . '" already exists.';
        }

        if (empty($errors)) {
            $ins = $pdo->prepare("
                INSERT INTO courses 
                    (course_name, course_code, credit_hours, faculty_id, department_id, semester_id, academic_year_id, teacher_id, description) 
                VALUES 
                    (:cn, :cc, :cr, :fid, :did, :semid, :yid, :tid, :descr)
            ");
            $ins->execute([
                'cn'    => $course_name,
                'cc'    => $course_code,
                'cr'    => $credit_hours,
                'fid'   => $faculty_id,
                'did'   => $department_id,
                'semid' => $semester_id,
                'yid'   => $academic_year_id,
                'tid'   => $teacher_id,
                'descr' => $description
            ]);
            set_flash('success', 'Course "' . htmlspecialchars($course_name) . ' (' . htmlspecialchars($course_code) . ')" created successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/courses.php'));
        exit;
    }

    // Action 2: Update Course
    if ($action === 'update') {
        $course_id        = (int)($_POST['course_id'] ?? 0);
        $course_name      = trim($_POST['course_name'] ?? '');
        $course_code      = strtoupper(trim($_POST['course_code'] ?? ''));
        $credit_hours     = max(1, (int)($_POST['credit_hours'] ?? 3));
        $faculty_id       = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
        $department_id    = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $semester_id      = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
        $academic_year_id = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
        $teacher_id       = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $description      = trim($_POST['description'] ?? '');

        $errors = [];
        if ($course_id <= 0) $errors[] = 'Invalid course ID.';
        if (empty($course_name)) $errors[] = 'Course title is required.';
        if (empty($course_code)) $errors[] = 'Course code is required.';

        $chk = $pdo->prepare("SELECT course_id FROM courses WHERE course_code = :cc AND course_id != :cid LIMIT 1");
        $chk->execute(['cc' => $course_code, 'cid' => $course_id]);
        if ($chk->fetch()) {
            $errors[] = 'Course code "' . htmlspecialchars($course_code) . '" is already in use.';
        }

        if (empty($errors)) {
            $up = $pdo->prepare("
                UPDATE courses 
                SET course_name = :cn, course_code = :cc, credit_hours = :cr, 
                    faculty_id = :fid, department_id = :did, semester_id = :semid, academic_year_id = :yid,
                    teacher_id = :tid, description = :descr 
                WHERE course_id = :cid
            ");
            $up->execute([
                'cn'    => $course_name,
                'cc'    => $course_code,
                'cr'    => $credit_hours,
                'fid'   => $faculty_id,
                'did'   => $department_id,
                'semid' => $semester_id,
                'yid'   => $academic_year_id,
                'tid'   => $teacher_id,
                'descr' => $description,
                'cid'   => $course_id
            ]);
            set_flash('success', 'Course updated successfully.');
        } else {
            set_flash('danger', implode('<br>', $errors));
        }
        header('Location: ' . base_url('admin/courses.php'));
        exit;
    }

    // Action 3: Delete Course
    if ($action === 'delete') {
        $course_id = (int)($_POST['course_id'] ?? 0);
        if ($course_id <= 0) {
            set_flash('danger', 'Invalid course ID.');
        } else {
            try {
                $pdo->beginTransaction();
                $courseCheck = $pdo->prepare("SELECT course_id FROM courses WHERE course_id = :cid FOR UPDATE");
                $courseCheck->execute(['cid' => $course_id]);
                if (!$courseCheck->fetchColumn()) {
                    $pdo->rollBack();
                    set_flash('danger', 'Course not found.');
                } else {
                    $relatedCheck = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM enrollments WHERE course_id = :cid1) +
                            (SELECT COUNT(*) FROM attendance WHERE course_id = :cid2) +
                            (SELECT COUNT(*) FROM grades WHERE course_id = :cid3)
                    ");
                    $relatedCheck->execute(['cid1' => $course_id, 'cid2' => $course_id, 'cid3' => $course_id]);
                    if ((int)$relatedCheck->fetchColumn() > 0) {
                        $pdo->rollBack();
                        set_flash('danger', 'Cannot delete this course: it has enrollment, attendance, or examination records. Preserve the academic history and remove the course only when those records are no longer required.');
                    } else {
                        $del = $pdo->prepare("DELETE FROM courses WHERE course_id = :cid");
                        $del->execute(['cid' => $course_id]);
                        if ($del->rowCount() === 1) {
                            $pdo->commit();
                            set_flash('success', 'Course deleted successfully.');
                        } else {
                            $pdo->rollBack();
                            set_flash('danger', 'Course not found; no record was deleted.');
                        }
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getCode() === '23000'
                    ? 'Cannot delete this course: related academic records prevent deletion.'
                    : 'Course deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                set_flash('danger', $message);
            }
        }
        header('Location: ' . base_url('admin/courses.php'));
        exit;
    }
}

// Fetch Dropdown options
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();
$departments = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name ASC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name FROM semesters ORDER BY semester_name ASC")->fetchAll();
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();
$teachers = $pdo->query("
    SELECT t.teacher_id, t.title, u.full_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    ORDER BY u.full_name ASC
")->fetchAll();

// Build query
$where = [];
$params = [];
if ($selected_dept > 0) {
    $where[] = "c.department_id = :did";
    $params['did'] = $selected_dept;
}
if ($selected_sem > 0) {
    $where[] = "c.semester_id = :semid";
    $params['semid'] = $selected_sem;
}
$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$courses_stmt = $pdo->prepare("
    SELECT c.*, 
           f.faculty_name, f.faculty_code,
           d.department_name, d.department_code,
           sem.semester_name,
           t.teacher_id, t.title AS teacher_title,
           u.full_name AS teacher_name, u.email AS teacher_email,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrolled_count
    FROM courses c
    LEFT JOIN faculties f ON c.faculty_id = f.faculty_id
    LEFT JOIN departments d ON c.department_id = d.department_id
    LEFT JOIN semesters sem ON c.semester_id = sem.semester_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    $where_sql
    ORDER BY c.course_code ASC
");
$courses_stmt->execute($params);
$courses = $courses_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">University Courses & Units</h4>
        <p class="text-muted small mb-0">Create degree syllabus modules, assign credit units, and designate course lecturers.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo base_url('admin/enrollments.php'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-ui-checks me-1"></i> Enrollments
        </a>
        <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            <i class="bi bi-journal-plus me-1"></i> Create New Course
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Filter by Department:</label>
                <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Departments (<?php echo count($departments); ?>) --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['department_id']; ?>" <?php echo ($selected_dept === (int)$d['department_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['department_name'] . ' (' . $d['department_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Filter by Semester:</label>
                <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Semesters --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo $s['semester_id']; ?>" <?php echo ($selected_sem === (int)$s['semester_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['semester_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 text-md-end mt-md-4">
                <?php if ($selected_dept > 0 || $selected_sem > 0): ?>
                    <a href="<?php echo base_url('admin/courses.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Courses Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total: <?php echo count($courses); ?> Courses
            </span>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search courses...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th style="width: 100px;">Code</th>
                    <th>Course Title</th>
                    <th>Credits</th>
                    <th>Department & Faculty</th>
                    <th>Lecturer Assigned</th>
                    <th>Enrolled</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
                            No courses found. Click <strong>Create New Course</strong> to add one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary fw-bold fs-6"><?php echo htmlspecialchars($c['course_code']); ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['course_name']); ?></div>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?php echo htmlspecialchars($c['description'] ?? ''); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-bold"><?php echo (int)$c['credit_hours']; ?> Units</span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?php echo htmlspecialchars($c['department_name'] ?? 'Department'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($c['faculty_name'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($c['teacher_name'])): ?>
                                    <div class="small fw-semibold text-dark">
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($c['teacher_title'] ?? 'Lecturer'); ?></span>
                                        <?php echo htmlspecialchars($c['teacher_name']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo base_url('admin/enrollments.php?course_id=' . $c['course_id']); ?>" class="badge bg-secondary-subtle text-secondary border text-decoration-none">
                                    <i class="bi bi-people-fill me-1"></i><?php echo (int)$c['enrolled_count']; ?> Students
                                </a>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <button type="button" class="btn btn-outline-secondary btn-edit-course"
                                            data-bs-toggle="modal" data-bs-target="#editCourseModal"
                                            data-id="<?php echo $c['course_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($c['course_name']); ?>"
                                            data-code="<?php echo htmlspecialchars($c['course_code']); ?>"
                                            data-credits="<?php echo (int)$c['credit_hours']; ?>"
                                            data-faculty="<?php echo $c['faculty_id'] ?? ''; ?>"
                                            data-dept="<?php echo $c['department_id'] ?? ''; ?>"
                                            data-sem="<?php echo $c['semester_id'] ?? ''; ?>"
                                            data-year="<?php echo $c['academic_year_id'] ?? ''; ?>"
                                            data-teacher="<?php echo $c['teacher_id'] ?? ''; ?>"
                                            data-desc="<?php echo htmlspecialchars($c['description'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/courses.php'); ?>"
                                            data-id-field="course_id"
                                            data-record-id="<?php echo $c['course_id']; ?>"
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>"
                                            data-item-name="Course: <?php echo htmlspecialchars($c['course_name'] . ' (' . $c['course_code'] . ')'); ?>"
                                            data-message="Are you sure you want to delete this course? All associated enrollments, attendance, and exam marks will be permanently removed.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form method="POST" action="" class="d-none">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="course_id" value="<?php echo $c['course_id']; ?>">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Course -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i> Create University Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Course Code <span class="text-danger">*</span></label>
                            <input type="text" name="course_code" class="form-control text-uppercase" placeholder="e.g. CS101" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Course Title <span class="text-danger">*</span></label>
                            <input type="text" name="course_name" class="form-control" placeholder="e.g. Data Structures & Algorithms" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Credit Hours <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="10" name="credit_hours" class="form-control" value="3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>"><?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>"><?php echo htmlspecialchars($s['semester_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Assigned Lecturer</label>
                            <select name="teacher_id" class="form-select">
                                <option value="">-- Select Lecturer (Optional) --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['teacher_id']; ?>"><?php echo htmlspecialchars($t['title'] . ' ' . $t['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Syllabus / Course Description</label>
                            <textarea name="description" rows="2" class="form-control" placeholder="Course outline, prerequisites..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Course -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="course_id" id="edit_course_id">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Course Code <span class="text-danger">*</span></label>
                            <input type="text" name="course_code" id="edit_course_code" class="form-control text-uppercase" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Course Title <span class="text-danger">*</span></label>
                            <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Credit Hours <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="10" name="credit_hours" id="edit_credit_hours" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" id="edit_faculty_id" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>"><?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Department</label>
                            <select name="department_id" id="edit_department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" id="edit_semester_id" class="form-select">
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>"><?php echo htmlspecialchars($s['semester_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Assigned Lecturer</label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-select">
                                <option value="">-- Select Lecturer (Optional) --</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['teacher_id']; ?>"><?php echo htmlspecialchars($t['title'] . ' ' . $t['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Syllabus / Course Description</label>
                            <textarea name="description" id="edit_description" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-course').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_course_id').value = this.getAttribute('data-id');
            document.getElementById('edit_course_name').value = this.getAttribute('data-name');
            document.getElementById('edit_course_code').value = this.getAttribute('data-code');
            document.getElementById('edit_credit_hours').value = this.getAttribute('data-credits');
            document.getElementById('edit_faculty_id').value = this.getAttribute('data-faculty');
            document.getElementById('edit_department_id').value = this.getAttribute('data-dept');
            document.getElementById('edit_semester_id').value = this.getAttribute('data-sem');
            document.getElementById('edit_teacher_id').value = this.getAttribute('data-teacher');
            document.getElementById('edit_description').value = this.getAttribute('data-desc');
        });
    });

    // Delete modal handled globally by main.js
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
