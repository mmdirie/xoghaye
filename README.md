# SCHOOL MANAGER – Student Management System
> **Tagline:** Smart Education, Better Management.

A modern, responsive, web-based Student Management System built using **HTML5, CSS3, Bootstrap 5, JavaScript, PHP 8 (PDO), and MySQL**, designed specifically for educational institutions and fully compatible with **XAMPP**.

---

## 📌 System Architecture & User Roles

SCHOOL MANAGER provides a unified, secure academic management system with role-based access control across three user roles:

### 1. 🛡️ Administrator
- **Dashboard:** Live analytics including Total Students, Faculty/Teachers, Active Courses, Total Accounts, Recent Registrations, and Campus Attendance Rate.
- **Student Management:** Full CRUD operations — Register new students, search student directory, view comprehensive student profiles (rosters, GPA, attendance history), update records, and delete records with confirmation modals.
- **Faculty Management:** Add, update, assign, and delete teacher records and departments.
- **Course Management:** Create courses, assign and re-assign teachers, manage syllabi, and view enrolled students.
- **Course Enrollments:** Register and drop students from courses.
- **Attendance Records:** System-wide attendance audit logs with course, date, and status filters.
- **Academic Grades:** Complete institution gradebook, class averages, grade distributions (Honor Roll vs. Needs Support), and record updates.
- **User Accounts:** Manage credentials, roles (admin, teacher, student), and reset passwords.

### 2. 👨‍🏫 Teacher
- **Dashboard:** View assigned courses, total enrolled students, total recorded attendance sessions, and grades submitted.
- **Class Rosters:** View students enrolled specifically in assigned courses, attendance percentages, and current grades.
- **Attendance Management (FR-04):** Roll call tool for assigned courses with single-click "Mark All Present", custom statuses (*Present, Absent, Late, Excused*), date picker, and historical audit logs.
- **Grade Management (FR-05):** Enter numeric marks (0–100) with automatic letter grade computation (*A, A-, B+, B, B-, C+, C, C-, D, F*), remarks, and real-time updates.
- *Strict Security:* Teachers are restricted to only viewing and managing their assigned courses.

