<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Edit University Student Information (FR-03)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Edit Student Record';

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    set_flash('danger', 'Invalid student identifier provided.');
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// Fetch existing student & user record
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email 
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.student_id = :sid
    LIMIT 1
");
$stmt->execute(['sid' => $student_id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Student not found in database.');
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

// Fetch Dropdown Data
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();
$departments = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name ASC")->fetchAll();
$programs = $pdo->query("SELECT program_id, program_name, program_code FROM programs ORDER BY program_name ASC")->fetchAll();
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name FROM semesters ORDER BY semester_name ASC")->fetchAll();

$full_name           = $student['full_name'];
$email               = $student['email'];
$student_number      = $student['student_number'];
$registration_number = $student['registration_number'] ?? $student['student_number'];
$faculty_id          = $student['faculty_id'];
$department_id       = $student['department_id'];
$program_id          = $student['program_id'];
$academic_year_id    = $student['academic_year_id'];
$semester_id         = $student['semester_id'];
$phone               = $student['phone'];
$date_of_birth       = $student['date_of_birth'];
$address             = $student['address'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/edit_student.php?id=' . $student_id));
        exit;
    }

    $full_name           = trim($_POST['full_name'] ?? '');
    $email               = trim($_POST['email'] ?? '');
    $new_password        = $_POST['new_password'] ?? '';
    $student_number      = strtoupper(trim($_POST['student_number'] ?? ''));
    $registration_number = strtoupper(trim($_POST['registration_number'] ?? ''));
    $faculty_id          = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
    $department_id       = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $program_id          = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $academic_year_id    = !empty($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : null;
    $semester_id         = !empty($_POST['semester_id']) ? (int)$_POST['semester_id'] : null;
    $phone               = trim($_POST['phone'] ?? '');
    $date_of_birth       = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $address             = trim($_POST['address'] ?? '');

    $errors = [];

    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (empty($student_number)) $errors[] = 'Student ID is required.';

    // Check unique email (excluding current user_id)
    $chkEmail = $pdo->prepare("SELECT user_id FROM users WHERE email = :em AND user_id != :uid LIMIT 1");
    $chkEmail->execute(['em' => $email, 'uid' => $student['user_id']]);
    if ($chkEmail->fetch()) {
        $errors[] = 'The email address "' . htmlspecialchars($email) . '" is already in use by another account.';
    }

    // Check unique student number (excluding current student_id)
    $chkNum = $pdo->prepare("SELECT student_id FROM students WHERE student_number = :snum AND student_id != :sid LIMIT 1");
    $chkNum->execute(['snum' => $student_number, 'sid' => $student_id]);
    if ($chkNum->fetch()) {
        $errors[] = 'The Student ID "' . htmlspecialchars($student_number) . '" is already assigned to another student.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update users table
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    throw new Exception('New password must be at least 6 characters long.');
                }
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, password = :pwd WHERE user_id = :uid");
                $uStmt->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash, 'uid' => $student['user_id']]);
            } else {
                $uStmt = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em WHERE user_id = :uid");
                $uStmt->execute(['fn' => $full_name, 'em' => $email, 'uid' => $student['user_id']]);
            }

            // 2. Update students table
            $sStmt = $pdo->prepare("
                UPDATE students 
                SET student_number = :snum, registration_number = :reg, 
                    faculty_id = :fid, department_id = :did, program_id = :pid, 
                    academic_year_id = :yid, semester_id = :semid,
                    phone = :phone, address = :addr, date_of_birth = :dob 
                WHERE student_id = :sid
            ");
            $sStmt->execute([
                'snum'  => $student_number,
                'reg'   => $registration_number,
                'fid'   => $faculty_id,
                'did'   => $department_id,
                'pid'   => $program_id,
                'yid'   => $academic_year_id,
                'semid' => $semester_id,
                'phone' => $phone,
                'addr'  => $address,
                'dob'   => $date_of_birth,
                'sid'   => $student_id
            ]);

            $pdo->commit();
            set_flash('success', 'Student record for "' . htmlspecialchars($full_name) . '" updated successfully!');
            header('Location: ' . base_url('admin/students.php'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        set_flash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Update University Student</h4>
                <p class="text-muted small mb-0">Modify academic placement, personal info, or credentials for student #<?php echo $student_id; ?>.</p>
            </div>
            <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Student Directory
            </a>
        </div>

        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-pencil-square text-primary"></i> Edit Profile: <?php echo htmlspecialchars($student['full_name']); ?>
                </h2>
            </div>
            <div class="card-modern-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        1. Account & Security
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Institutional Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Student ID <span class="text-danger">*</span></label>
                            <input type="text" name="student_number" class="form-control text-uppercase" 
                                   value="<?php echo htmlspecialchars($student_number); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Registration Number</label>
                            <input type="text" name="registration_number" class="form-control text-uppercase" 
                                   value="<?php echo htmlspecialchars($registration_number); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Reset Password (Optional)</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="editPassword" class="form-control" 
                                       placeholder="Leave blank to keep unchanged">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="editPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        2. Academic Placement
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>" <?php echo ($faculty_id == $f['faculty_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>" <?php echo ($department_id == $d['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['department_code'] . ' - ' . $d['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Degree Program</label>
                            <select name="program_id" class="form-select">
                                <option value="">-- Select Program --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?php echo $p['program_id']; ?>" <?php echo ($program_id == $p['program_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['program_code'] . ' - ' . $p['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Academic Year</label>
                            <select name="academic_year_id" class="form-select">
                                <option value="">-- Select Academic Year --</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y['year_id']; ?>" <?php echo ($academic_year_id == $y['year_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($y['year_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>" <?php echo ($semester_id == $s['semester_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        3. Contact Information
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Residential Address</label>
                            <textarea name="address" rows="2" class="form-control"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
