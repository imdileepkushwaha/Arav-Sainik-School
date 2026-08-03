(function () {
    if (window.__adminUiInit) return;
    window.__adminUiInit = true;

    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggleBtn = document.getElementById('sidebarToggle');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });

    function bindPasswordToggle(wrap) {
        var input = wrap.querySelector('input[type="password"], input[type="text"][data-password-field]');
        var btn = wrap.querySelector('.password-toggle');
        if (!input || !btn || btn.dataset.bound === '1') {
            return;
        }
        btn.dataset.bound = '1';
        btn.addEventListener('click', function () {
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                input.setAttribute('data-password-field', '1');
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                input.removeAttribute('data-password-field');
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    }

    function initPasswordToggles() {
        document.querySelectorAll('input[type="password"].form-input, input[type="password"].form-control').forEach(function (input) {
            var wrap = input.closest('.password-input-wrap');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.className = 'password-input-wrap';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'password-toggle';
                btn.setAttribute('aria-label', 'Toggle password visibility');
                btn.innerHTML = '<i class="fas fa-eye"></i>';
                wrap.appendChild(btn);
            } else if (!wrap.querySelector('.password-toggle')) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'password-toggle';
                btn.setAttribute('aria-label', 'Toggle password visibility');
                btn.innerHTML = '<i class="fas fa-eye"></i>';
                wrap.appendChild(btn);
            }
            bindPasswordToggle(wrap);
        });

        document.querySelectorAll('.password-input-wrap').forEach(bindPasswordToggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordToggles);
    } else {
        initPasswordToggles();
    }
})();
