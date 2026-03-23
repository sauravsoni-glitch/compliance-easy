document.addEventListener('DOMContentLoaded', function() {
    var sidebarToggle = document.querySelector('.sidebar-toggle');
    var sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
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
