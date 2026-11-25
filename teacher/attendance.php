<?php
// ==========================================================
// XOGHAYE - RESULT MANAGER - Lecturer Attendance Management
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');

$pdo = get_db_connection();
$page_title = 'Record & Manage Attendance';
$user = current_user();

// Fetch teacher_id
$tStmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :uid LIMIT 1");
$tStmt->execute(['uid' => $user['user_id']]);
$teacher_id = (int)$tStmt->fetchColumn();

// Fetch teacher's assigned courses
$courses_stmt = $pdo->prepare("SELECT course_id, course_name, course_code FROM courses WHERE teacher_id = :tid ORDER BY course_code ASC");
$courses_stmt->execute(['tid' => $teacher_id]);
$courses = $courses_stmt->fetchAll();

// Selected parameters for recording
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['course_id'] ?? 0);
$session_date = isset($_GET['session_date']) ? trim($_GET['session_date']) : date('Y-m-d');
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'record';

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('teacher/attendance.php?course_id=' . $selected_course_id . '&session_date=' . $session_date));
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Batch Record Attendance
    if ($action === 'save_attendance') {
        $course_id   = (int)($_POST['course_id'] ?? 0);
        $attend_date = trim($_POST['attendance_date'] ?? date('Y-m-d'));
        $statuses    = $_POST['status'] ?? []; // student_id => status

        // Verify course belongs to this teacher
        $chk = $pdo->prepare("SELECT course_id, academic_year_id, semester_id FROM courses WHERE course_id = :cid AND teacher_id = :tid");
        $chk->execute(['cid' => $course_id, 'tid' => $teacher_id]);
        $course_info = $chk->fetch();
        if (!$course_info) {
            set_flash('danger', 'Unauthorized: You are not assigned to this course.');
            header('Location: ' . base_url('teacher/attendance.php'));
            exit;
        }

        if (empty($statuses)) {
            set_flash('warning', 'No students were submitted.');
        } else {
            try {
                $pdo->beginTransaction();
                $upsert = $pdo->prepare("
                    INSERT INTO attendance (student_id, course_id, attendance_date, status, academic_year_id, semester_id) 
                    VALUES (:sid, :cid, :adate, :stat, :ay, :sem)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");

                foreach ($statuses as $sid => $status) {
                    $sid = (int)$sid;
                    if ($sid > 0 && in_array($status, ['Present', 'Absent', 'Late', 'Excused'])) {
                        $upsert->execute([
                            'sid'   => $sid,
                            'cid'   => $course_id,
                            'adate' => $attend_date,
                            'stat'  => $status,
                            'ay'    => $course_info['academic_year_id'] ?? null,
                            'sem'   => $course_info['semester_id'] ?? null
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Attendance for ' . format_date($attend_date) . ' recorded successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Error recording attendance: ' . $e->getMessage());
            }
        }
        header('Location: ' . base_url('teacher/attendance.php?course_id=' . $course_id . '&session_date=' . $attend_date));
        exit;
    }

    // Action 2: Update single attendance record from review tab
    if ($action === 'quick_update') {
        $aid = (int)($_POST['attendance_id'] ?? 0);
        $new_st = $_POST['status'] ?? 'Present';

        if ($aid > 0 && in_array($new_st, ['Present', 'Absent', 'Late', 'Excused'])) {
            // Verify attendance belongs to a course taught by this teacher
            $chkAtt = $pdo->prepare("
                SELECT a.attendance_id FROM attendance a 
                JOIN courses c ON a.course_id = c.course_id 
                WHERE a.attendance_id = :aid AND c.teacher_id = :tid
            ");
            $chkAtt->execute(['aid' => $aid, 'tid' => $teacher_id]);
            if ($chkAtt->fetch()) {
                $up = $pdo->prepare("UPDATE attendance SET status = :st WHERE attendance_id = :aid");
                $up->execute(['st' => $new_st, 'aid' => $aid]);
                set_flash('success', 'Attendance entry updated.');
            }
        }
        header('Location: ' . base_url('teacher/attendance.php?tab=review'));
        exit;
    }
}

// Fetch enrolled students for the active course
$students = [];
$existing_attendance = [];
$active_course = null;

if ($selected_course_id > 0) {
    // Verify course belongs to this teacher
    $chkStmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = :cid AND teacher_id = :tid LIMIT 1");
    $chkStmt->execute(['cid' => $selected_course_id, 'tid' => $teacher_id]);
    $active_course = $chkStmt->fetch();

    if ($active_course) {
        $sStmt = $pdo->prepare("
            SELECT s.student_id, s.student_number, u.full_name, u.email
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.course_id = :cid
            ORDER BY u.full_name ASC
        ");
        $sStmt->execute(['cid' => $selected_course_id]);
        $students = $sStmt->fetchAll();

        // Check if attendance already exists for this course & date
        $existStmt = $pdo->prepare("
            SELECT student_id, status 
            FROM attendance 
            WHERE course_id = :cid AND attendance_date = :adate
        ");
        $existStmt->execute(['cid' => $selected_course_id, 'adate' => $session_date]);
        $existing_attendance = $existStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

// Fetch review history for this teacher's assigned courses
$history = [];
if ($active_tab === 'review' && !empty($courses)) {
    $cids = array_column($courses, 'course_id');
    $placeholders = implode(',', array_fill(0, count($cids), '?'));

    $histStmt = $pdo->prepare("
        SELECT a.*, c.course_code, c.course_name, s.student_number, u.full_name AS student_name
        FROM attendance a
        JOIN courses c ON a.course_id = c.course_id
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        WHERE a.course_id IN ($placeholders)
        ORDER BY a.attendance_date DESC, c.course_code ASC, u.full_name ASC
        LIMIT 100
    ");
    $histStmt->execute($cids);
    $history = $histStmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">Student Attendance Management</h4>
        <p class="text-muted small mb-0">Record daily classroom roll call and audit attendance history for your assigned classes.</p>
    </div>
    <!-- Tabs Switcher -->
    <div class="btn-group">
        <a href="<?php echo base_url('teacher/attendance.php?tab=record&course_id=' . $selected_course_id); ?>" 
           class="btn <?php echo ($active_tab === 'record') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
            <i class="bi bi-calendar-plus me-1"></i> Record Session
        </a>
        <a href="<?php echo base_url('teacher/attendance.php?tab=review'); ?>" 
           class="btn <?php echo ($active_tab === 'review') ? 'btn-primary' : 'btn-outline-secondary'; ?>">
            <i class="bi bi-clock-history me-1"></i> Review History
        </a>
    </div>
</div>

<?php if (empty($courses)): ?>
    <div class="card-modern text-center py-5">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
        <h5>No Assigned Courses</h5>
        <p class="text-muted">You do not have any courses assigned to manage attendance.</p>
    </div>
<?php elseif ($active_tab === 'record'): ?>
    <!-- 1. Selection & Configuration Form -->
    <div class="card-modern mb-4">
        <div class="card-modern-body py-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="record">

                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Assigned Course:</label>
                    <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['course_id']; ?>" <?php echo ($selected_course_id === (int)$c['course_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Attendance Date:</label>
                    <input type="date" name="session_date" class="form-control form-control-sm" 
                           value="<?php echo htmlspecialchars($session_date); ?>" onchange="this.form.submit()">
                </div>

                <div class="col-md-3 text-md-end mt-md-4">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-arrow-repeat me-1"></i> Load Roll Call
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Attendance Recording Table Form -->
    <div class="card-modern">
        <div class="card-modern-header flex-column flex-md-row gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary text-white fs-6 px-3 py-2">
                    <?php echo htmlspecialchars($active_course['course_code'] ?? ''); ?>
                </span>
                <span class="fw-bold text-dark">
                    Date: <?php echo format_date($session_date); ?>
                </span>
                <?php if (!empty($existing_attendance)): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border ms-2">
                        <i class="bi bi-info-circle me-1"></i> Pre-recorded (Update Mode)
                    </span>
                <?php endif; ?>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-success" id="btnMarkAllPresent">
                    <i class="bi bi-check-all me-1"></i> Mark All Present
                </button>
            </div>
        </div>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($session_date); ?>">

            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Student Details</th>
                            <th>Student #</th>
                            <th class="text-center" style="width: 420px;">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 text-muted d-block mb-2"></i>
                                    No students are enrolled in this course yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $idx = 1; foreach ($students as $st): 
                                $curr_status = $existing_attendance[$st['student_id']] ?? 'Present';
                            ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $idx++; ?></td>
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
                                    <td class="text-center">
                                        <div class="btn-group w-100" role="group">
                                            <!-- Present -->
                                            <input type="radio" class="btn-check status-radio status-present" 
                                                   name="status[<?php echo $st['student_id']; ?>]" 
                                                   id="p_<?php echo $st['student_id']; ?>" value="Present" 
                                                   <?php echo ($curr_status === 'Present') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success btn-sm py-1" for="p_<?php echo $st['student_id']; ?>">
                                                <i class="bi bi-check-circle me-1"></i> Present
                                            </label>

                                            <!-- Late -->
                                            <input type="radio" class="btn-check status-radio" 
                                                   name="status[<?php echo $st['student_id']; ?>]" 
                                                   id="l_<?php echo $st['student_id']; ?>" value="Late" 
                                                   <?php echo ($curr_status === 'Late') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning text-dark btn-sm py-1" for="l_<?php echo $st['student_id']; ?>">
                                                <i class="bi bi-clock me-1"></i> Late
                                            </label>

                                            <!-- Absent -->
                                            <input type="radio" class="btn-check status-radio" 
                                                   name="status[<?php echo $st['student_id']; ?>]" 
                                                   id="a_<?php echo $st['student_id']; ?>" value="Absent" 
                                                   <?php echo ($curr_status === 'Absent') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger btn-sm py-1" for="a_<?php echo $st['student_id']; ?>">
                                                <i class="bi bi-x-circle me-1"></i> Absent
                                            </label>

                                            <!-- Excused -->
                                            <input type="radio" class="btn-check status-radio" 
                                                   name="status[<?php echo $st['student_id']; ?>]" 
                                                   id="e_<?php echo $st['student_id']; ?>" value="Excused" 
                                                   <?php echo ($curr_status === 'Excused') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info text-dark btn-sm py-1" for="e_<?php echo $st['student_id']; ?>">
                                                Excused
                                            </label>
                                        </div>
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
                        <i class="bi bi-cloud-check-fill me-1"></i> Save Class Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

<?php elseif ($active_tab === 'review'): ?>
    <!-- Review Tab: Historical Records -->
    <div class="card-modern">
        <div class="card-modern-header flex-column flex-md-row gap-3">
            <h2 class="card-modern-title fs-6">
                <i class="bi bi-clock-history text-primary"></i> Attendance History Log (Last 100 entries)
            </h2>
            <div class="input-group" style="max-width: 280px;">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Filter history...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-modern table-searchable align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Student #</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th class="text-end">Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                No attendance entries recorded yet for your courses.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): 
                            $badge_class = 'badge-present';
                            if ($h['status'] === 'Absent') $badge_class = 'badge-absent';
                            elseif ($h['status'] === 'Late') $badge_class = 'badge-late';
                            elseif ($h['status'] === 'Excused') $badge_class = 'badge-excused';
                        ?>
                            <tr>
                                <td><?php echo format_date($h['attendance_date']); ?></td>
                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($h['student_name']); ?></td>
                                <td><span class="badge bg-light text-primary border"><?php echo htmlspecialchars($h['student_number']); ?></span></td>
                                <td>
                                    <span class="badge bg-primary me-1"><?php echo htmlspecialchars($h['course_code']); ?></span>
                                    <?php echo htmlspecialchars($h['course_name']); ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo htmlspecialchars($h['status']); ?></span>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="" class="d-inline-flex gap-1">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="quick_update">
                                        <input type="hidden" name="attendance_id" value="<?php echo $h['attendance_id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: 105px;" onchange="this.form.submit()">
                                            <option value="Present" <?php echo ($h['status'] === 'Present') ? 'selected' : ''; ?>>Present</option>
                                            <option value="Absent" <?php echo ($h['status'] === 'Absent') ? 'selected' : ''; ?>>Absent</option>
                                            <option value="Late" <?php echo ($h['status'] === 'Late') ? 'selected' : ''; ?>>Late</option>
                                            <option value="Excused" <?php echo ($h['status'] === 'Excused') ? 'selected' : ''; ?>>Excused</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // "Mark All Present" button click handler
    const markAllBtn = document.getElementById('btnMarkAllPresent');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.status-present').forEach(function(radio) {
                radio.checked = true;
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
