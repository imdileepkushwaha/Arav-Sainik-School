        </div>

        <footer class="sp-footer">
            <div class="sp-footer-left">
                <p>&copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($school['name']); ?></strong>. All rights reserved.</p>
            </div>
            <div class="sp-footer-right">
                <a href="profile.php">My Profile</a>
                <a href="change-password.php">Security</a>
                <a href="notices.php">Notices</a>
                <a href="logout.php">Logout</a>
            </div>
        </footer>
    </div>
</div>
<script>
function spToggleUserMenu(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    var menu = document.getElementById('spHeaderUser');
    if (!menu) return false;
    menu.classList.toggle('open');
    return false;
}

(function () {
    var toggle = document.getElementById('spMenuToggle');
    var sidebar = document.getElementById('spSidebar');
    var overlay = document.getElementById('spOverlay');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('active');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    document.addEventListener('click', function (e) {
        var menu = document.getElementById('spHeaderUser');
        if (menu && !menu.contains(e.target)) {
            menu.classList.remove('open');
        }
        var searchWrap = document.getElementById('spSearchWrap');
        if (searchWrap && !searchWrap.contains(e.target)) {
            var dd = document.getElementById('spSearchDropdown');
            if (dd) dd.hidden = true;
        }
    });

    var input = document.getElementById('spSearchInput');
    var dropdown = document.getElementById('spSearchDropdown');
    if (input && dropdown) {
        var pages = [
            { title: 'Dashboard', url: 'dashboard.php', type: 'Page', icon: 'fa-home' },
            { title: 'My Profile', url: 'profile.php', type: 'Page', icon: 'fa-user' },
            { title: 'Notices', url: 'notices.php', type: 'Page', icon: 'fa-bullhorn' },
            { title: 'Attendance', url: 'attendance.php', type: 'Page', icon: 'fa-calendar-check' },
            { title: 'Homework', url: 'homework.php', type: 'Page', icon: 'fa-book-open' },
            { title: 'Exam Results', url: 'results.php', type: 'Page', icon: 'fa-chart-line' },
            { title: 'Timetable', url: 'timetable.php', type: 'Page', icon: 'fa-table' },
            { title: 'Fees', url: 'fees.php', type: 'Page', icon: 'fa-file-invoice-dollar' },
            { title: 'Certificates', url: 'certificates.php', type: 'Page', icon: 'fa-certificate' },
            { title: 'Documents', url: 'documents.php', type: 'Page', icon: 'fa-folder-open' },
            { title: 'Change Password', url: 'change-password.php', type: 'Page', icon: 'fa-lock' }
        ];

        function render(items) {
            if (!items.length) {
                dropdown.innerHTML = '<div class="sp-search-empty">No results</div>';
                dropdown.hidden = false;
                return;
            }
            dropdown.innerHTML = items.map(function (it) {
                return '<a class="sp-search-item" href="' + it.url + '"><i class="fas ' + it.icon + '"></i><div><strong>' + it.title + '</strong><span>' + it.type + '</span></div></a>';
            }).join('');
            dropdown.hidden = false;
        }

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            if (q.length < 1) {
                dropdown.hidden = true;
                return;
            }
            render(pages.filter(function (p) {
                return p.title.toLowerCase().indexOf(q) !== -1;
            }));
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 1) {
                input.dispatchEvent(new Event('input'));
            }
        });

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                input.focus();
            }
        });
    }
})();
</script>
</body>
</html>
