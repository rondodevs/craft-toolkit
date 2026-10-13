(function() {
    if (typeof Craft === 'undefined') {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // the new token replaces the saved one: Cloudflare cannot edit an existing token from a link
    function tokenLink(url, label) {
        return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(label) + ' ↗</a>';
    }

    function bindAction(btn, status, action, pendingLabel, onDone) {
        if (!btn || !status) {
            return;
        }

        btn.addEventListener('click', function() {
            var question = btn.getAttribute('data-confirm');

            if (question && !window.confirm(question)) {
                return;
            }

            btn.setAttribute('disabled', 'disabled');
            status.innerHTML = 'Status: <strong>' + pendingLabel + '</strong>';

            Craft.postActionRequest(action, {}, function(response, textStatus) {
                var ok = textStatus === 'success';
                var message = response && response.message ? response.message : (ok ? 'ok' : 'failed');
                status.innerHTML = 'Status: <strong' + (ok ? '' : ' class="error"') + '>' + escapeHtml(message) + '</strong>';
                btn.removeAttribute('disabled');

                if (onDone) {
                    onDone(response, ok);
                }
            });
        });
    }

    var checkBtn = document.querySelector('.cloudflare-check-btn');
    var checkResults = document.getElementById('cloudflare-check-results');
    var createRuleBtn = document.querySelector('.cloudflare-create-rule-btn');
    // Craft's status dots
    var statusClasses = {ok: 'green', warn: 'orange', fail: 'red', skip: 'disabled'};

    function renderChecks(response, ok) {
        var checks = ok && response && Array.isArray(response.checks) ? response.checks : [];

        checkResults.innerHTML = checks.map(function(check) {
            return '<li><span class="status ' + (statusClasses[check.status] || 'disabled') + '"></span>'
                + '<strong>' + escapeHtml(check.label) + '</strong>: ' + escapeHtml(check.message)
                + (check.link ? ' ' + tokenLink(check.link.url, check.link.label) : '') + '</li>';
        }).join('');
        checkResults.classList.toggle('hidden', checks.length === 0);

        var ruleAction = ok && response ? response.ruleAction : null;
        var ruleName = createRuleBtn.getAttribute('data-rule-name');

        // with a rule already on the zone the button rewrites it, never adds a second one
        createRuleBtn.textContent = ruleAction === 'fix' ? 'Fix Cache Rule' : 'Create Cache Rule';
        createRuleBtn.setAttribute('data-confirm', ruleAction === 'fix'
            ? 'Overwrite the existing Cache Rule with the "' + ruleName + '" settings?'
            : 'Create the Cache Rule "' + ruleName + '" on the Cloudflare zone?');
        createRuleBtn.classList.toggle('hidden', !ruleAction);
    }

    if (checkResults && createRuleBtn) {
        bindAction(checkBtn, document.getElementById('cloudflare-check-status'), 'toolkit/cloudflare/check-edge-cache', 'checking...', renderChecks);
        // checks again once the rule is written, so the list shows the new state
        bindAction(createRuleBtn, document.getElementById('cloudflare-check-status'), 'toolkit/cloudflare/create-cache-rule', 'creating the rule...', function(response, ok) {
            if (ok && checkBtn) {
                checkBtn.click();
            }
        });
    }

    function openModal(title) {
        var overlay = document.createElement('div');
        overlay.className = 'toolkit-modal-overlay';
        overlay.innerHTML = '<div class="toolkit-modal" role="dialog" aria-modal="true">'
            + '<div class="toolkit-modal-header"><h3>' + escapeHtml(title) + '</h3><button type="button" class="btn">Close</button></div>'
            + '<div class="toolkit-modal-body"></div></div>';
        document.body.appendChild(overlay);

        function close() {
            overlay.remove();
            document.removeEventListener('keydown', onKeydown);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
            }
        }

        overlay.querySelector('.toolkit-modal-header .btn').addEventListener('click', close);
        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeydown);

        return overlay.querySelector('.toolkit-modal-body');
    }

    function renderRule(rule) {
        var badges = '';

        if (rule.isOurs) {
            badges += ' <span class="cloudflare-rule-badge">used by the check</span>';
        }

        if (rule.overridesOurs) {
            badges += ' <span class="cloudflare-rule-badge warning">applies after it, can override it</span>';
        }

        var settings = Object.keys(rule.settings).map(function(label) {
            return '<dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(rule.settings[label]) + '</dd>';
        }).join('');

        var problems = rule.problems.length
            ? '<ul class="cloudflare-rule-problems">' + rule.problems.map(function(problem) {
                return '<li class="error">' + escapeHtml(problem) + '</li>';
            }).join('') + '</ul>'
            : '';

        return '<li class="cloudflare-rule' + (rule.isOurs ? ' is-ours' : '') + '">'
            + '<div class="cloudflare-rule-title"><span class="status ' + (rule.enabled ? 'green' : 'disabled') + '" title="' + (rule.enabled ? 'Enabled' : 'Disabled') + '"></span>'
            + '<strong>' + rule.position + '. ' + escapeHtml(rule.description || '(no name)') + '</strong>' + badges + '</div>'
            + '<dl class="cloudflare-rule-settings">' + settings + '</dl>'
            + '<pre class="code cloudflare-rule-expression"><code>' + escapeHtml(rule.expression) + '</code></pre>'
            + problems
            + '</li>';
    }

    var rulesBtn = document.querySelector('.cloudflare-rules-btn');

    if (rulesBtn) {
        rulesBtn.addEventListener('click', function() {
            var body = openModal('Cloudflare Cache Rules');
            rulesBtn.setAttribute('disabled', 'disabled');
            body.innerHTML = '<p class="light">Loading…</p>';

            Craft.postActionRequest('toolkit/cloudflare/list-cache-rules', {}, function(response, textStatus) {
                rulesBtn.removeAttribute('disabled');

                if (textStatus !== 'success') {
                    body.innerHTML = '<p class="error">' + escapeHtml(response && response.message ? response.message : 'Could not read the Cache Rules.') + '</p>'
                        + (response && response.tokenUrl ? '<p>' + tokenLink(response.tokenUrl, 'Create token on Cloudflare') + '</p>' : '');
                    return;
                }

                var intro = '<p class="light">In the order Cloudflare applies them: every rule that matches a request applies, and for the same setting the last one wins.'
                    + (response.host ? ' Frontend host: <code>' + escapeHtml(response.host) + '</code>.' : '') + '</p>';

                body.innerHTML = intro + (response.rules.length
                    ? '<ol class="cloudflare-rules">' + response.rules.map(renderRule).join('') + '</ol>'
                    : '<p>The zone has no Cache Rules.</p>');
            });
        });
    }

    bindAction(document.querySelector('.cloudflare-verify-btn'), document.getElementById('cloudflare-status'), 'toolkit/cloudflare/verify', 'checking...');
    bindAction(document.querySelector('.cloudflare-purge-btn'), document.getElementById('cloudflare-purge-status'), 'toolkit/cloudflare/purge-everything', 'purging...');

    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('.cloudflare-tab'));
    var tabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));

    function activateTab(tabName) {
        tabButtons.forEach(function(tabButton) {
            var isActive = tabButton.getAttribute('data-tab') === tabName;
            tabButton.classList.toggle('active', isActive);
            tabButton.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tabButton.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        tabPanels.forEach(function(panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-tab-panel') !== tabName);
        });
    }

    tabButtons.forEach(function(tabButton) {
        tabButton.addEventListener('click', function() {
            activateTab(tabButton.getAttribute('data-tab'));
        });
    });
})();
