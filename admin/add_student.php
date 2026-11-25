<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Student Registration (FR-02)
// ==========================================================
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = get_db_connection();
$page_title = 'Register University Student';

// Fetch Dropdown Data
$faculties = $pdo->query("SELECT faculty_id, faculty_name, faculty_code FROM faculties ORDER BY faculty_name ASC")->fetchAll();
$departments = $pdo->query("SELECT department_id, department_name, department_code, faculty_id FROM departments ORDER BY department_name ASC")->fetchAll();
$programs = $pdo->query("SELECT program_id, program_name, program_code, department_id FROM programs ORDER BY program_name ASC")->fetchAll();
$years = $pdo->query("SELECT year_id, year_name, is_current FROM academic_years ORDER BY year_name DESC")->fetchAll();
$semesters = $pdo->query("SELECT semester_id, semester_name, is_current FROM semesters ORDER BY semester_name ASC")->fetchAll();
$courses = $pdo->query("SELECT course_id, course_name, course_code, credit_hours FROM courses ORDER BY course_code ASC")->fetchAll();

// Default values & suggestions
$student_count = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$suggested_student_num = 'STU-' . date('Y') . '-' . str_pad($student_count + 1, 3, '0', STR_PAD_LEFT);
$suggested_reg_num = 'REG/' . date('Y') . '/' . str_pad($student_count + 1, 4, '0', STR_PAD_LEFT);

