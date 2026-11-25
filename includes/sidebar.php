<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Navigation Sidebar
// ==========================================================
$user = current_user();
$role = $user['role'] ?? 'guest';
$current_script = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_route = $current_dir . '/' . $current_script;

function is_active($route, $current_route, $current_script) {
    if (strpos($route, '/') !== false) {
        return ($current_route === $route) ? 'active' : '';
    }
    return ($current_script === $route) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <!-- University Brand Identity -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="brand-title">
            <?php echo APP_SHORT_NAME; ?>
            <span class="brand-tagline">RESULT MANAGER &bull; UNIVERSITY</span>
        </div>
    </div>

    <!-- Navigation Items -->
    <div class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <!-- ================= ADMIN / REGISTRAR NAVIGATION ================= -->
            <div class="sidebar-heading">Overview</div>
            <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="nav-link-custom <?php echo is_active('admin/dashboard.php', $current_route, $current_script); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>University Dashboard</span>
            </a>

            <div class="sidebar-heading">Academic Structure</div>
            <a href="<?php echo base_url('admin/faculties.php'); ?>" class="nav-link-custom <?php echo is_active('admin/faculties.php', $current_route, $current_script); ?>">
                <i class="bi bi-buildings-fill"></i>
                <span>Faculties</span>
            </a>
            <a href="<?php echo base_url('admin/departments.php'); ?>" class="nav-link-custom <?php echo is_active('admin/departments.php', $current_route, $current_script); ?>">
                <i class="bi bi-diagram-3-fill"></i>
                <span>Departments</span>
            </a>
            <a href="<?php echo base_url('admin/programs.php'); ?>" class="nav-link-custom <?php echo is_active('admin/programs.php', $current_route, $current_script); ?>">
                <i class="bi bi-mortarboard"></i>
                <span>Degree Programs</span>
            </a>
            <a href="<?php echo base_url('admin/academic_years.php'); ?>" class="nav-link-custom <?php echo is_active('admin/academic_years.php', $current_route, $current_script); ?>">
                <i class="bi bi-calendar3-range-fill"></i>
                <span>Academic Years</span>
            </a>
            <a href="<?php echo base_url('admin/semesters.php'); ?>" class="nav-link-custom <?php echo is_active('admin/semesters.php', $current_route, $current_script); ?>">
                <i class="bi bi-calendar-week-fill"></i>
                <span>Semesters</span>
            </a>

            <div class="sidebar-heading">Academic Management</div>
            <a href="<?php echo base_url('admin/students.php'); ?>" class="nav-link-custom <?php echo (in_array($current_script, ['students.php', 'edit_student.php', 'view_student.php'])) ? 'active' : ''; ?>">
                <i class="bi bi-people-fill"></i>
                <span>Students Directory</span>
            </a>
            <a href="<?php echo base_url('admin/add_student.php'); ?>" class="nav-link-custom <?php echo is_active('admin/add_student.php', $current_route, $current_script); ?>">
                <i class="bi bi-person-plus-fill"></i>
                <span>Register Student</span>
            </a>
            <a href="<?php echo base_url('admin/teachers.php'); ?>" class="nav-link-custom <?php echo is_active('admin/teachers.php', $current_route, $current_script); ?>">
                <i class="bi bi-person-badge-fill"></i>
                <span>Lecturers & Faculty</span>
            </a>
            <a href="<?php echo base_url('admin/courses.php'); ?>" class="nav-link-custom <?php echo is_active('admin/courses.php', $current_route, $current_script); ?>">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Courses & Units</span>
            </a>
            <a href="<?php echo base_url('admin/enrollments.php'); ?>" class="nav-link-custom <?php echo is_active('admin/enrollments.php', $current_route, $current_script); ?>">
                <i class="bi bi-ui-checks"></i>
                <span>Student Enrollments</span>
            </a>

            <div class="sidebar-heading">Examination & Results</div>
            <a href="<?php echo base_url('admin/attendance.php'); ?>" class="nav-link-custom <?php echo is_active('admin/attendance.php', $current_route, $current_script); ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance Logs</span>
            </a>
            <a href="<?php echo base_url('admin/grades.php'); ?>" class="nav-link-custom <?php echo is_active('admin/grades.php', $current_route, $current_script); ?>">
                <i class="bi bi-award-fill"></i>
                <span>Results & Grades</span>
            </a>

            <div class="sidebar-heading">Administration</div>
            <a href="<?php echo base_url('admin/users.php'); ?>" class="nav-link-custom <?php echo is_active('admin/users.php', $current_route, $current_script); ?>">
                <i class="bi bi-shield-lock-fill"></i>
                <span>System Accounts</span>
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- ================= LECTURER NAVIGATION ================= -->
            <div class="sidebar-heading">Overview</div>
            <a href="<?php echo base_url('teacher/dashboard.php'); ?>" class="nav-link-custom <?php echo is_active('teacher/dashboard.php', $current_route, $current_script); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Lecturer Dashboard</span>
            </a>

            <div class="sidebar-heading">Academic Duties</div>
            <a href="<?php echo base_url('teacher/courses.php'); ?>" class="nav-link-custom <?php echo is_active('teacher/courses.php', $current_route, $current_script); ?>">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Assigned Courses</span>
            </a>
            <a href="<?php echo base_url('teacher/attendance.php'); ?>" class="nav-link-custom <?php echo is_active('teacher/attendance.php', $current_route, $current_script); ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Lecture Attendance</span>
            </a>
            <a href="<?php echo base_url('teacher/grades.php'); ?>" class="nav-link-custom <?php echo is_active('teacher/grades.php', $current_route, $current_script); ?>">
                <i class="bi bi-award-fill"></i>
                <span>Submit Exam Results</span>
            </a>

        <?php elseif ($role === 'student'): ?>
            <!-- ================= STUDENT NAVIGATION ================= -->
            <div class="sidebar-heading">Academic Portal</div>
            <a href="<?php echo base_url('student/dashboard.php'); ?>" class="nav-link-custom <?php echo is_active('student/dashboard.php', $current_route, $current_script); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Student Dashboard</span>
            </a>
            <a href="<?php echo base_url('student/courses.php'); ?>" class="nav-link-custom <?php echo is_active('student/courses.php', $current_route, $current_script); ?>">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Enrolled Courses</span>
            </a>
            <a href="<?php echo base_url('student/attendance.php'); ?>" class="nav-link-custom <?php echo is_active('student/attendance.php', $current_route, $current_script); ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance Log</span>
            </a>
            <a href="<?php echo base_url('student/grades.php'); ?>" class="nav-link-custom <?php echo is_active('student/grades.php', $current_route, $current_script); ?>">
                <i class="bi bi-award-fill"></i>
                <span>Academic Transcript</span>
            </a>
            <a href="<?php echo base_url('student/profile.php'); ?>" class="nav-link-custom <?php echo is_active('student/profile.php', $current_route, $current_script); ?>">
                <i class="bi bi-person-lines-fill"></i>
                <span>Student Profile</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-heading">Account</div>
        <a href="<?php echo base_url('profile.php'); ?>" class="nav-link-custom <?php echo ($current_script === 'profile.php' && $current_dir !== 'student') ? 'active' : ''; ?>">
            <i class="bi bi-gear-fill"></i>
            <span>Security & Settings</span>
        </a>
    </div>

    <!-- Sidebar User Footer -->
    <div class="sidebar-footer">
        <?php if ($user): ?>
            <div class="user-snippet">
                <div class="user-avatar-mini">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-info-text">
                    <div class="user-name" title="<?php echo htmlspecialchars($user['full_name']); ?>">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </div>
                    <span class="user-role-badge <?php 
                        echo ($user['role'] === 'admin') ? 'badge-role-admin' : (($user['role'] === 'teacher') ? 'badge-role-teacher' : 'badge-role-student'); 
                    ?>">
                        <?php 
                        if ($user['role'] === 'admin') echo 'Administrator';
                        elseif ($user['role'] === 'teacher') echo 'Lecturer';
                        else echo 'Student';
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</aside>
