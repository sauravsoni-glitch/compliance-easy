(function () {
    function qs(id) { return document.getElementById(id); }

    /**
     * @param {{ title?: string, message?: string, confirmText?: string, cancelText?: string, alert?: boolean }} opts
     * @returns {Promise<boolean>}
     */
    window.appConfirm = function (opts) {
        opts = opts || {};
        var isAlert = !!opts.alert;
        return new Promise(function (resolve) {
            var overlay = qs('app-confirm-overlay');
            var titleEl = qs('app-confirm-title');
            var msgEl = qs('app-confirm-message');
            var okBtn = qs('app-confirm-ok');
            var cancelBtn = qs('app-confirm-cancel');
            if (!overlay || !titleEl || !msgEl || !okBtn || !cancelBtn) {
                resolve(false);
                return;
            }
            titleEl.textContent = opts.title || (isAlert ? 'Notice' : 'Confirm');
            msgEl.textContent = opts.message || '';
            okBtn.textContent = opts.confirmText || (isAlert ? 'OK' : 'OK');
            cancelBtn.textContent = opts.cancelText || 'Cancel';
            cancelBtn.hidden = isAlert;

            var done = false;
            function finish(v) {
                if (done) return;
                done = true;
                overlay.hidden = true;
                overlay.setAttribute('aria-hidden', 'true');
                overlay.onclick = null;
                okBtn.onclick = null;
                cancelBtn.onclick = null;
                document.removeEventListener('keydown', onKey);
                resolve(v);
            }

            function onKey(e) {
                if (e.key === 'Escape') {
                    finish(isAlert ? true : false);
                }
            }

            okBtn.onclick = function () { finish(true); };
            cancelBtn.onclick = function () { finish(false); };
            if (!isAlert) {
                overlay.onclick = function (ev) {
                    if (ev.target === overlay) finish(false);
                };
            } else {
                overlay.onclick = null;
            }
            document.addEventListener('keydown', onKey);
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
            okBtn.focus();
        });
    };

    /** @param {string} message */
    window.appAlert = function (message, title) {
        return window.appConfirm({
            alert: true,
            title: title || 'Notice',
            message: message || '',
            confirmText: 'OK'
        }).then(function () {});
    };
})();

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

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        var msg = form.getAttribute('data-app-confirm');
        if (msg === null || msg === '') return;
        e.preventDefault();
        e.stopPropagation();
        window.appConfirm({ title: 'Confirm', message: msg }).then(function (ok) {
            if (ok) form.submit();
        });
    }, true);
});
