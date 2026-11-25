<?php
// ==========================================================
// SCHOOL MANAGER - Student Personal Attendance Records
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = get_db_connection();
$page_title = 'My Attendance Records';
$user = current_user();

// Fetch student_id
$sStmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $user['user_id']]);
$student_id = (int)$sStmt->fetchColumn();

// Course Filter
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Fetch enrolled courses for dropdown
$courses = $pdo->prepare("
    SELECT c.course_id, c.course_code, c.course_name 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = :sid
    ORDER BY c.course_code ASC
");
$courses->execute(['sid' => $student_id]);
$enrolled_courses = $courses->fetchAll();

// Fetch Attendance Stats for this student
$statStmt = $pdo->prepare("
    SELECT status, COUNT(*) as cnt 
    FROM attendance 
    WHERE student_id = :sid 
    GROUP BY status
");
$statStmt->execute(['sid' => $student_id]);
$stats = $statStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_sessions = array_sum($stats) ?: 0;
$present_cnt    = $stats['Present'] ?? 0;
$late_cnt       = $stats['Late'] ?? 0;
$absent_cnt     = $stats['Absent'] ?? 0;
$excused_cnt    = $stats['Excused'] ?? 0;
$att_rate = ($total_sessions > 0) ? round(($present_cnt / $total_sessions) * 100) : 100;

// Build records query
$where = ["a.student_id = :sid"];
$params = ['sid' => $student_id];

if ($selected_course_id > 0) {
    $where[] = "a.course_id = :cid";
    $params['cid'] = $selected_course_id;
}

$where_sql = "WHERE " . implode(" AND ", $where);

$query = "
    SELECT a.*, c.course_code, c.course_name,
           u_t.full_name AS teacher_name
    FROM attendance a
    JOIN courses c ON a.course_id = c.course_id
    LEFT JOIN teachers t ON c.teacher_id = t.teacher_id
    LEFT JOIN users u_t ON t.user_id = u_t.user_id
    $where_sql
    ORDER BY a.attendance_date DESC, c.course_code ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">My Attendance History</h4>
        <p class="text-muted small mb-0">Detailed breakdown of your session attendance records across all enrolled subjects.</p>
    </div>
</div>

<!-- Metric Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Present Rate</div>
                <div class="card-stat-value text-success fs-3"><?php echo $att_rate; ?>%</div>
                <small class="text-muted"><?php echo $present_cnt; ?> / <?php echo $total_sessions; ?> Sessions</small>
            </div>
            <div class="card-stat-icon icon-emerald"><i class="bi bi-check-lg"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Late Arrivals</div>
                <div class="card-stat-value text-warning fs-3"><?php echo $late_cnt; ?></div>
                <small class="text-muted">Recorded Tardy</small>
            </div>
            <div class="card-stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Unexcused Absences</div>
                <div class="card-stat-value text-danger fs-3"><?php echo $absent_cnt; ?></div>
                <small class="text-muted">Missed Classes</small>
            </div>
            <div class="card-stat-icon icon-rose"><i class="bi bi-x-circle"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-stat p-3">
            <div>
                <div class="card-stat-title">Excused Leaves</div>
                <div class="card-stat-value text-info fs-3"><?php echo $excused_cnt; ?></div>
                <small class="text-muted">Approved Requests</small>
            </div>
            <div class="card-stat-icon icon-blue"><i class="bi bi-shield-check"></i></div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card-modern mb-4">
    <div class="card-modern-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1">Filter by Enrolled Course:</label>
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- All Enrolled Courses (<?php echo count($enrolled_courses); ?>) --</option>
                    <?php foreach ($enrolled_courses as $c): ?>
                        <option value="<?php echo $c['course_id']; ?>" <?php echo ($selected_course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Search Logs:</label>
                <input type="text" id="tableSearch" class="form-control form-control-sm" placeholder="Filter by date or course...">
            </div>

            <div class="col-md-2 text-md-end mt-md-4">
                <?php if ($selected_course_id > 0): ?>
                    <a href="<?php echo base_url('student/attendance.php'); ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Table of Attendance Records -->
<div class="card-modern">
    <div class="card-modern-header">
        <h2 class="card-modern-title fs-6">
            <i class="bi bi-calendar-check text-primary"></i> Attendance Log History (<?php echo count($records); ?>)
        </h2>
    </div>

    <div class="table-responsive">
        <table class="table table-modern table-searchable align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Instructor</th>
                    <th>Recorded Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                            No attendance records found for the selected course.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $r): 
                        $badge_class = 'badge-present';
                        if ($r['status'] === 'Absent') $badge_class = 'badge-absent';
                        elseif ($r['status'] === 'Late') $badge_class = 'badge-late';
                        elseif ($r['status'] === 'Excused') $badge_class = 'badge-excused';
                    ?>
                        <tr>
                            <td class="fw-semibold text-dark">
                                <?php echo format_date($r['attendance_date']); ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($r['course_code']); ?></span>
                            </td>
                            <td class="fw-bold text-dark">
                                <?php echo htmlspecialchars($r['course_name']); ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo htmlspecialchars($r['teacher_name'] ?? 'Instructor'); ?>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($r['status']); ?>
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
