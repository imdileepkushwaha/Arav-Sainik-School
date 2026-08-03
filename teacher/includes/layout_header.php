<?php
// Expects: $page_title, $page_subtitle (optional)
$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — <?php echo htmlspecialchars($tp_school['name']); ?></title>
    <?php if (!empty($tp_favicon_url)): ?><link rel="icon" href="<?php echo htmlspecialchars($tp_favicon_url); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/teacher-portal.css">
</head>
<body class="tp-body">
<div class="tp-overlay" id="tpOverlay"></div>
<div class="tp-wrapper">
    <aside class="tp-sidebar" id="tpSidebar">
        <div class="tp-brand">
            <div class="tp-brand-icon<?php echo $tp_logo_url ? ' has-logo' : ''; ?>">
                <?php if ($tp_logo_url): ?>
                <img src="<?php echo htmlspecialchars($tp_logo_url); ?>" alt="<?php echo htmlspecialchars($tp_school['name']); ?>">
                <?php else: ?>
                <i class="fas fa-chalkboard-teacher"></i>
                <?php endif; ?>
            </div>
            <div>
                <h2><?php echo htmlspecialchars($tp_school['name']); ?></h2>
                <span>Teacher Portal</span>
            </div>
        </div>
        <div class="tp-user-card">
            <img src="<?php echo htmlspecialchars($tp_photo); ?>" alt="<?php echo $tp_name; ?>">
            <div>
                <strong><?php echo $tp_name; ?></strong>
                <small><?php echo $tp_emp_id; ?></small>
            </div>
        </div>
        <nav class="tp-nav">
            <p class="tp-nav-label">Main</p>
            <a href="dashboard.php" class="<?php echo tp_nav_active('dashboard'); ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="profile.php" class="<?php echo tp_nav_active('profile'); ?>"><i class="fas fa-user"></i> My Profile</a>
            <a href="timetable.php" class="<?php echo tp_nav_active('timetable'); ?>"><i class="fas fa-calendar-alt"></i> Timetable</a>
            <a href="notices.php" class="<?php echo tp_nav_active('notices'); ?>"><i class="fas fa-bullhorn"></i> Notices</a>
            <p class="tp-nav-label">Teaching</p>
            <a href="my-classes.php" class="<?php echo tp_nav_active('my-classes'); ?>"><i class="fas fa-users"></i> My Classes</a>
            <a href="attendance.php" class="<?php echo tp_nav_active('attendance'); ?>"><i class="far fa-calendar-check"></i> Mark Attendance</a>
            <a href="homework.php" class="<?php echo tp_nav_active('homework'); ?>"><i class="fas fa-book-open"></i> Homework</a>
            <p class="tp-nav-label">Account</p>
            <a href="leave.php" class="<?php echo tp_nav_active('leave'); ?>"><i class="fas fa-plane-departure"></i> Apply Leave</a>
            <a href="my-attendance.php" class="<?php echo tp_nav_active('my-attendance'); ?>"><i class="fas fa-user-check"></i> My Attendance</a>
            <a href="change-password.php" class="<?php echo tp_nav_active('change-password'); ?>"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <div class="tp-sidebar-footer">
            <a href="logout.php" class="tp-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    <div class="tp-main">
        <header class="tp-topbar">
            <div class="tp-header-left">
                <button type="button" class="tp-menu-toggle" id="tpMenuToggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="tp-header-breadcrumb">
                    <span class="tp-breadcrumb-root">Teacher</span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="tp-breadcrumb-current"><?php echo htmlspecialchars($page_title); ?></span>
                </div>
            </div>

            <div class="tp-header-center">
                <div class="tp-search-bar" id="tpSearchWrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="tpSearchInput" placeholder="Search portal pages..." autocomplete="off">
                    <kbd class="tp-search-shortcut">Ctrl K</kbd>
                    <div class="tp-search-dropdown" id="tpSearchDropdown" hidden></div>
                </div>
            </div>

            <div class="tp-header-actions">
                <a href="notices.php" class="tp-header-icon-btn" aria-label="Notices">
                    <i class="far fa-bell"></i>
                </a>
                <a href="leave.php" class="tp-header-icon-btn" aria-label="Leave">
                    <i class="far fa-calendar-minus"></i>
                </a>

                <div class="tp-header-user" id="tpHeaderUser">
                    <button type="button" class="tp-header-user-trigger" id="tpHeaderUserTrigger" onclick="return tpToggleUserMenu(event)">
                        <img src="<?php echo htmlspecialchars($tp_photo); ?>" alt="<?php echo $tp_name; ?>">
                        <div class="tp-header-user-info">
                            <span class="tp-header-user-name"><?php echo $tp_name; ?></span>
                            <span class="tp-header-user-role"><?php echo htmlspecialchars($teacher['subject'] ?? 'Teacher'); ?></span>
                        </div>
                        <i class="fas fa-chevron-down tp-header-user-chevron"></i>
                    </button>
                    <div class="tp-header-user-dropdown">
                        <div class="tp-dropdown-header">
                            <img src="<?php echo htmlspecialchars($tp_photo); ?>" alt="<?php echo $tp_name; ?>">
                            <div>
                                <strong><?php echo $tp_name; ?></strong>
                                <span><?php echo htmlspecialchars($teacher['email'] ?? $tp_emp_id); ?></span>
                            </div>
                        </div>
                        <ul class="tp-dropdown-menu">
                            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                            <li><a href="my-attendance.php"><i class="fas fa-user-check"></i> My Attendance</a></li>
                            <li><a href="change-password.php"><i class="fas fa-lock"></i> Change Password</a></li>
                        </ul>
                        <div class="tp-dropdown-footer">
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <main class="tp-content">
        <?php if ($tp_flash_success): ?><div class="tp-alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($tp_flash_success); ?></div><?php endif; ?>
        <?php if ($tp_flash_error): ?><div class="tp-alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($tp_flash_error); ?></div><?php endif; ?>
