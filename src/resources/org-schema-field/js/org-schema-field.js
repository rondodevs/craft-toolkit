(function() {
    function readConfig(container) {
        var script = container.querySelector('.org-schema-field-config');

        if (!script) {
            return {typeFields: {}, defaultFields: []};
        }

        try {
            return JSON.parse(script.textContent);
        } catch (e) {
            return {typeFields: {}, defaultFields: []};
        }
    }

    function fieldsForType(config, type) {
        if (type && config.typeFields && config.typeFields[type]) {
            return config.typeFields[type];
        }

        return config.defaultFields || [];
    }

    function buildBasicFieldHtml(prefix, field) {
        var wrapper = document.createElement('div');
        wrapper.className = 'field org-schema-field-basic-field' + (field.inputType === 'textarea' ? ' org-schema-field-basic-field-wide' : '');

        var heading = document.createElement('div');
        heading.className = 'heading';
        var label = document.createElement('label');
        label.textContent = field.label;
        heading.appendChild(label);

        var inputWrap = document.createElement('div');
        inputWrap.className = 'input ltr';

        var input;
        if (field.inputType === 'textarea') {
            input = document.createElement('textarea');
            input.className = 'text fullwidth';
            input.rows = 2;
        } else {
            input = document.createElement('input');
            input.type = field.inputType || 'text';
            input.className = 'text fullwidth';
        }
        input.name = prefix + '[props][' + field.key + ']';

        inputWrap.appendChild(input);
        wrapper.appendChild(heading);
        wrapper.appendChild(inputWrap);

        return wrapper;
    }

    function rebuildBasicFields(row, config) {
        var select = row.querySelector('.org-schema-field-type');
        var basicContainer = row.querySelector('.org-schema-field-basic');

        if (!select || !basicContainer) {
            return;
        }

        var name = select.getAttribute('name') || '';
        var suffix = '[type]';
        var prefix = name.slice(0, name.length - suffix.length);
        var fields = fieldsForType(config, select.value);

        basicContainer.innerHTML = '';

        fields.forEach(function(field) {
            basicContainer.appendChild(buildBasicFieldHtml(prefix, field));
        });
    }

    function setRowMode(row, mode) {
        var basicContainer = row.querySelector('.org-schema-field-basic');
        var jsonContainer = row.querySelector('.org-schema-field-json');
        var modeInput = row.querySelector('.org-schema-field-mode-input');
        var buttons = row.querySelectorAll('.org-schema-field-mode-btn');

        row.setAttribute('data-mode', mode);

        if (modeInput) {
            modeInput.value = mode;
        }

        buttons.forEach(function(button) {
            button.classList.toggle('active', button.getAttribute('data-mode') === mode);
        });

        if (basicContainer) {
            basicContainer.style.display = mode === 'ui' ? '' : 'none';
        }

        if (jsonContainer) {
            jsonContainer.style.display = mode === 'json' ? '' : 'none';
        }
    }

    function syncJsonFromBasicFields(row) {
        var basicContainer = row.querySelector('.org-schema-field-basic');
        var textarea = row.querySelector('.org-schema-field-properties');

        if (!basicContainer || !textarea) {
            return;
        }

        var properties = {};

        basicContainer.querySelectorAll('input, textarea').forEach(function(input) {
            var match = /\[props\]\[([^\]]+)\]$/.exec(input.getAttribute('name') || '');
            var value = input.value.trim();

            if (match && value !== '') {
                properties[match[1]] = value;
            }
        });

        if (Object.keys(properties).length > 0) {
            textarea.value = JSON.stringify(properties, null, 2);
        }
    }

    function enhanceTypeSelect(select, onChange) {
        if (!select || !window.jQuery || !window.jQuery.fn.selectize || select.dataset.selectized) {
            return;
        }

        select.dataset.selectized = '1';

        // Selectize updates the backing <select> via a jQuery-only `trigger('change')`,
        // which never dispatches a native "change" event — so a plain
        // `addEventListener('change', ...)` on an ancestor never sees it. Hook
        // Selectize's own onChange callback instead to react immediately.
        window.jQuery(select).selectize({
            create: true,
            createOnBlur: true,
            persist: false,
            maxItems: 1,
            openOnFocus: true,
            onChange: function() {
                if (onChange) {
                    onChange();
                }
            },
        });
    }

    function initRow(row, config) {
        var select = row.querySelector('.org-schema-field-type');

        enhanceTypeSelect(select, function() {
            rebuildBasicFields(row, config);
        });

        setRowMode(row, row.getAttribute('data-mode') === 'json' ? 'json' : 'ui');
    }

    document.querySelectorAll('[data-org-schema-field]').forEach(function(container) {
        var config = readConfig(container);

        container.querySelectorAll('.org-schema-field-rows > .org-schema-field-row').forEach(function(row) {
            initRow(row, config);
        });
    });

    document.addEventListener('change', function(event) {
        var select = event.target.closest('.org-schema-field-type');

        if (!select) {
            return;
        }

        var container = select.closest('[data-org-schema-field]');
        var row = select.closest('.org-schema-field-row');

        if (!container || !row) {
            return;
        }

        rebuildBasicFields(row, readConfig(container));
    });

    document.addEventListener('click', function(event) {
        var modeButton = event.target.closest('.org-schema-field-mode-btn');

        if (modeButton) {
            var row = modeButton.closest('.org-schema-field-row');
            var mode = modeButton.getAttribute('data-mode');

            if (row && mode) {
                if (mode === 'json') {
                    syncJsonFromBasicFields(row);
                }

                setRowMode(row, mode);
            }

            return;
        }

        var addButton = event.target.closest('.org-schema-field-add-row');

        if (addButton) {
            var container = addButton.closest('[data-org-schema-field]');
            var template = container ? container.querySelector('.org-schema-field-row-template') : null;
            var rows = container ? container.querySelector('.org-schema-field-rows') : null;

            if (template && rows) {
                var index = rows.querySelectorAll('.org-schema-field-row').length;
                var clone = template.content.firstElementChild.cloneNode(true);

                clone.querySelectorAll('[name]').forEach(function(el) {
                    el.setAttribute('name', el.getAttribute('name').replace(/__ROWINDEX__/g, index));
                });

                rows.appendChild(clone);
                initRow(clone, readConfig(container));
            }

            return;
        }

        var removeButton = event.target.closest('.org-schema-field-remove-row');

        if (removeButton) {
            var row = removeButton.closest('.org-schema-field-row');

            if (row) {
                row.remove();
            }
        }
    });
})();
