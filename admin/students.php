<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Student Directory (CRUD, Search & Filtering)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'University Student Directory';

// Handle Student Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/students.php'));
        exit;
    }

    $student_id = (int)($_POST['student_id'] ?? 0);
    if ($student_id <= 0) {
        set_flash('danger', 'Invalid student ID.');
    } else {
        try {
            $pdo->beginTransaction();
            $findStmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = :sid FOR UPDATE");
            $findStmt->execute(['sid' => $student_id]);
            $user_id = $findStmt->fetchColumn();

            if (!$user_id) {
                $pdo->rollBack();
                set_flash('danger', 'Student not found in database.');
            } else {
                $relatedStmt = $pdo->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM enrollments WHERE student_id = :sid1) +
                        (SELECT COUNT(*) FROM attendance WHERE student_id = :sid2) +
                        (SELECT COUNT(*) FROM grades WHERE student_id = :sid3)
                ");
                $relatedStmt->execute(['sid1' => $student_id, 'sid2' => $student_id, 'sid3' => $student_id]);
                if ((int)$relatedStmt->fetchColumn() > 0) {
                    $pdo->rollBack();
                    set_flash('danger', 'Cannot delete this student: the profile has enrollments, attendance, or examination records. Preserve the academic history and remove those records only through their authorized management pages.');
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM users WHERE user_id = :uid");
                    $delStmt->execute(['uid' => $user_id]);
                    if ($delStmt->rowCount() === 1) {
                        $pdo->commit();
                        set_flash('success', 'Student profile and account deleted successfully.');
                    } else {
                        $pdo->rollBack();
                        set_flash('danger', 'Student account not found; no record was deleted.');
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = $e->getCode() === '23000'
                ? 'Cannot delete this student: related records prevent deletion.'
                : 'Student deletion failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            set_flash('danger', $message);
        }
    }
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// Filters & Pagination
$filter_faculty = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
$filter_dept    = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_prog    = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$search_query   = isset($_GET['q']) ? trim($_GET['q']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clauses
$where = [];
$params = [];

if ($filter_faculty > 0) {
    $where[] = "s.faculty_id = :fid";
    $params['fid'] = $filter_faculty;
}
if ($filter_dept > 0) {
    $where[] = "s.department_id = :did";
    $params['did'] = $filter_dept;
}
if ($filter_prog > 0) {
    $where[] = "s.program_id = :pid";
    $params['pid'] = $filter_prog;
}
if (!empty($search_query)) {
    $where[] = "(u.full_name LIKE :sq OR s.student_number LIKE :sq OR s.registration_number LIKE :sq OR u.email LIKE :sq)";
    $params['sq'] = '%' . $search_query . '%';
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total matching records for pagination
$count_query = "
    SELECT COUNT(*) 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    $where_sql
";
$cnt_stmt = $pdo->prepare($count_query);
$cnt_stmt->execute($params);
$total_students = (int)$cnt_stmt->fetchColumn();
$total_pages = ceil($total_students / $limit);

// Fetch page records with university relations
$query = "
    SELECT s.*, u.full_name, u.email,
           f.faculty_name, f.faculty_code,
           d.department_name, d.department_code,
           p.program_name, p.program_code,
           (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.student_id) as enrolled_count
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN faculties f ON s.faculty_id = f.faculty_id
    LEFT JOIN departments d ON s.department_id = d.department_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    $where_sql
    ORDER BY s.student_id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch filter dropdown data
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();
$departments = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name ASC")->fetchAll();
$programs = $pdo->query("SELECT program_id, program_name, program_code FROM programs ORDER BY program_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">University Student Directory</h4>
        <p class="text-muted small mb-0">View student academic profiles, program registrations, transcripts, and records.</p>
    </div>
    <a href="<?php echo base_url('admin/add_student.php'); ?>" class="btn btn-primary shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Register New Student
    </a>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Search Keywords:</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, Student ID, Email..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Faculty:</label>
                <select name="faculty_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Faculties --</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo $f['faculty_id']; ?>" <?php echo ($filter_faculty === (int)$f['faculty_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Department:</label>
                <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['department_id']; ?>" <?php echo ($filter_dept === (int)$d['department_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Degree Program:</label>
                <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Programs --</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?php echo $p['program_id']; ?>" <?php echo ($filter_prog === (int)$p['program_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['program_code'] . ' - ' . $p['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card-modern">
    <div class="card-modern-header flex-column flex-md-row gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white rounded-pill px-3 py-2">
                Total Found: <?php echo $total_students; ?> Students
            </span>
        </div>
        <div class="input-group" style="max-width: 280px;">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Filter this page...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Student Name & Email</th>
                    <th>Student ID</th>
                    <th>Reg. Number</th>
                    <th>Degree Program & Faculty</th>
                    <th>Enrolled Courses</th>
                    <th>CGPA</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-mortarboard fs-1 text-muted d-block mb-2"></i>
                            No students found matching this criteria. Click <strong>Register New Student</strong> to add one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $st): 
                        // Compute dynamic CGPA if grades exist
                        $gStmt = $pdo->prepare("SELECT score, letter_grade, credit_hours FROM grades WHERE student_id = :sid");
                        $gStmt->execute(['sid' => $st['student_id']]);
                        $st_grades = $gStmt->fetchAll();
                        $student_cgpa = calculate_gpa($st_grades);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar-mini me-2" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                        <?php echo strtoupper(substr($st['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <a href="<?php echo base_url('admin/view_student.php?id=' . $st['student_id']); ?>" class="fw-bold text-dark text-decoration-none">
                                            <?php echo htmlspecialchars($st['full_name']); ?>
                                        </a>
                                        <div class="text-muted small"><?php echo htmlspecialchars($st['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border fw-semibold"><?php echo htmlspecialchars($st['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="text-dark small fw-semibold"><?php echo htmlspecialchars($st['registration_number'] ?? $st['student_number']); ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?php echo htmlspecialchars($st['program_name'] ?? 'General Undergraduate'); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($st['faculty_name'] ?? ''); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?php echo (int)$st['enrolled_count']; ?> Courses
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($st_grades)): ?>
                                    <strong class="fs-6 <?php echo ($student_cgpa >= 3.0) ? 'text-success' : 'text-primary'; ?>">
                                        <?php echo number_format($student_cgpa, 2); ?>
                                    </strong>
                                    <small class="text-muted">/ 4.0</small>
                                <?php else: ?>
                                    <span class="text-muted small">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group">
                                    <a href="<?php echo base_url('admin/view_student.php?id=' . $st['student_id']); ?>" class="btn btn-outline-primary" title="View Academic Profile & Transcript">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo base_url('admin/edit_student.php?id=' . $st['student_id']); ?>" class="btn btn-outline-secondary" title="Edit Student">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-confirm-delete"
                                            data-action="<?php echo base_url('admin/students.php'); ?>"
                                            data-id-field="student_id"
                                            data-record-id="<?php echo $st['student_id']; ?>"
                                            data-item-name="<?php echo htmlspecialchars($st['full_name'] . ' (' . $st['student_number'] . ')'); ?>"
                                            data-message="Are you sure you want to delete this student record? All transcript entries, enrollments, and attendance records will be removed."
                                            data-csrf="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_students; ?> total students)
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&faculty_id=<?php echo $filter_faculty; ?>&department_id=<?php echo $filter_dept; ?>&program_id=<?php echo $filter_prog; ?>&q=<?php echo urlencode($search_query); ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?php echo ($page === $p) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $p; ?>&faculty_id=<?php echo $filter_faculty; ?>&department_id=<?php echo $filter_dept; ?>&program_id=<?php echo $filter_prog; ?>&q=<?php echo urlencode($search_query); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&faculty_id=<?php echo $filter_faculty; ?>&department_id=<?php echo $filter_dept; ?>&program_id=<?php echo $filter_prog; ?>&q=<?php echo urlencode($search_query); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
