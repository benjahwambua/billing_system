(function () {
    'use strict';

    var scrollStorageKey = 'flexihub.sidebar.scrollTop';

    function initSidebar() {
        var sidebar = document.getElementById('flexihubSidebar');
        if (!sidebar) return;

        var sections = Array.from(sidebar.querySelectorAll('.sidebar-menu-section'));

        sections.forEach(function (section, index) {
            var heading = section.querySelector('.sidebar-menu-heading');
            if (!heading) return;

            heading.setAttribute('role', 'button');
            heading.setAttribute('tabindex', '0');

            var key = 'flexihub.sidebar.section.' + index;
            var links = Array.from(section.querySelectorAll('.sidebar-menu-link'));
            var active = links.some(function (link) {
                return link.classList.contains('active');
            });
            var saved = localStorage.getItem(key);
            var open = saved === null ? active : saved === 'open';

            section.classList.toggle('is-collapsed', !open);
            section.classList.toggle('is-active-open', active && open);

            function toggle() {
                var nowOpen = section.classList.contains('is-collapsed');
                section.classList.toggle('is-collapsed', !nowOpen);
                section.classList.toggle('is-active-open', active && nowOpen);
                localStorage.setItem(key, nowOpen ? 'open' : 'closed');

                // Keep the sidebar's current position when a section expands/collapses.
                saveScrollPosition();
            }

            heading.addEventListener('click', toggle);
            heading.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });

        function saveScrollPosition() {
            try {
                sessionStorage.setItem(scrollStorageKey, String(sidebar.scrollTop));
            } catch (error) {
                // Navigation remains usable if browser storage is unavailable.
            }
        }

        // Restore after the saved section states have been applied.
        try {
            var savedScrollTop = sessionStorage.getItem(scrollStorageKey);
            if (savedScrollTop !== null) {
                requestAnimationFrame(function () {
                    sidebar.scrollTop = parseInt(savedScrollTop, 10) || 0;
                    requestAnimationFrame(function () {
                        sidebar.scrollTop = parseInt(savedScrollTop, 10) || 0;
                    });
                });
            }
        } catch (error) {
            // Ignore storage restrictions without interrupting navigation.
        }

        // Save before following a menu link, including normal full-page navigation.
        sidebar.addEventListener('click', function (event) {
            var link = event.target.closest('a.sidebar-menu-link, a.sidebar-brand-link');
            if (link) saveScrollPosition();
        });

        window.addEventListener('pagehide', saveScrollPosition);
        window.addEventListener('beforeunload', saveScrollPosition);
        sidebar.addEventListener('scroll', saveScrollPosition, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();