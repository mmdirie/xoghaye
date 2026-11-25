<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Top Navigation Bar
// ==========================================================
$user = current_user();
$role_badge_class = 'badge-role-student';
$role_display = 'Student';
if ($user && $user['role'] === 'admin') {
    $role_badge_class = 'badge-role-admin';
    $role_display = 'Administrator';
} elseif ($user && $user['role'] === 'teacher') {
    $role_badge_class = 'badge-role-teacher';
    $role_display = 'Lecturer';
}
?>
<header class="top-navbar">
    <div class="d-flex align-items-center">
        <!-- Mobile Sidebar Toggle Button -->
        <button class="btn btn-light d-lg-none me-3 border" id="sidebarToggle" type="button" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>

        <h1 class="navbar-page-title">
            <?php echo htmlspecialchars($page_title ?? 'University Portal'); ?>
        </h1>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Current Date Pill -->
        <div class="d-none d-md-flex align-items-center text-muted small bg-light px-3 py-1 rounded-pill border">
            <i class="bi bi-calendar3 me-2 text-primary"></i>
            <span><?php echo date('l, M d, Y'); ?></span>
        </div>

        <?php if ($user): ?>
            <!-- User Dropdown Menu -->
            <div class="dropdown navbar-user-dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center py-1 px-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar-mini me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </span>
                    <span class="d-none d-sm-inline-block text-start me-1">
                        <span class="d-block fw-semibold text-dark lh-1" style="font-size: 0.85rem;"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-role-badge <?php echo $role_badge_class; ?> mt-1" style="font-size: 0.65rem;"><?php echo $role_display; ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="small text-muted text-truncate" style="max-width: 180px;"><?php echo htmlspecialchars($user['email']); ?></div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?php echo base_url('profile.php'); ?>">
                            <i class="bi bi-person me-2 text-primary"></i> My Profile & Security
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger" href="<?php echo base_url('auth/logout.php'); ?>">
                            <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</header>