### 3. 🎓 Student
- **Personal Dashboard:** Personal profile information, active enrolled courses count, overall attendance percentage, cumulative GPA (4.0 scale), and recent test scores.
- **My Courses:** Enrolled curriculum with instructor details, department, syllabus, course-specific attendance %, and current grade.
- **My Attendance:** Complete session history by date and course with status pills.
- **My Grades & Results:** Official Academic Report Card with cumulative GPA, performance standing (*Dean's Honor List*, *Good Standing*), and printable view.
- **Profile & Security:** View academic profile, update personal contact details (phone, address), and securely change password.
- *Strict Security:* Students can only view their own records and results.

---

## 🔑 Test Login Credentials

| Role | Name | Email Address | Password |
| :--- | :--- | :--- | :--- |
| **Administrator** | System Administrator | `admin@schoolmanager.edu` | `Admin@123` |
| **Teacher** | Dr. Sarah Jenkins (CompSci) | `sarah.jenkins@schoolmanager.edu` | `Teacher@123` |
| **Teacher** | Prof. Robert Chen (Math) | `robert.chen@schoolmanager.edu` | `Teacher@123` |
| **Teacher** | Dr. Emily Davis (Physics) | `emily.davis@schoolmanager.edu` | `Teacher@123` |
| **Student** | Alex Morgan (`STU-2024-001`) | `alex.morgan@schoolmanager.edu` | `Student@123` |
| **Student** | Maria Garcia (`STU-2024-002`) | `maria.garcia@schoolmanager.edu` | `Student@123` |
| **Student** | David Kim (`STU-2024-003`) | `david.kim@schoolmanager.edu` | `Student@123` |

> 💡 **Quick Login:** On the login page, click any of the **Quick Test Credentials** pills (*Admin*, *Teacher*, or *Student*) to auto-fill the login form instantly!

---

## 🗄️ Database Architecture (`campuspro_db`)

The database is built on InnoDB with UTF-8 Unicode encoding and strict foreign key relationships:

1. **`users`**: Central authentication table (`user_id`, `full_name`, `email`, `password`, `role`, timestamps).
2. **`students`**: Student academic profiles (`student_id`, `user_id`, `student_number`, `phone`, `address`, `date_of_birth`).
3. **`teachers`**: Faculty profiles (`teacher_id`, `user_id`, `department`, `phone`).
4. **`courses`**: Academic courses (`course_id`, `course_name`, `course_code`, `teacher_id`, `description`).
5. **`enrollments`**: Course roster mappings (`enrollment_id`, `student_id`, `course_id`, `enrollment_date`).
6. **`attendance`**: Daily attendance records (`attendance_id`, `student_id`, `course_id`, `attendance_date`, `status`).
7. **`grades`**: Academic assessments (`grade_id`, `student_id`, `course_id`, `score`, `letter_grade`, `remarks`).

---

## 🚀 Installation & Setup Guide for XAMPP

### Step 1: Start XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 2: Database Setup
1. Open your browser and navigate to phpMyAdmin:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click on the **Databases** tab and create a database named `campuspro_db`.
3. Select `campuspro_db`, click on the **Import** tab, browse and choose:
   ```
   database/campuspro_db.sql
   ```
4. Click **Import** (or **Go**) and choose `database/campuspro_db.sql` to create the core tables and initial seed data.
5. Import `database/upgrade_to_xoghaye.sql` to create the additional academic-management tables and apply safe deletion constraints.

*(Alternatively, run via command line:)*
```bash
mysql -u root < database/campuspro_db.sql
mysql -u root < database/upgrade_to_xoghaye.sql
```

When upgrading an existing database, back it up and run `database/upgrade_to_xoghaye.sql`. It applies restrictive foreign-key deletion rules and is safe to rerun.

### Step 3: Project Placement
Ensure the project is inside XAMPP's `htdocs` directory:
- Path: `C:\xampp\htdocs\school_manager`

*(If your project is located on your Desktop, you can create a junction link in PowerShell as Administrator):*
```powershell
cmd /c 'mklink /J "C:\xampp\htdocs\school_manager" "C:\Users\HP\Desktop\SCHOOL MANAGER"'
```

### Step 4: Verify Database Connection (`config/database.php`)
Open `config/database.php`. The default credentials match standard XAMPP settings:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'campuspro_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Blank by default in XAMPP
```

### Step 5: Open the Application
Launch your browser and visit:
```
http://localhost/school_manager/
```
The application will automatically direct you to the secure login portal.

---

## 🔒 Security Features

- **Password Hashing:** Passwords are encrypted using PHP `password_hash()` with the `PASSWORD_BCRYPT` algorithm. Plaintext passwords are never stored.
- **SQL Injection Prevention:** 100% of database interactions utilize **PDO Prepared Statements** with bound parameters.
- **CSRF Protection:** Critical forms include cryptographic session-tied CSRF tokens validated via `hash_equals()`.
- **Session Security:** Cookie parameters are configured with `httponly` and session identifiers are regenerated upon authentication (`session_regenerate_id(true)`) to mitigate session fixation attacks.
- **Role-Based Authorization:** Strict server-side route guards enforce access boundaries (`require_role()`). Unauthorized access attempts are rejected and redirected.
- **Input Sanitization:** User submissions are sanitized and validated with HTML entity escaping (`htmlspecialchars()`).
- **Safe Record Deletion:** Administrative delete requests require CSRF validation, use prepared statements, and are checked against related academic records. Students, courses, faculty, departments, programs, academic years, semesters, and lecturers with linked records cannot be deleted until their dependencies are handled. Foreign-key restrictions prevent cascade deletion of enrollment, attendance, and grade history.

---

## 📂 Project Directory Structure

```
SCHOOL MANAGER/
├── config/
│   └── database.php         # PDO connection & dynamic URL configuration
├── includes/
│   ├── auth.php             # Authentication, session security & helper utilities
│   ├── header.php           # HTML5 head, Bootstrap 5.3 CSS, icons, layout opening
│   ├── navbar.php           # Top sticky navigation bar & user menu
│   ├── sidebar.php          # Dynamic, role-aware responsive sidebar
│   └── footer.php           # Global delete confirmation modal & JavaScript bundles
├── admin/
│   ├── dashboard.php        # Admin KPI dashboard & analytics
│   ├── students.php         # Student management table with search & deletion
│   ├── add_student.php      # Student registration (FR-02)
│   ├── edit_student.php     # Student record editor (FR-03)
│   ├── view_student.php     # Comprehensive 360° student academic record
│   ├── teachers.php         # Faculty & department management (CRUD)
│   ├── courses.php          # Course management & teacher assignment (CRUD)
│   ├── enrollments.php      # Class enrollment management
│   ├── attendance.php       # System-wide attendance review & audits
│   ├── grades.php           # Institution-wide gradebook & evaluation overview
│   └── users.php            # User account directory & role administration
├── teacher/
│   ├── dashboard.php        # Teacher dashboard (assigned courses, students)
│   ├── courses.php          # Assigned courses & class rosters
│   ├── attendance.php       # Roll call recording & attendance history (FR-04)
│   └── grades.php           # Student grade entry & assessment updates (FR-05)
├── student/
│   ├── dashboard.php        # Student personal dashboard (GPA, summary)
│   ├── profile.php          # Student profile & contact updater
│   ├── courses.php          # Enrolled courses & instructor information
│   ├── attendance.php       # Attendance history log
│   └── grades.php           # Official Academic Report Card & results
├── auth/
│   ├── login.php            # Secure login interface with demo credential pills
│   └── logout.php           # Session destruction & logout handler
├── assets/
│   ├── css/
│   │   └── style.css        # Modern university design, color palette, custom components
│   └── js/
│       └── main.js          # Interactive client scripts, live search, grade calculator
├── database/
│   └── campuspro_db.sql     # Full SQL schema, foreign keys & realistic seed data
├── profile.php              # Global account profile & password changer for all users
├── index.php                # Application gateway redirector
└── README.md                # System documentation & setup guide
```

---

## 🎓 Grading Reference Scale

| Score Range | Letter Grade | Grade Point (4.0) | Performance Level |
| :--- | :--- | :--- | :--- |
| **93.0% – 100.0%** | **A** | 4.0 | Excellent / Highest Honors |
| **90.0% – 92.9%** | **A-** | 3.7 | Superior |
| **87.0% – 89.9%** | **B+** | 3.3 | Very Good |
| **83.0% – 86.9%** | **B** | 3.0 | Good / Proficient |
| **80.0% – 82.9%** | **B-** | 2.7 | Above Average |
| **77.0% – 79.9%** | **C+** | 2.3 | Competent |
| **73.0% – 76.9%** | **C** | 2.0 | Average |
| **70.0% – 72.9%** | **C-** | 1.7 | Minimum Passing |
| **65.0% – 69.9%** | **D+** | 1.3 | Below Average |
| **60.0% – 64.9%** | **D** | 1.0 | Marginally Passing |
| **< 60.0%** | **F** | 0.0 | Failing |

---

Developed for **SCHOOL MANAGER – Smart Education, Better Management.**
Compatible with **XAMPP / PHP 8.0+ / MariaDB 10.4+ / MySQL 8.0+**.
