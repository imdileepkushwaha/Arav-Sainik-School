<?php
// admin/includes/header.php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/settings_helpers.php';

$page_title = $page_title ?? 'Dashboard';
$admin_name = htmlspecialchars(ucfirst($_SESSION['admin_username']));
$avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['admin_username']) . '&background=059669&color=fff&bold=true';
$schoolBrand = getSchoolProfile($pdo);
$brandLogoUrl = schoolSidebarLogoUrl($schoolBrand, 'admin');
$brandFaviconUrl = schoolBrandingUrl($schoolBrand['favicon'] ?? '', 'admin');
$brandTitle = $schoolBrand['name'] ?: 'EduDash';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($brandTitle); ?></title>
    <?php if ($brandFaviconUrl): ?><link rel="icon" href="<?php echo htmlspecialchars($brandFaviconUrl); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-breadcrumb">
                    <span class="breadcrumb-root">Admin</span>
                    <i class="fas fa-chevron-right"></i>
                    <span class="breadcrumb-current"><?php echo htmlspecialchars($page_title); ?></span>
                </div>
            </div>

            <div class="header-center">
                <div class="search-bar" id="globalSearchWrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="globalSearchInput" placeholder="Search students, teachers, pages..." autocomplete="off">
                    <kbd class="search-shortcut">Ctrl K</kbd>
                    <div class="global-search-dropdown" id="globalSearchDropdown" hidden></div>
                </div>
            </div>

            <div class="header-actions">
                <?php
                $dbBadgeOnline = ($db_active_profile ?? 'offline') === 'online';
                $dbBadgeMode = ucfirst($db_connection_mode ?? 'offline');
                ?>
                <span class="db-header-badge <?php echo $dbBadgeOnline ? 'is-online' : 'is-offline'; ?>" title="Database: <?php echo $dbBadgeOnline ? 'Online' : 'Offline'; ?> · Mode <?php echo htmlspecialchars($dbBadgeMode); ?>">
                    <i class="fas fa-database"></i>
                    <span><?php echo $dbBadgeOnline ? 'Online' : 'Offline'; ?></span>
                </span>
                <button type="button" class="header-icon-btn" aria-label="Notifications">
                    <i class="far fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                <button type="button" class="header-icon-btn" aria-label="Messages">
                    <i class="far fa-envelope"></i>
                </button>

                <div class="header-user" id="headerUserMenu">
                    <button type="button" class="header-user-trigger" id="headerUserTrigger" onclick="return toggleUserMenu(event)">
                        <img src="<?php echo $avatar_url; ?>" alt="<?php echo $admin_name; ?>">
                        <div class="header-user-info">
                            <span class="header-user-name"><?php echo $admin_name; ?></span>
                            <span class="header-user-role">Administrator</span>
                        </div>
                        <i class="fas fa-chevron-down header-user-chevron"></i>
                    </button>
                    <div class="header-user-dropdown">
                        <div class="dropdown-header">
                            <img src="<?php echo $avatar_url; ?>" alt="<?php echo $admin_name; ?>">
                            <div>
                                <strong><?php echo $admin_name; ?></strong>
                                <span>admin@schoolerp.com</span>
                            </div>
                        </div>
                        <ul class="dropdown-menu">
                            <li><a href="#"><i class="fas fa-user"></i> My Profile</a></li>
                            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                            <li><a href="../index.php"><i class="fas fa-globe"></i> View Website</a></li>
                        </ul>
                        <div class="dropdown-footer">
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <script>
        function toggleUserMenu(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }

            var menu = document.getElementById('headerUserMenu');
            if (!menu) return false;

            menu.classList.toggle('open');
            return false;
        }

        document.addEventListener('click', function (e) {
            var menu = document.getElementById('headerUserMenu');
            if (menu && !menu.contains(e.target)) {
                menu.classList.remove('open');
            }
            var searchWrap = document.getElementById('globalSearchWrap');
            if (searchWrap && !searchWrap.contains(e.target)) {
                var dd = document.getElementById('globalSearchDropdown');
                if (dd) dd.hidden = true;
            }
        });

        (function () {
            var input = document.getElementById('globalSearchInput');
            var dropdown = document.getElementById('globalSearchDropdown');
            if (!input || !dropdown) return;
            var timer = null;
            function render(items) {
                if (!items.length) {
                    dropdown.innerHTML = '<div class="global-search-empty">No results</div>';
                    dropdown.hidden = false;
                    return;
                }
                dropdown.innerHTML = items.map(function (it) {
                    return '<a class="global-search-item" href="' + it.url + '"><i class="fas ' + it.icon + '"></i><div><strong>' + it.title + '</strong><span>' + it.type + ' · ' + it.meta + '</span></div></a>';
                }).join('');
                dropdown.hidden = false;
            }
            input.addEventListener('input', function () {
                clearTimeout(timer);
                var q = input.value.trim();
                if (q.length < 2) { dropdown.hidden = true; return; }
                timer = setTimeout(function () {
                    fetch('search.php?q=' + encodeURIComponent(q))
                        .then(function (r) { return r.json(); })
                        .then(function (d) { render(d.results || []); })
                        .catch(function () {
                            dropdown.innerHTML = '<div class="global-search-empty">Search unavailable</div>';
                            dropdown.hidden = false;
                        });
                }, 250);
            });
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); input.focus(); }
            });
        })();
        </script>

        <div class="admin-content">
