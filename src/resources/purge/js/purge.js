(function() {
    if (typeof Craft === 'undefined') {
        return;
    }

    var btns = Array.prototype.slice.call(document.querySelectorAll('.purge-btn'));
    var status = document.getElementById('purge-status');

    if (btns.length === 0 || !status) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var question = btn.getAttribute('data-confirm');

            if (question && !window.confirm(question)) {
                return;
            }

            // one purge at a time: they would race on the same caches
            btns.forEach(function(other) { other.setAttribute('disabled', 'disabled'); });
            status.innerHTML = 'Status: <strong>purging...</strong>';

            Craft.postActionRequest(btn.getAttribute('data-action'), {}, function(response, textStatus) {
                var ok = textStatus === 'success';
                var message = response && response.message ? response.message : (ok ? 'done' : 'failed');
                status.innerHTML = 'Status: <strong' + (ok ? '' : ' class="error"') + '>' + escapeHtml(message) + '</strong>';
                btns.forEach(function(other) { other.removeAttribute('disabled'); });
            });
        });
    });
})();
