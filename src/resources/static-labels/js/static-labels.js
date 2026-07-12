(function() {
    function activateTab(siteHandle) {
        var activeTab = null;

        document.querySelectorAll('.static-labels-tab').forEach(function(tab) {
            var isActive = tab.getAttribute('data-site-handle') === siteHandle;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                activeTab = tab;
            }
        });

        document.querySelectorAll('.static-labels-site').forEach(function(panel) {
            var isActive = panel.getAttribute('data-site-handle') === siteHandle;
            panel.classList.toggle('hidden', !isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });

        if (activeTab) {
            var siteTitle = document.querySelector('.static-labels-site-title');
            var siteUpdated = document.getElementById('static-labels-site-updated');
            var siteName = activeTab.getAttribute('data-site-name') || siteHandle;
            var siteHandleLabel = activeTab.getAttribute('data-site-handle') || siteHandle;
            var updated = activeTab.getAttribute('data-site-updated') || 'never';

            if (siteTitle) {
                siteTitle.innerHTML = Craft.escapeHtml(siteName) + ' <span class="light">(' + Craft.escapeHtml(siteHandleLabel) + ')</span>';
            }

            if (siteUpdated) {
                siteUpdated.textContent = updated;
            }
        }
    }

    function sanitizeKeyValue(value) {
        return value.replace(/\s+/g, '');
    }

    function applySearch(siteContainer) {
        if (!siteContainer) {
            return;
        }

        var searchInput = siteContainer.querySelector('.static-labels-search');
        var query = searchInput ? searchInput.value.trim().toLowerCase() : '';

        siteContainer.querySelectorAll('.static-label-row').forEach(function(row) {
            var keyInput = row.querySelector('[data-role="label-key"]');
            var valueInput = row.querySelector('[data-role="label-single-mode"]');
            var oneInput = row.querySelector('[data-role="label-one"]');
            var zeroInput = row.querySelector('[data-role="label-zero"]');
            var manyInput = row.querySelector('[data-role="label-many"]');
            var keyText = keyInput ? keyInput.value.toLowerCase() : '';
            var valueText = valueInput ? valueInput.value.toLowerCase() : '';
            var oneText = oneInput ? oneInput.value.toLowerCase() : '';
            var zeroText = zeroInput ? zeroInput.value.toLowerCase() : '';
            var manyText = manyInput ? manyInput.value.toLowerCase() : '';
            var visible = query === '' || keyText.indexOf(query) !== -1 || valueText.indexOf(query) !== -1 || oneText.indexOf(query) !== -1 || zeroText.indexOf(query) !== -1 || manyText.indexOf(query) !== -1;
            row.style.display = visible ? '' : 'none';
        });
    }

    function updateRowMode(row) {
        if (!row) {
            return;
        }

        var modeInput = row.querySelector('[data-role="label-mode"]');
        var mode = modeInput ? modeInput.value : 'single';
        var single = row.querySelector('.static-label-single');
        var plural = row.querySelector('.static-label-plural');

        if (single) {
            single.style.display = mode === 'single' ? '' : 'none';
        }

        if (plural) {
            plural.style.display = mode === 'plural' ? '' : 'none';
        }
    }

    function buildRow(siteHandle) {
        var row = document.createElement('div');
        row.className = 'input ltr static-label-row';

        var keyInput = document.createElement('input');
        keyInput.type = 'text';
        keyInput.name = 'labels[' + siteHandle + '][keys][]';
        keyInput.className = 'text fullwidth';
        keyInput.placeholder = 'label.key';
        keyInput.setAttribute('data-role', 'label-key');
        keyInput.setAttribute('spellcheck', 'false');
        keyInput.setAttribute('autocomplete', 'off');

        var keyCell = document.createElement('div');
        keyCell.className = 'static-label-col static-label-col-key';
        keyCell.appendChild(keyInput);

        var modeSelect = document.createElement('select');
        modeSelect.name = 'labels[' + siteHandle + '][modes][]';
        modeSelect.className = 'fullwidth static-label-mode';
        modeSelect.setAttribute('data-role', 'label-mode');
        var modeSingle = document.createElement('option');
        modeSingle.value = 'single';
        modeSingle.textContent = 'Single';
        var modePlural = document.createElement('option');
        modePlural.value = 'plural';
        modePlural.textContent = 'Plural';
        modeSelect.appendChild(modeSingle);
        modeSelect.appendChild(modePlural);

        var modeCell = document.createElement('div');
        modeCell.className = 'static-label-col static-label-col-mode';
        var modeWrapper = document.createElement('div');
        modeWrapper.className = 'select';
        modeWrapper.appendChild(modeSelect);
        modeCell.appendChild(modeWrapper);

        var valueCell = document.createElement('div');
        valueCell.className = 'static-label-col static-label-col-values';

        var singleWrap = document.createElement('div');
        singleWrap.className = 'static-label-single';

        var singleInput = document.createElement('input');
        singleInput.type = 'text';
        singleInput.name = 'labels[' + siteHandle + '][singleValues][]';
        singleInput.className = 'text fullwidth';
        singleInput.placeholder = 'Single string';
        singleInput.setAttribute('data-role', 'label-single-mode');
        singleWrap.appendChild(singleInput);

        var pluralWrap = document.createElement('div');
        pluralWrap.className = 'static-label-plural';
        pluralWrap.style.display = 'none';

        var pluralGrid = document.createElement('div');
        pluralGrid.className = 'static-label-plural-grid';

        var zeroInput = document.createElement('input');
        zeroInput.type = 'text';
        zeroInput.name = 'labels[' + siteHandle + '][zeroValues][]';
        zeroInput.className = 'text fullwidth';
        zeroInput.placeholder = '0 elements';
        zeroInput.setAttribute('data-role', 'label-zero');

        var oneInput = document.createElement('input');
        oneInput.type = 'text';
        oneInput.name = 'labels[' + siteHandle + '][oneValues][]';
        oneInput.className = 'text fullwidth';
        oneInput.placeholder = '1 element';
        oneInput.setAttribute('data-role', 'label-one');

        var manyInput = document.createElement('input');
        manyInput.type = 'text';
        manyInput.name = 'labels[' + siteHandle + '][manyValues][]';
        manyInput.className = 'text fullwidth';
        manyInput.placeholder = 'N elements';
        manyInput.setAttribute('data-role', 'label-many');

        pluralGrid.appendChild(zeroInput);
        pluralGrid.appendChild(oneInput);
        pluralGrid.appendChild(manyInput);
        pluralWrap.appendChild(pluralGrid);

        valueCell.appendChild(singleWrap);
        valueCell.appendChild(pluralWrap);

        var actionsCell = document.createElement('div');
        actionsCell.className = 'static-label-col static-label-col-actions';
        var deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'btn static-labels-remove-row';
        deleteButton.textContent = 'Delete';
        actionsCell.appendChild(deleteButton);

        row.appendChild(keyCell);
        row.appendChild(modeCell);
        row.appendChild(valueCell);
        row.appendChild(actionsCell);

        return row;
    }

    document.querySelectorAll('.static-labels-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            activateTab(tab.getAttribute('data-site-handle'));
        });
    });

    var initialTab = document.querySelector('.static-labels-tab');
    if (initialTab) {
        activateTab(initialTab.getAttribute('data-site-handle'));
    }

    document.querySelectorAll('.static-labels-search').forEach(function(input) {
        input.addEventListener('input', function() {
            applySearch(input.closest('.static-labels-site'));
        });
    });

    document.querySelectorAll('.static-labels-add-row').forEach(function(button) {
        button.addEventListener('click', function() {
            var siteContainer = button.closest('.static-labels-site');
            if (!siteContainer) {
                return;
            }

            var rows = siteContainer.querySelector('.static-labels-rows');
            if (!rows) {
                return;
            }

            rows.appendChild(buildRow(siteContainer.getAttribute('data-site-handle')));
            applySearch(siteContainer);
        });
    });

    document.addEventListener('input', function(event) {
        var keyInput = event.target.closest('[data-role="label-key"]');
        if (keyInput) {
            var sanitized = sanitizeKeyValue(keyInput.value);
            if (sanitized !== keyInput.value) {
                keyInput.value = sanitized;
            }
        }

        var rowInput = event.target.closest('.static-label-row');
        if (rowInput) {
            updateRowMode(rowInput);
            applySearch(rowInput.closest('.static-labels-site'));
        }
    });

    document.addEventListener('click', function(event) {
        var removeButton = event.target.closest('.static-labels-remove-row');
        if (!removeButton) {
            return;
        }

        var row = removeButton.closest('.static-label-row');
        var container = removeButton.closest('.static-labels-site');
        if (!row || !container) {
            return;
        }

        var rows = container.querySelectorAll('.static-label-row');
        if (rows.length <= 1) {
            row.querySelectorAll('input').forEach(function(input) {
                input.value = '';
            });
            var modeInput = row.querySelector('[data-role="label-mode"]');
            if (modeInput) {
                modeInput.value = 'single';
                updateRowMode(row);
            }
        } else {
            row.remove();
        }

        applySearch(container);
    });

    document.querySelectorAll('.static-labels-site').forEach(function(siteContainer) {
        siteContainer.querySelectorAll('.static-label-row').forEach(function(row) {
            updateRowMode(row);
        });
        applySearch(siteContainer);
    });
})();
