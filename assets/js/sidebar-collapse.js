(function () {
    'use strict';

    var scrollStorageKey = 'flexihub.sidebar.scrollTop';

    function initSidebar() {
        var sidebar = document.getElementById('flexihubSidebar');
        if (!sidebar) return;

        function saveScrollPosition() {
            try {
                sessionStorage.setItem(scrollStorageKey, String(sidebar.scrollTop));
            } catch (error) {}
        }

        function restoreScrollPosition() {
            try {
                var saved = sessionStorage.getItem(scrollStorageKey);
                if (saved === null) return;
                var top = parseInt(saved, 10);
                if (!Number.isFinite(top)) return;

                // Restore after CSS, fonts and expanded menu sections have settled.
                sidebar.scrollTop = top;
                requestAnimationFrame(function () {
                    sidebar.scrollTop = top;
                    requestAnimationFrame(function () {
                        sidebar.scrollTop = top;
                    });
                });
                window.setTimeout(function () {
                    sidebar.scrollTop = top;
                }, 100);
                window.setTimeout(function () {
                    sidebar.scrollTop = top;
                }, 350);
            } catch (error) {}
        }

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

            var saved = null;
            try { saved = localStorage.getItem(key); } catch (error) {}
            var open = saved === null ? active : saved === 'open';

            section.classList.toggle('is-collapsed', !open);
            section.classList.toggle('is-active-open', active && open);

            function toggle() {
                var nowOpen = section.classList.contains('is-collapsed');
                var currentTop = sidebar.scrollTop;

                section.classList.toggle('is-collapsed', !nowOpen);
                section.classList.toggle('is-active-open', active && nowOpen);

                try { localStorage.setItem(key, nowOpen ? 'open' : 'closed'); } catch (error) {}

                // Expanding a section changes layout; restore the exact prior scroll offset.
                sidebar.scrollTop = currentTop;
                requestAnimationFrame(function () { sidebar.scrollTop = currentTop; });
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

        // Capture the position before the browser starts navigating away.
        sidebar.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (link && sidebar.contains(link)) saveScrollPosition();
        }, true);

        sidebar.addEventListener('scroll', saveScrollPosition, { passive: true });
        window.addEventListener('pagehide', saveScrollPosition);
        window.addEventListener('beforeunload', saveScrollPosition);
        window.addEventListener('load', restoreScrollPosition);
        restoreScrollPosition();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();