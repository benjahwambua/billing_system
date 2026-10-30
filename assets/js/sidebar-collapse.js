(function () {
    'use strict';

    var scrollStorageKey = 'flexihub.sidebar.scrollTop';
    var restoreLockUntil = 0;

    function initSidebar() {
        var sidebar = document.getElementById('flexihubSidebar');
        if (!sidebar) return;

        // Disable smooth scrolling for this container: restoring an offset must be immediate.
        sidebar.style.scrollBehavior = 'auto';
        sidebar.style.overflowY = 'auto';
        sidebar.style.overflowAnchor = 'none';

        function readSavedPosition() {
            try {
                var value = sessionStorage.getItem(scrollStorageKey);
                if (value === null) value = localStorage.getItem(scrollStorageKey);
                var top = parseInt(value, 10);
                return Number.isFinite(top) && top >= 0 ? top : 0;
            } catch (error) {
                return 0;
            }
        }

        function saveScrollPosition() {
            if (Date.now() < restoreLockUntil) return;
            try {
                var top = String(sidebar.scrollTop);
                sessionStorage.setItem(scrollStorageKey, top);
                localStorage.setItem(scrollStorageKey, top);
            } catch (error) {}
        }

        function restoreScrollPosition() {
            var top = readSavedPosition();
            // Ignore scroll events caused by the browser creating the new page at scrollTop 0.
            restoreLockUntil = Date.now() + 900;

            function applySavedPosition() {
                var maxTop = Math.max(0, sidebar.scrollHeight - sidebar.clientHeight);
                sidebar.scrollTop = Math.min(top, maxTop);
                // Re-read the intended offset after content/layout changes and try again.
                if (sidebar.scrollTop !== Math.min(top, maxTop)) {
                    sidebar.scrollTop = top;
                }
            }

            applySavedPosition();
            requestAnimationFrame(function () {
                applySavedPosition();
                requestAnimationFrame(applySavedPosition);
            });
            [80, 220, 450, 800].forEach(function (delay) {
                window.setTimeout(applySavedPosition, delay);
            });
            window.setTimeout(function () {
                restoreLockUntil = 0;
                saveScrollPosition();
            }, 950);
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
                // Keep the clicked heading at the same viewport position even if the
                // section expands/collapses and changes the total menu height.
                var headingTop = heading.getBoundingClientRect().top;
                var nowOpen = section.classList.contains('is-collapsed');

                section.classList.toggle('is-collapsed', !nowOpen);
                section.classList.toggle('is-active-open', active && nowOpen);

                try { localStorage.setItem(key, nowOpen ? 'open' : 'closed'); } catch (error) {}

                requestAnimationFrame(function () {
                    var shift = heading.getBoundingClientRect().top - headingTop;
                    sidebar.scrollTop += shift;
                    saveScrollPosition();
                });
            }

            heading.addEventListener('click', toggle);
            heading.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });

        // Save before navigation and don't allow unload events to replace the saved
        // offset with the browser's temporary zero position.
        sidebar.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (link && sidebar.contains(link)) {
                try {
                    var top = String(sidebar.scrollTop);
                    sessionStorage.setItem(scrollStorageKey, top);
                    localStorage.setItem(scrollStorageKey, top);
                } catch (error) {}
            }
        }, true);

        sidebar.addEventListener('scroll', saveScrollPosition, { passive: true });
        window.addEventListener('pagehide', saveScrollPosition);
        window.addEventListener('beforeunload', saveScrollPosition);
        window.addEventListener('pageshow', restoreScrollPosition);
        window.addEventListener('load', restoreScrollPosition);
        restoreScrollPosition();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();