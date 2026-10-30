(function() {
    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('.streaming-video-tab'));
    var tabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-streaming-video-panel]'));

    if (tabButtons.length === 0) {
        return;
    }

    function activateTab(tabName) {
        tabButtons.forEach(function(tabButton) {
            var isActive = tabButton.getAttribute('data-tab') === tabName;
            tabButton.classList.toggle('active', isActive);
            tabButton.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tabButton.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        tabPanels.forEach(function(panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-streaming-video-panel') !== tabName);
        });
    }

    tabButtons.forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            var tabName = tabButton.getAttribute('data-tab');
            activateTab(tabName);
            history.replaceState(null, '', '#' + tabName);
        });
    });

    // Reopens the same tab after a form post redirects back here
    var initialTab = window.location.hash.replace('#', '');
    if (tabButtons.some(function(tabButton) { return tabButton.getAttribute('data-tab') === initialTab; })) {
        activateTab(initialTab);
    }
})();
