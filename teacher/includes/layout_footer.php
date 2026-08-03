        </main>

        <footer class="tp-footer">
            <div class="tp-footer-left">
                <p>&copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($tp_school['name']); ?></strong>. All rights reserved.</p>
            </div>
            <div class="tp-footer-right">
                <a href="profile.php">My Profile</a>
                <a href="change-password.php">Security</a>
                <a href="notices.php">Notices</a>
                <a href="logout.php">Logout</a>
            </div>
        </footer>
    </div>
</div>
<script>
function tpToggleUserMenu(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    var menu = document.getElementById('tpHeaderUser');
    if (!menu) return false;
    menu.classList.toggle('open');
    return false;
}

(function () {
    var toggle = document.getElementById('tpMenuToggle');
    var sidebar = document.getElementById('tpSidebar');
    var overlay = document.getElementById('tpOverlay');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    document.addEventListener('click', function (e) {
        var menu = document.getElementById('tpHeaderUser');
        if (menu && !menu.contains(e.target)) {
            menu.classList.remove('open');
        }
        var searchWrap = document.getElementById('tpSearchWrap');
        if (searchWrap && !searchWrap.contains(e.target)) {
            var dd = document.getElementById('tpSearchDropdown');
            if (dd) dd.hidden = true;
        }
    });

    var input = document.getElementById('tpSearchInput');
    var dropdown = document.getElementById('tpSearchDropdown');
    if (input && dropdown) {
        var pages = [
            { title: 'Dashboard', url: 'dashboard.php', type: 'Page', icon: 'fa-home' },
            { title: 'My Profile', url: 'profile.php', type: 'Page', icon: 'fa-user' },
            { title: 'Timetable', url: 'timetable.php', type: 'Page', icon: 'fa-calendar-alt' },
            { title: 'Notices', url: 'notices.php', type: 'Page', icon: 'fa-bullhorn' },
            { title: 'My Classes', url: 'my-classes.php', type: 'Page', icon: 'fa-users' },
            { title: 'Mark Attendance', url: 'attendance.php', type: 'Page', icon: 'fa-calendar-check' },
            { title: 'Homework', url: 'homework.php', type: 'Page', icon: 'fa-book-open' },
            { title: 'Apply Leave', url: 'leave.php', type: 'Page', icon: 'fa-plane-departure' },
            { title: 'My Attendance', url: 'my-attendance.php', type: 'Page', icon: 'fa-user-check' },
            { title: 'Change Password', url: 'change-password.php', type: 'Page', icon: 'fa-lock' }
        ];

        function render(items) {
            if (!items.length) {
                dropdown.innerHTML = '<div class="tp-search-empty">No results</div>';
                dropdown.hidden = false;
                return;
            }
            dropdown.innerHTML = items.map(function (it) {
                return '<a class="tp-search-item" href="' + it.url + '"><i class="fas ' + it.icon + '"></i><div><strong>' + it.title + '</strong><span>' + it.type + '</span></div></a>';
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
