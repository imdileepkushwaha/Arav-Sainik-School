<?php
$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — <?php echo htmlspecialchars($school['name']); ?></title>
    <?php if (!empty($sp_favicon_url)): ?><link rel="icon" href="<?php echo htmlspecialchars($sp_favicon_url); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/student-portal.css">
</head>
<body class="sp-body">
<div class="sp-overlay" id="spOverlay"></div>
<div class="sp-wrapper">
    <aside class="sp-sidebar" id="spSidebar">
        <div class="sp-brand">
            <div class="sp-brand-icon<?php echo $sp_logo_url ? ' has-logo' : ''; ?>">
                <?php if ($sp_logo_url): ?>
                <img src="<?php echo htmlspecialchars($sp_logo_url); ?>" alt="<?php echo htmlspecialchars($school['name']); ?>">
                <?php else: ?>
                <i class="fas fa-graduation-cap"></i>
                <?php endif; ?>
            </div>
            <div><h2><?php echo htmlspecialchars($school['name']); ?></h2><span>Student Portal</span></div>
        </div>
        <div class="sp-user-card">
            <img src="<?php echo htmlspecialchars($sp_photo); ?>" alt="<?php echo $sp_name; ?>">
            <div><strong><?php echo $sp_name; ?></strong><small><?php echo $sp_ad_no; ?></small></div>
        </div>
        <nav class="sp-nav">
            <p class="sp-nav-label">Main</p>
            <a href="dashboard.php" class="<?php echo sp_nav_active('dashboard'); ?>"><i class="fas fa-home"></i> Dashboard</a>
            <a href="profile.php" class="<?php echo sp_nav_active('profile'); ?>"><i class="fas fa-user"></i> My Profile</a>
            <a href="notices.php" class="<?php echo sp_nav_active('notices'); ?>"><i class="fas fa-bullhorn"></i> Notices</a>
            <p class="sp-nav-label">Academics</p>
            <a href="attendance.php" class="<?php echo sp_nav_active('attendance'); ?>"><i class="far fa-calendar-check"></i> Attendance</a>
            <a href="homework.php" class="<?php echo sp_nav_active('homework'); ?>"><i class="fas fa-book-open"></i> Homework</a>
            <a href="results.php" class="<?php echo sp_nav_active('results'); ?>"><i class="fas fa-chart-line"></i> Exam Results</a>
            <a href="timetable.php" class="<?php echo sp_nav_active('timetable'); ?>"><i class="fas fa-table"></i> Timetable</a>
            <p class="sp-nav-label">Finance &amp; Records</p>
            <a href="fees.php" class="<?php echo sp_nav_active('fees'); ?>"><i class="fas fa-file-invoice-dollar"></i> Fees</a>
            <a href="certificates.php" class="<?php echo sp_nav_active('certificates'); ?>"><i class="fas fa-certificate"></i> Certificates</a>
            <a href="documents.php" class="<?php echo sp_nav_active('documents'); ?>"><i class="fas fa-folder-open"></i> Documents</a>
            <p class="sp-nav-label">Account</p>
            <a href="change-password.php" class="<?php echo sp_nav_active('change-password'); ?>"><i class="fas fa-lock"></i> Change Password</a>
        </nav>
        <div class="sp-sidebar-footer"><a href="logout.php" class="sp-logout"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>
    <div class="sp-main">
        <header class="sp-topbar">
            <div class="sp-header-left">
                <button type="button" class="sp-menu-toggle" id="spMenuToggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="sp-header-breadcrumb">
                    <span class="sp-breadcrumb-root">Student</span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="sp-breadcrumb-current"><?php echo htmlspecialchars($page_title); ?></span>
                </div>
            </div>

            <div class="sp-header-center">
                <div class="sp-search-bar" id="spSearchWrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="spSearchInput" placeholder="Search portal pages..." autocomplete="off">
                    <kbd class="sp-search-shortcut">Ctrl K</kbd>
                    <div class="sp-search-dropdown" id="spSearchDropdown" hidden></div>
                </div>
            </div>

            <div class="sp-header-actions">
                <a href="notices.php" class="sp-header-icon-btn" aria-label="Notices">
                    <i class="far fa-bell"></i>
                </a>
                <a href="fees.php" class="sp-header-icon-btn" aria-label="Fees">
                    <i class="fas fa-file-invoice-dollar"></i>
                </a>

                <div class="sp-header-user" id="spHeaderUser">
                    <button type="button" class="sp-header-user-trigger" id="spHeaderUserTrigger" onclick="return spToggleUserMenu(event)">
                        <img src="<?php echo htmlspecialchars($sp_photo); ?>" alt="<?php echo $sp_name; ?>">
                        <div class="sp-header-user-info">
                            <span class="sp-header-user-name"><?php echo $sp_name; ?></span>
                            <span class="sp-header-user-role">Class <?php echo htmlspecialchars($student['class'] ?? ''); ?><?php echo !empty($student['section']) ? ' · ' . htmlspecialchars($student['section']) : ''; ?></span>
                        </div>
                        <i class="fas fa-chevron-down sp-header-user-chevron"></i>
                    </button>
                    <div class="sp-header-user-dropdown">
                        <div class="sp-dropdown-header">
                            <img src="<?php echo htmlspecialchars($sp_photo); ?>" alt="<?php echo $sp_name; ?>">
                            <div>
                                <strong><?php echo $sp_name; ?></strong>
                                <span><?php echo $sp_ad_no; ?></span>
                            </div>
                        </div>
                        <ul class="sp-dropdown-menu">
                            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                            <li><a href="fees.php"><i class="fas fa-file-invoice-dollar"></i> Fees</a></li>
                            <li><a href="change-password.php"><i class="fas fa-lock"></i> Change Password</a></li>
                        </ul>
                        <div class="sp-dropdown-footer">
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <div class="sp-content">
