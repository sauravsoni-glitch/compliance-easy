document.addEventListener('DOMContentLoaded', function() {
    var sidebarToggle = document.querySelector('.sidebar-toggle');
    var sidebar = document.querySelector('.sidebar');
    var LS_KEY = 'ehf_sidebar_collapsed';
    if (sidebarToggle && sidebar) {
        document.querySelectorAll('.sidebar-nav a.nav-item').forEach(function (a) {
            var s = a.querySelector('span');
            if (s && !a.getAttribute('aria-label')) {
                a.setAttribute('aria-label', s.textContent.trim());
            }
        });
        function applySidebarCollapsedUi() {
            var collapsed = sidebar.classList.contains('collapsed');
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }
        try {
            if (localStorage.getItem(LS_KEY) === '1') {
                sidebar.classList.add('collapsed');
            }
        } catch (e) { /* private mode */ }
        applySidebarCollapsedUi();
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('collapsed');
            try {
                localStorage.setItem(LS_KEY, sidebar.classList.contains('collapsed') ? '1' : '0');
            } catch (e2) { /* private mode */ }
            applySidebarCollapsedUi();
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.header-dropdown-wrap[aria-expanded="true"]').forEach(function(wrap) {
            wrap.setAttribute('aria-expanded', 'false');
            var panel = wrap.querySelector('.header-dropdown-panel');
            if (panel) panel.setAttribute('aria-hidden', 'true');
        });
    }
    document.getElementById('btn-notifications') && document.getElementById('btn-notifications').addEventListener('click', function(e) {
        e.stopPropagation();
        var wrap = this.closest('.header-dropdown-wrap');
        var panel = document.getElementById('panel-notifications');
        var isOpen = wrap.getAttribute('aria-expanded') === 'true';
        closeAllDropdowns();
        if (!isOpen) {
            wrap.setAttribute('aria-expanded', 'true');
            if (panel) panel.setAttribute('aria-hidden', 'false');
        }
    });
    document.getElementById('btn-user-menu') && document.getElementById('btn-user-menu').addEventListener('click', function(e) {
        e.stopPropagation();
        var wrap = this.closest('.header-dropdown-wrap');
        var panel = document.getElementById('panel-user-menu');
        var isOpen = wrap.getAttribute('aria-expanded') === 'true';
        closeAllDropdowns();
        if (!isOpen) {
            wrap.setAttribute('aria-expanded', 'true');
            if (panel) panel.setAttribute('aria-hidden', 'false');
        }
    });
    document.addEventListener('click', closeAllDropdowns);
});
