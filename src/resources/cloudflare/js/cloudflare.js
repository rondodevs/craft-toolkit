(function() {
    var verifyBtn = document.querySelector('.cloudflare-verify-btn');
    var status = document.getElementById('cloudflare-status');

    if (!verifyBtn || !status || typeof Craft === 'undefined') {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    verifyBtn.addEventListener('click', function() {
        verifyBtn.setAttribute('disabled', 'disabled');
        status.innerHTML = 'Status: <strong>checking...</strong>';

        Craft.postActionRequest('toolkit/cloudflare/verify', {}, function(response, textStatus) {
            var ok = textStatus === 'success';
            var message = response && response.message ? response.message : (ok ? 'ok' : 'failed');
            status.innerHTML = 'Status: <strong' + (ok ? '' : ' class="error"') + '>' + escapeHtml(message) + '</strong>';
            verifyBtn.removeAttribute('disabled');
        });
    });
})();