$full_name = '';
$email = '';
$student_number = $suggested_student_num;
$registration_number = $suggested_reg_num;
$faculty_id = '';
$department_id = '';
$program_id = '';
$academic_year_id = '';
$semester_id = '';
$phone = '';
$date_of_birth = '';
$address = '';
$selected_courses = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation expired. Please try again.');
        header('Location: ' . base_url('admin/add_student.php'));
        exit;
    }

    $full_name           = trim($_POST['full_name'] ?? '');
    $email               = trim($_POST['email'] ?? '');
    $password            = $_POST['password'] ?? '';
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
    $selected_courses    = $_POST['courses'] ?? [];

    $errors = [];

    // Validations
    if (empty($full_name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid institutional email is required.';
    if (empty($password) || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (empty($student_number)) $errors[] = 'Student ID is required.';

    // Check email uniqueness
    $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
    $checkEmail->execute(['email' => $email]);
    if ($checkEmail->fetch()) {
        $errors[] = 'Email "' . htmlspecialchars($email) . '" is already registered.';
    }

    // Check student_number uniqueness
    $checkNum = $pdo->prepare("SELECT student_id FROM students WHERE student_number = :snum LIMIT 1");
    $checkNum->execute(['snum' => $student_number]);
    if ($checkNum->fetch()) {
        $errors[] = 'Student ID "' . htmlspecialchars($student_number) . '" is already registered.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert into users table
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (:fn, :em, :pwd, 'student')");
            $uStmt->execute(['fn' => $full_name, 'em' => $email, 'pwd' => $hash]);
            $user_id = (int)$pdo->lastInsertId();

            // 2. Insert into students table
            $sStmt = $pdo->prepare("
                INSERT INTO students 
                    (user_id, student_number, registration_number, faculty_id, department_id, program_id, academic_year_id, semester_id, phone, address, date_of_birth) 
                VALUES 
                    (:uid, :snum, :reg, :fid, :did, :pid, :yid, :semid, :phone, :addr, :dob)
            ");
            $sStmt->execute([
                'uid'   => $user_id,
                'snum'  => $student_number,
                'reg'   => !empty($registration_number) ? $registration_number : $student_number,
                'fid'   => $faculty_id,
                'did'   => $department_id,
                'pid'   => $program_id,
                'yid'   => $academic_year_id,
                'semid' => $semester_id,
                'phone' => $phone,
                'addr'  => $address,
                'dob'   => $date_of_birth
            ]);
            $student_id = (int)$pdo->lastInsertId();

            // 3. Optional initial enrollments
            if (!empty($selected_courses)) {
                $enrStmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, academic_year_id, semester_id, enrollment_date) VALUES (:sid, :cid, :yid, :semid, CURRENT_DATE)");
                foreach ($selected_courses as $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) {
                        $enrStmt->execute([
                            'sid'   => $student_id,
                            'cid'   => $cid,
                            'yid'   => $academic_year_id,
                            'semid' => $semester_id
                        ]);
                    }
                }
            }

            $pdo->commit();
            set_flash('success', 'University Student "' . htmlspecialchars($full_name) . '" (' . htmlspecialchars($student_number) . ') registered successfully!');
            header('Location: ' . base_url('admin/students.php'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Student registration failed: ' . $e->getMessage();
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
                <h4 class="fw-bold mb-1">University Student Registration</h4>
                <p class="text-muted small mb-0">Register a new undergraduate or postgraduate student into the university academic registry.</p>
            </div>
            <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Student Directory
            </a>
        </div>

        <div class="card-modern">
            <div class="card-modern-header">
                <h2 class="card-modern-title fs-6">
                    <i class="bi bi-person-fill-add text-primary"></i> Student Registration Form
                </h2>
            </div>
            <div class="card-modern-body p-4">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        1. Account Credentials & Personal Identity
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" 
                                   placeholder="e.g. Hassan Ahmed Ali" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">University Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="student@xoghaye.edu" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="regPassword" class="form-control" 
                                       value="Student@123" minlength="6" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="regPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Default password: <code>Student@123</code> (changeable upon login).</div>
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
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        2. Academic Program & Placement
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Faculty</label>
                            <select name="faculty_id" id="facultySelect" class="form-select">
                                <option value="">-- Select Faculty --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['faculty_id']; ?>" <?php echo ($faculty_id == $f['faculty_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($f['faculty_code'] . ' - ' . $f['faculty_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Academic Department</label>
                            <select name="department_id" id="deptSelect" class="form-select">
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
                            <select name="program_id" id="progSelect" class="form-select">
                                <option value="">-- Select Degree Program --</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?php echo $p['program_id']; ?>" <?php echo ($program_id == $p['program_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['program_code'] . ' - ' . $p['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Admitted Academic Year</label>
                            <select name="academic_year_id" class="form-select">
                                <option value="">-- Select Academic Year --</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y['year_id']; ?>" <?php echo ($y['is_current'] == 1) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($y['year_name']); ?> <?php echo ($y['is_current'] == 1) ? '(Current Active)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Current Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">-- Select Semester --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo $s['semester_id']; ?>" <?php echo ($s['is_current'] == 1) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        3. Contact Details
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="+252 ... / +1 (555) 000-0000" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Residential Address</label>
                            <textarea name="address" rows="2" class="form-control" placeholder="Street, City, Country"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                    </div>

                    <h6 class="text-primary fw-bold text-uppercase small mb-3 border-bottom pb-2">
                        4. Initial Semester Course Registrations (Optional)
                    </h6>
                    <p class="text-muted small mb-3">Check the courses to register this student for this semester:</p>
                    <div class="row g-2 mb-4">
                        <?php if (empty($courses)): ?>
                            <div class="col-12 text-muted small">No courses registered in system yet.</div>
                        <?php else: ?>
                            <?php foreach ($courses as $c): ?>
                                <div class="col-md-6">
                                    <div class="form-check p-2 border rounded bg-light">
                                        <input class="form-check-input ms-1 me-2" type="checkbox" name="courses[]" 
                                               value="<?php echo $c['course_id']; ?>" id="crs_<?php echo $c['course_id']; ?>"
                                               <?php echo in_array($c['course_id'], $selected_courses) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-semibold text-dark" for="crs_<?php echo $c['course_id']; ?>">
                                            <span class="badge bg-primary text-white me-1"><?php echo htmlspecialchars($c['course_code']); ?></span>
                                            <?php echo htmlspecialchars($c['course_name']); ?>
                                            <span class="badge bg-white text-secondary border ms-1"><?php echo (int)$c['credit_hours']; ?> Credits</span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?php echo base_url('admin/students.php'); ?>" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle-fill me-1"></i> Register Student Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
