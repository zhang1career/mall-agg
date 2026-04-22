(function () {
    'use strict';

    var THEME_KEY = 'mall-admin-theme';
    var DARK = 'dark';
    var LIGHT = 'light';
    var SIDEBAR_KEY = 'mall_admin_sidebar_collapsed';
    var SIDEBAR_EXPANDED = 256;
    var SIDEBAR_COLLAPSED = 64;

    function getStoredTheme() {
        try {
            return localStorage.getItem(THEME_KEY) || LIGHT;
        } catch (e) {
            return LIGHT;
        }
    }

    function setStoredTheme(v) {
        try {
            localStorage.setItem(THEME_KEY, v);
        } catch (e) {}
    }

    function applyTheme(theme) {
        var isDark = theme === DARK;
        document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        var cb = document.getElementById('theme-toggle');
        if (cb) {
            cb.checked = !isDark;
        }
    }

    function initTheme() {
        var initial = getStoredTheme();
        applyTheme(initial);
        var cb = document.getElementById('theme-toggle');
        if (cb) {
            cb.addEventListener('change', function () {
                var theme = this.checked ? LIGHT : DARK;
                setStoredTheme(theme);
                applyTheme(theme);
            });
        }
    }

    function initSidebar() {
        var sidebar = document.getElementById('console-sidebar');
        var handle = document.getElementById('sidebar-handle');
        var toggleBtn = document.getElementById('sidebar-toggle');
        var iconExpand = document.getElementById('sidebar-toggle-icon-expand');
        var iconCollapse = document.getElementById('sidebar-toggle-icon-collapse');

        if (!sidebar) {
            return;
        }

        function isCollapsed() {
            try {
                return localStorage.getItem(SIDEBAR_KEY) === '1';
            } catch (e) {
                return false;
            }
        }

        function setCollapsed(collapsed) {
            try {
                localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0');
            } catch (e) {}
        }

        function applyState() {
            var collapsed = isCollapsed();
            var footerShort = document.getElementById('sidebar-footer-short');
            if (collapsed) {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.style.setProperty('--sidebar-width', SIDEBAR_COLLAPSED + 'px');
                document.querySelectorAll('.sidebar-text').forEach(function (el) {
                    el.classList.add('hidden');
                });
                var logoText = document.getElementById('sidebar-logo-text');
                var logoIcon = document.getElementById('sidebar-logo-icon');
                if (logoText) {
                    logoText.classList.add('hidden');
                }
                if (logoIcon) {
                    logoIcon.classList.remove('hidden');
                }
                if (footerShort) {
                    footerShort.classList.remove('hidden');
                }
                if (iconExpand) {
                    iconExpand.classList.remove('hidden');
                }
                if (iconCollapse) {
                    iconCollapse.classList.add('hidden');
                }
                if (toggleBtn) {
                    toggleBtn.title = 'Expand';
                }
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.style.setProperty('--sidebar-width', SIDEBAR_EXPANDED + 'px');
                document.querySelectorAll('.sidebar-text').forEach(function (el) {
                    el.classList.remove('hidden');
                });
                var logoText2 = document.getElementById('sidebar-logo-text');
                var logoIcon2 = document.getElementById('sidebar-logo-icon');
                if (logoText2) {
                    logoText2.classList.remove('hidden');
                }
                if (logoIcon2) {
                    logoIcon2.classList.add('hidden');
                }
                if (footerShort) {
                    footerShort.classList.add('hidden');
                }
                if (iconExpand) {
                    iconExpand.classList.add('hidden');
                }
                if (iconCollapse) {
                    iconCollapse.classList.remove('hidden');
                }
                if (toggleBtn) {
                    toggleBtn.title = 'Collapse';
                }
            }
        }

        function toggle() {
            setCollapsed(!isCollapsed());
            applyState();
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggle);
        }

        var dragStartX = 0;
        var dragStartW = 0;

        function onDrag(e) {
            var delta = e.clientX - dragStartX;
            var newW = Math.max(SIDEBAR_COLLAPSED, Math.min(SIDEBAR_EXPANDED, dragStartW + delta));
            if (newW <= SIDEBAR_COLLAPSED + 20) {
                setCollapsed(true);
                applyState();
            } else if (newW >= SIDEBAR_EXPANDED - 20) {
                setCollapsed(false);
                applyState();
            }
        }

        function onDragEnd() {
            document.removeEventListener('mousemove', onDrag);
            document.removeEventListener('mouseup', onDragEnd);
        }

        if (handle) {
            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                dragStartX = e.clientX;
                dragStartW = isCollapsed() ? SIDEBAR_COLLAPSED : SIDEBAR_EXPANDED;
                document.addEventListener('mousemove', onDrag);
                document.addEventListener('mouseup', onDragEnd);
            });
        }

        applyState();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initSidebar();
    });
})();
