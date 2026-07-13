(function() {
    var searchInput = document.getElementById('toolkit-average-color-volume-search');
    var listbox = document.getElementById('toolkit-average-color-volume-listbox');
    var chipsContainer = document.getElementById('toolkit-average-color-selected-volumes');
    var inputsContainer = document.getElementById('toolkit-average-color-volume-inputs');
    var source = document.getElementById('toolkit-average-color-volume-source');
    var statusEl = document.getElementById('toolkit-average-color-field-status');

    if (!searchInput || !listbox || !chipsContainer || !inputsContainer || !source) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showStatus(message, isError) {
        if (!statusEl) { return; }
        statusEl.innerHTML = '<strong' + (isError ? ' class="error"' : '') + '>' + escapeHtml(message) + '</strong>';
    }

    var volumes = Array.prototype.slice.call(source.querySelectorAll('li')).map(function(li) {
        return {
            id: li.getAttribute('data-id'),
            name: li.getAttribute('data-name'),
            handle: li.getAttribute('data-handle'),
            hasField: li.getAttribute('data-has-field') === '1',
            selected: li.getAttribute('data-selected') === '1',
        };
    });

    function postAction(action, data, callback) {
        if (typeof Craft !== 'undefined' && Craft.postActionRequest) {
            Craft.postActionRequest(action, data, function(response, textStatus) {
                callback(response, textStatus === 'success');
            });
            return;
        }
        callback(null, false);
    }

    function createField(volume, button) {
        button.setAttribute('disabled', 'disabled');
        button.textContent = 'Creating...';
        postAction('toolkit/average-color/create-field', { volumeId: volume.id }, function(response, ok) {
            var message = response && response.message ? response.message : (ok ? 'Field created.' : 'Unable to create the field. Check the Craft logs for details.');
            showStatus(message, !ok);

            if (ok) {
                volume.hasField = true;
                renderChips();
                renderOptions(searchInput.value);
            } else {
                button.textContent = 'Create field';
                button.removeAttribute('disabled');
            }
        });
    }

    function buildCreateFieldButton(volume) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn small toolkit-average-color-create-btn';
        button.textContent = 'Create field';
        button.title = 'Add the "averageColor" field to this volume\'s field layout';
        button.addEventListener('mousedown', function(event) {
            event.stopPropagation();
            event.preventDefault();
        });
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            createField(volume, button);
        });
        return button;
    }

    function selectedVolumes() {
        return volumes.filter(function(volume) { return volume.selected; });
    }

    function renderHiddenInputs() {
        inputsContainer.innerHTML = '';
        selectedVolumes().forEach(function(volume) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'volumeIds[]';
            input.value = volume.id;
            inputsContainer.appendChild(input);
        });
    }

    function renderChips() {
        chipsContainer.innerHTML = '';
        selectedVolumes().forEach(function(volume) {
            var chip = document.createElement('span');
            chip.className = 'toolkit-average-color-chip';

            var label = document.createElement('span');
            label.textContent = volume.name;
            chip.appendChild(label);

            if (!volume.hasField) {
                var badge = document.createElement('span');
                badge.className = 'toolkit-average-color-badge toolkit-average-color-badge-missing';
                badge.textContent = 'Missing field';
                chip.appendChild(badge);
                chip.appendChild(buildCreateFieldButton(volume));
            }

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'toolkit-average-color-remove-btn';
            removeBtn.setAttribute('aria-label', 'Remove ' + volume.name);
            removeBtn.textContent = '×';
            removeBtn.addEventListener('click', function() {
                setSelected(volume, false);
            });
            chip.appendChild(removeBtn);

            chipsContainer.appendChild(chip);
        });
    }

    function setSelected(volume, selected) {
        volume.selected = selected;
        renderChips();
        renderHiddenInputs();
        renderOptions(searchInput.value);
    }

    function renderOptions(filterText) {
        var normalized = (filterText || '').trim().toLowerCase();
        var matches = volumes.filter(function(volume) {
            return normalized === '' ||
                volume.name.toLowerCase().indexOf(normalized) !== -1 ||
                volume.handle.toLowerCase().indexOf(normalized) !== -1;
        });

        listbox.innerHTML = '';

        if (matches.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'toolkit-average-color-option-empty';
            empty.textContent = 'No matching volumes.';
            listbox.appendChild(empty);
            return;
        }

        matches.forEach(function(volume) {
            var option = document.createElement('div');
            option.className = 'toolkit-average-color-option' + (volume.selected ? ' selected' : '');
            option.setAttribute('role', 'option');

            var label = document.createElement('span');
            label.className = 'toolkit-average-color-option-label';
            label.textContent = volume.name + ' (' + volume.handle + ')';
            option.appendChild(label);

            var meta = document.createElement('span');
            meta.className = 'toolkit-average-color-option-meta';

            var badge = document.createElement('span');
            badge.className = 'toolkit-average-color-badge ' + (volume.hasField ? 'toolkit-average-color-badge-ok' : 'toolkit-average-color-badge-missing');
            badge.textContent = volume.hasField ? 'Found' : 'Missing';
            meta.appendChild(badge);

            if (!volume.hasField) {
                meta.appendChild(buildCreateFieldButton(volume));
            }

            option.appendChild(meta);

            option.addEventListener('mousedown', function(event) {
                event.preventDefault();
                setSelected(volume, !volume.selected);
            });

            listbox.appendChild(option);
        });
    }

    function openListbox() {
        listbox.classList.remove('hidden');
        searchInput.setAttribute('aria-expanded', 'true');
    }

    function closeListbox() {
        listbox.classList.add('hidden');
        searchInput.setAttribute('aria-expanded', 'false');
    }

    searchInput.addEventListener('focus', function() {
        renderOptions(searchInput.value);
        openListbox();
    });
    searchInput.addEventListener('input', function() {
        renderOptions(searchInput.value);
        openListbox();
    });
    searchInput.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeListbox();
        }
    });
    searchInput.addEventListener('blur', function() {
        setTimeout(closeListbox, 150);
    });

    renderChips();
    renderHiddenInputs();
})();
