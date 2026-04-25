document.addEventListener('DOMContentLoaded', function() {
    // If previous navigation was a tab switch, suppress this page enter animation once.
    try {
        if (sessionStorage.getItem('ehf_skip_tab_enter_anim') === '1') {
            document.body.classList.add('skip-tab-enter-anim');
            sessionStorage.removeItem('ehf_skip_tab_enter_anim');
        }
    } catch (e) {}

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

    // Smooth section/tab transition when navigating via links.
    (function initSectionTransitions() {
        var body = document.body;
        if (!body) return;

        function shouldAnimateLink(link, evt) {
            if (!link || !link.getAttribute) return false;
            if (evt.defaultPrevented) return false;
            if (link.target && link.target.toLowerCase() === '_blank') return false;
            if (evt.metaKey || evt.ctrlKey || evt.shiftKey || evt.altKey) return false;
            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#') return false;
            if (/^mailto:|^tel:|^javascript:/i.test(href)) return false;

            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (e) {
                return false;
            }
            if (url.origin !== window.location.origin) return false;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash === window.location.hash) {
                return false;
            }
            return true;
        }

        document.addEventListener('click', function (evt) {
            var link = evt.target && evt.target.closest ? evt.target.closest('a') : null;
            if (!shouldAnimateLink(link, evt)) return;

            var isTabSwitch =
                link.classList.contains('tab') ||
                link.classList.contains('compliance-tab') ||
                link.classList.contains('doa-vtab') ||
                ((link.getAttribute('href') || '').indexOf('?tab=') !== -1);
            if (isTabSwitch) {
                try { sessionStorage.setItem('ehf_skip_tab_enter_anim', '1'); } catch (e) {}
                return; // let tab navigation proceed without transition-out
            }
            if (!link.classList.contains('nav-item')) return;

            evt.preventDefault();
            if (body.classList.contains('app-transition-out')) {
                window.location.href = link.href;
                return;
            }

            body.classList.add('app-transition-out');
            window.setTimeout(function () {
                window.location.href = link.href;
            }, 170);
        }, true);
    })();
});
