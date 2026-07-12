(function() {
    var checkBtn = document.querySelector('.kv-cache-check-btn');
    var flushBtn = document.querySelector('.kv-cache-flush-btn');
    var purgeTagsBtn = document.querySelector('.kv-cache-purge-tags-btn');
    var checkStatus = document.getElementById('kv-cache-check-status');
    var flushStatus = document.getElementById('kv-cache-status');
    var tagsStatus = document.getElementById('kv-cache-tags-status');
    var tagSearchInput = document.getElementById('kv-cache-tag-search');
    var tagListbox = document.getElementById('kv-cache-tag-listbox');
    var tagChipsContainer = document.getElementById('kv-cache-selected-tags');
    var detailsContainer = document.getElementById('kv-cache-check-details');
    var searchInput = document.getElementById('kv-cache-search');
    var viewRawBtn = document.getElementById('kv-cache-view-raw');
    var copyBtn = document.getElementById('kv-cache-copy');
    var lastDetails = null;

    var TAG_KEYS = ['cacheTags', 'tags'];

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatScalar(value) {
        if (value === null) { return 'null'; }
        if (typeof value === 'boolean') { return value ? 'true' : 'false'; }
        return String(value);
    }

    function isPlainObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function isNonEmptyScalar(value) {
        return value !== null && value !== undefined && typeof value !== 'object' && String(value) !== '';
    }

    function cellValue(value) {
        if (value === null || value === undefined) { return ''; }
        if (typeof value === 'object') {
            try { return JSON.stringify(value); } catch (e) { return String(value); }
        }
        return formatScalar(value);
    }

    function toTagStrings(value) {
        if (!Array.isArray(value)) { return null; }
        var tags = value
            .filter(function(tag) { return tag !== null && tag !== undefined && typeof tag !== 'object'; })
            .map(function(tag) { return String(tag); });
        return tags.length > 0 ? tags : null;
    }

    function collectEntryTags(value, depth, seen, found) {
        if (depth > 8 || value === null || typeof value !== 'object') { return; }

        if (Array.isArray(value)) {
            value.forEach(function(v) { collectEntryTags(v, depth + 1, seen, found); });
            return;
        }

        if (isPlainObject(value)) {
            TAG_KEYS.forEach(function(key) {
                var tags = toTagStrings(value[key]);
                if (tags) {
                    tags.forEach(function(tag) {
                        if (!seen[tag]) {
                            seen[tag] = true;
                            found.push(tag);
                        }
                    });
                }
            });
            Object.keys(value).forEach(function(key) {
                if (TAG_KEYS.indexOf(key) !== -1) { return; }
                collectEntryTags(value[key], depth + 1, seen, found);
            });
        }
    }

    function entryTags(item) {
        var seen = {};
        var found = [];
        collectEntryTags(item, 0, seen, found);
        return found.length > 0 ? found : null;
    }

    function postAction(action, data, callback) {
        if (typeof Craft !== 'undefined' && Craft.postActionRequest) {
            Craft.postActionRequest(action, data, function(response, textStatus) {
                callback(response, textStatus === 'success');
            });
            return;
        }
        callback(null, false);
    }

    function removeRowFromDetails(item) {
        if (lastDetails && Array.isArray(lastDetails.rows)) {
            var idx = lastDetails.rows.indexOf(item);
            if (idx !== -1) { lastDetails.rows.splice(idx, 1); }
        }
        renderDetailsTree(lastDetails);
        if (searchInput && searchInput.value) { applyFilter(searchInput.value); }
    }

    function removeRowsByTags(tags) {
        if (!lastDetails || !Array.isArray(lastDetails.rows) || !tags || tags.length === 0) { return; }
        var tagSet = {};
        tags.forEach(function(tag) { tagSet[String(tag)] = true; });
        lastDetails.rows = lastDetails.rows.filter(function(item) {
            var itemTags = entryTags(item);
            if (!itemTags) { return true; }
            return !itemTags.some(function(tag) { return tagSet[tag]; });
        });
        renderDetailsTree(lastDetails);
        if (searchInput && searchInput.value) { applyFilter(searchInput.value); }
    }

    function buildPurgeButton(item) {
        var key = (isPlainObject(item) && isNonEmptyScalar(item.key)) ? String(item.key) : null;
        if (!key) { return null; }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn small kv-cache-row-purge-btn';
        button.textContent = 'Purge';
        button.title = 'Purge cache entry: ' + key;
        button.addEventListener('click', function() {
            button.setAttribute('disabled', 'disabled');
            button.textContent = 'Purging...';
            postAction('toolkit/kv-cache/purge-keys', { cacheType: 'data', keys: [key] }, function(response, ok) {
                if (ok) {
                    removeRowFromDetails(item);
                } else {
                    button.textContent = 'Failed';
                    button.removeAttribute('disabled');
                }
            });
        });
        return button;
    }

    function buildRawButton(item, label) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn small kv-cache-row-raw-btn';
        button.textContent = 'Raw JSON';
        button.addEventListener('click', function() {
            openRawModal(item, label ? ('Raw JSON — ' + label) : 'Raw JSON');
        });
        return button;
    }

    function findEntryInfos(value, depth, results) {
        if (results.length >= 5 || depth > 6 || value === null || typeof value !== 'object') { return; }

        if (Array.isArray(value)) {
            value.forEach(function(v) { findEntryInfos(v, depth + 1, results); });
            return;
        }

        if (isPlainObject(value)) {
            var idValue = value.id !== undefined ? value.id : value.entryId;
            if (isNonEmptyScalar(value.title) && isNonEmptyScalar(idValue)) {
                results.push({ title: String(value.title), id: String(idValue) });
                return;
            }
            Object.keys(value).forEach(function(key) {
                if (TAG_KEYS.indexOf(key) !== -1) { return; }
                findEntryInfos(value[key], depth + 1, results);
            });
        }
    }

    function entryInfo(item) {
        var results = [];
        findEntryInfos(item, 0, results);
        if (results.length === 0) { return null; }
        return { title: results[0].title, id: results[0].id, extraCount: results.length - 1 };
    }

    function buildKeyCell(item) {
        var td = document.createElement('td');
        td.className = 'kv-cache-key-cell';
        td.textContent = (isPlainObject(item) && isNonEmptyScalar(item.key)) ? String(item.key) : '—';
        return td;
    }

    function buildTitleCell(info) {
        var td = document.createElement('td');
        td.className = 'kv-cache-title-cell';

        if (!info) {
            td.textContent = '—';
            return td;
        }

        td.appendChild(document.createTextNode(info.title));

        if (info.extraCount > 0) {
            var more = document.createElement('span');
            more.className = 'kv-cache-entry-meta';
            more.textContent = ' (+' + info.extraCount + ' more)';
            td.appendChild(more);
        }

        return td;
    }

    function buildEntryIdCell(info) {
        var td = document.createElement('td');
        td.textContent = info ? info.id : '—';
        return td;
    }

    var TAGS_INLINE_LIMIT = 3;

    function buildTagChip(tag) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'kv-cache-chip kv-cache-chip-clickable';
        chip.textContent = tag;
        if (selectedTags.indexOf(tag) !== -1) {
            chip.classList.add('selected');
        }
        chip.title = selectedTags.indexOf(tag) !== -1
            ? 'Remove from the purge picker'
            : 'Add to the purge picker on the "Purge Cache" tab';
        chip.addEventListener('click', function() {
            if (selectedTags.indexOf(tag) !== -1) {
                removeSelectedTag(tag);
            } else {
                addSelectedTag(tag);
            }
        });
        registerTagChip(tag, chip);
        return chip;
    }

    function buildTagsCell(item) {
        var td = document.createElement('td');
        td.className = 'kv-cache-tags-cell';

        var tags = entryTags(item);
        if (!tags || tags.length === 0) {
            td.textContent = '—';
            return td;
        }

        var chips = document.createElement('div');
        chips.className = 'kv-cache-chips';
        tags.slice(0, TAGS_INLINE_LIMIT).forEach(function(tag) {
            chips.appendChild(buildTagChip(tag));
        });

        if (tags.length > TAGS_INLINE_LIMIT) {
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'kv-cache-chip kv-cache-chip-more';
            more.textContent = '+' + (tags.length - TAGS_INLINE_LIMIT) + ' more';
            more.addEventListener('click', function() {
                openTagsModal(tags);
            });
            chips.appendChild(more);
        }

        td.appendChild(chips);
        return td;
    }

    function buildObjectTable(items) {
        var wrap = document.createElement('div');
        wrap.className = 'kv-cache-table-wrap';

        var table = document.createElement('table');
        table.className = 'kv-cache-table';

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        ['Key', 'Title', 'Entry ID', 'Cache tags', 'Actions'].forEach(function(label) {
            var th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        var maxRows = 500;
        var rowCount = Math.min(items.length, maxRows);
        var hasPurgeableRows = false;

        for (var r = 0; r < rowCount; r++) {
            var row = document.createElement('tr');
            var item = items[r];
            var info = entryInfo(item);
            row.setAttribute('data-kv-cache-search', JSON.stringify(item).toLowerCase());

            row.appendChild(buildKeyCell(item));
            row.appendChild(buildTitleCell(info));
            row.appendChild(buildEntryIdCell(info));
            row.appendChild(buildTagsCell(item));

            var actionsTd = document.createElement('td');
            actionsTd.className = 'kv-cache-actions-cell';

            var entryLabel = (isPlainObject(item) && item.key) ? String(item.key) : (info ? info.title : null);
            actionsTd.appendChild(buildRawButton(item, entryLabel));

            var purgeButton = buildPurgeButton(item);
            if (purgeButton) {
                hasPurgeableRows = true;
                actionsTd.appendChild(purgeButton);
            }
            row.appendChild(actionsTd);

            tbody.appendChild(row);
        }
        table.appendChild(tbody);
        wrap.appendChild(table);

        if (items.length > maxRows) {
            var note = document.createElement('div');
            note.className = 'kv-cache-note';
            note.textContent = 'Showing first ' + maxRows + ' of ' + items.length + ' rows. Use the filter above to narrow down results.';
            wrap.appendChild(note);
        }

        if (!hasPurgeableRows) {
            var hint = document.createElement('div');
            hint.className = 'kv-cache-note';
            hint.textContent = 'No "key" field found on these entries, so per-row purge is not available here. Use "Purge by tags" instead.';
            wrap.appendChild(hint);
        }

        return wrap;
    }

    function buildChipList(items) {
        var wrap = document.createElement('div');
        wrap.className = 'kv-cache-table-wrap';
        var maxItems = 500;
        var list = items.slice(0, maxItems).map(function(item) { return formatScalar(item); }).join(', ');
        var p = document.createElement('div');
        p.className = 'kv-cache-section-body';
        p.textContent = list || '(empty)';
        wrap.appendChild(p);
        if (items.length > maxItems) {
            var note = document.createElement('div');
            note.className = 'kv-cache-note';
            note.textContent = 'Showing first ' + maxItems + ' of ' + items.length + ' items.';
            wrap.appendChild(note);
        }
        return wrap;
    }

    function buildDefinitionList(obj) {
        var dl = document.createElement('dl');
        dl.className = 'kv-cache-dl';
        Object.keys(obj).forEach(function(key) {
            var dt = document.createElement('dt');
            dt.textContent = key;
            var dd = document.createElement('dd');
            dd.textContent = cellValue(obj[key]);
            dl.appendChild(dt);
            dl.appendChild(dd);
        });
        return dl;
    }

    function findCountedListInfo(value) {
        if (!isPlainObject(value)) { return null; }
        if (typeof value.total !== 'number') { return null; }
        var keys = Object.keys(value);
        var arrayKey = keys.filter(function(k) { return k !== 'total' && Array.isArray(value[k]); })[0];
        if (!arrayKey) { return null; }
        return { arrayKey: arrayKey, total: value.total };
    }

    function unwrapCountedList(value) {
        var info = findCountedListInfo(value);
        if (!info) { return null; }
        if (Object.keys(value).length !== 2) { return null; }
        return { items: value[info.arrayKey], total: info.total };
    }

    function buildNode(value) {
        if (Array.isArray(value)) {
            if (value.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'kv-cache-note';
                empty.textContent = '(empty list)';
                return empty;
            }
            if (isPlainObject(value[0])) {
                return buildObjectTable(value);
            }
            return buildChipList(value);
        }

        var unwrapped = unwrapCountedList(value);
        if (unwrapped) {
            return buildNode(unwrapped.items);
        }

        if (isPlainObject(value)) {
            var keys = Object.keys(value);
            var allScalar = keys.every(function(key) { return !value[key] || typeof value[key] !== 'object'; });
            if (allScalar) {
                return buildDefinitionList(value);
            }

            var listInfo = findCountedListInfo(value);
            var container = document.createElement('div');
            keys.forEach(function(key) {
                if (listInfo && key === 'total') { return; }
                container.appendChild(buildSection(key, value[key], keys.length));
            });
            return container;
        }

        var scalarWrap = document.createElement('div');
        scalarWrap.className = 'kv-cache-section-body';
        scalarWrap.textContent = formatScalar(value);
        return scalarWrap;
    }

    function countOf(value) {
        var info = findCountedListInfo(value);
        if (info) { return info.total; }
        if (Array.isArray(value)) { return value.length; }
        if (isPlainObject(value)) { return Object.keys(value).length; }
        return null;
    }

    function buildSection(key, value, siblingCount) {
        var details = document.createElement('details');
        details.className = 'kv-cache-section';
        details.open = siblingCount <= 3;

        var summary = document.createElement('summary');
        var label = document.createElement('span');
        label.textContent = key;
        summary.appendChild(label);

        var count = countOf(value);
        if (count !== null) {
            var isListLike = Array.isArray(value) || !!findCountedListInfo(value);
            var badge = document.createElement('span');
            badge.className = 'kv-cache-badge';
            badge.textContent = count + (isListLike ? (count === 1 ? ' item' : ' items') : (count === 1 ? ' field' : ' fields'));
            summary.appendChild(badge);
        }

        details.appendChild(summary);

        var body = document.createElement('div');
        body.className = 'kv-cache-section-body';
        body.appendChild(buildNode(value));
        details.appendChild(body);

        return details;
    }

    function renderDetailsTree(details) {
        detailsContainer.innerHTML = '';
        tagChipRegistry = {};

        if (!details || typeof details !== 'object' || Object.keys(details).length === 0) {
            var note = document.createElement('div');
            note.className = 'kv-cache-note';
            note.textContent = 'No cache data returned by the endpoint.';
            detailsContainer.appendChild(note);
            return;
        }

        var keys = Object.keys(details).filter(function(key) {
            return key !== 'status' && key !== 'total';
        });
        if (keys.length === 0) {
            keys = Object.keys(details);
        }
        keys.forEach(function(key) {
            detailsContainer.appendChild(buildSection(key, details[key], keys.length));
        });
    }

    function applyFilter(term) {
        if (!detailsContainer) { return; }
        var normalized = (term || '').trim().toLowerCase();

        var rows = Array.prototype.slice.call(detailsContainer.querySelectorAll('tr[data-kv-cache-search]'));
        rows.forEach(function(row) {
            var haystack = row.getAttribute('data-kv-cache-search') || '';
            var match = normalized === '' || haystack.indexOf(normalized) !== -1;
            row.classList.toggle('kv-cache-hidden', !match);
        });

        if (normalized !== '') {
            var sections = Array.prototype.slice.call(detailsContainer.querySelectorAll('.kv-cache-section'));
            sections.forEach(function(section) {
                if (section.textContent.toLowerCase().indexOf(normalized) !== -1) {
                    section.open = true;
                }
            });
        }
    }

    function closeModal(overlay) {
        if (typeof overlay.__kvCacheCleanup === 'function') {
            overlay.__kvCacheCleanup();
        }
        overlay.remove();
        document.removeEventListener('keydown', overlay.__kvCacheEscHandler);
    }

    function createModal(title, onClose) {
        var overlay = document.createElement('div');
        overlay.className = 'kv-cache-modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'kv-cache-modal';

        var header = document.createElement('div');
        header.className = 'kv-cache-modal-header';
        var titleEl = document.createElement('h3');
        titleEl.textContent = title;
        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn';
        closeBtn.textContent = 'Close';
        header.appendChild(titleEl);
        header.appendChild(closeBtn);

        var body = document.createElement('div');
        body.className = 'kv-cache-modal-body';

        modal.appendChild(header);
        modal.appendChild(body);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        overlay.__kvCacheCleanup = onClose || null;

        closeBtn.addEventListener('click', function() { closeModal(overlay); });
        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) { closeModal(overlay); }
        });

        overlay.__kvCacheEscHandler = function(event) {
            if (event.key === 'Escape') { closeModal(overlay); }
        };
        document.addEventListener('keydown', overlay.__kvCacheEscHandler);

        return body;
    }

    function openRawModal(details, title) {
        var body = createModal(title || 'Raw JSON');
        var pre = document.createElement('pre');
        pre.className = 'kv-cache-raw';
        try {
            pre.textContent = JSON.stringify(details, null, 2);
        } catch (e) {
            pre.textContent = String(details);
        }
        body.appendChild(pre);
    }

    function openTagsModal(tags) {
        var chipEntries = [];

        var body = createModal('Cache tags (' + tags.length + ')', function() {
            chipEntries.forEach(function(entry) {
                unregisterTagChip(entry.tag, entry.chip);
            });
        });

        var hint = document.createElement('p');
        hint.className = 'light';
        hint.textContent = 'Click a tag to add or remove it from the picker on the "Purge Cache" tab.';
        body.appendChild(hint);

        var chips = document.createElement('div');
        chips.className = 'kv-cache-chips';
        tags.forEach(function(tag) {
            var chip = buildTagChip(tag);
            chipEntries.push({ tag: tag, chip: chip });
            chips.appendChild(chip);
        });
        body.appendChild(chips);
    }

    function collectAllTags(details) {
        var found = [];
        var seen = {};

        function addTag(tag) {
            if (tag === null || tag === undefined || typeof tag === 'object') { return; }
            var value = String(tag);
            if (value === '' || seen[value]) { return; }
            seen[value] = true;
            found.push(value);
        }

        function walk(value) {
            if (Array.isArray(value)) {
                value.forEach(walk);
                return;
            }
            if (isPlainObject(value)) {
                TAG_KEYS.forEach(function(key) {
                    if (Array.isArray(value[key])) {
                        value[key].forEach(addTag);
                    }
                });
                Object.keys(value).forEach(function(key) {
                    if (TAG_KEYS.indexOf(key) !== -1) { return; }
                    walk(value[key]);
                });
            }
        }

        walk(details);
        found.sort();
        return found;
    }

    var availableTags = [];
    var selectedTags = [];
    var MAX_TAG_OPTIONS = 100;
    var tagChipRegistry = {};

    function registerTagChip(tag, chip) {
        if (!tagChipRegistry[tag]) { tagChipRegistry[tag] = []; }
        tagChipRegistry[tag].push(chip);
    }

    function unregisterTagChip(tag, chip) {
        var chips = tagChipRegistry[tag];
        if (!chips) { return; }
        var index = chips.indexOf(chip);
        if (index !== -1) { chips.splice(index, 1); }
    }

    function syncTagChips(tag) {
        var chips = tagChipRegistry[tag];
        if (!chips) { return; }
        var isSelected = selectedTags.indexOf(tag) !== -1;
        chips.forEach(function(chip) {
            chip.classList.toggle('selected', isSelected);
            chip.title = isSelected
                ? 'Remove from the purge picker'
                : 'Add to the purge picker on the "Purge Cache" tab';
        });
    }

    function renderTagChips() {
        if (!tagChipsContainer) { return; }
        tagChipsContainer.innerHTML = '';
        selectedTags.forEach(function(tag) {
            var chip = document.createElement('span');
            chip.className = 'kv-cache-tag-chip';

            var label = document.createElement('span');
            label.textContent = tag;
            chip.appendChild(label);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', 'Remove ' + tag);
            removeBtn.textContent = '×';
            removeBtn.addEventListener('click', function() {
                removeSelectedTag(tag);
            });
            chip.appendChild(removeBtn);

            tagChipsContainer.appendChild(chip);
        });
    }

    function addSelectedTag(tag) {
        tag = String(tag).trim();
        if (tag === '' || selectedTags.indexOf(tag) !== -1) { return; }
        selectedTags.push(tag);
        renderTagChips();
        syncTagChips(tag);
    }

    function removeSelectedTag(tag) {
        var index = selectedTags.indexOf(tag);
        if (index !== -1) {
            selectedTags.splice(index, 1);
            renderTagChips();
            syncTagChips(tag);
        }
    }

    function resetTagPicker() {
        selectedTags.slice().forEach(function(tag) {
            removeSelectedTag(tag);
        });
        if (tagSearchInput) { tagSearchInput.value = ''; }
        renderTagOptions('');
        closeTagListbox();
    }

    function renderTagOptions(filterText) {
        if (!tagListbox) { return; }
        var normalized = (filterText || '').trim().toLowerCase();
        var matches = availableTags.filter(function(tag) {
            return normalized === '' || tag.toLowerCase().indexOf(normalized) !== -1;
        }).slice(0, MAX_TAG_OPTIONS);

        tagListbox.innerHTML = '';

        if (matches.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'kv-cache-tag-empty';
            empty.textContent = normalized === ''
                ? 'No cache tags discovered yet. Run a connection check, or type a tag and press Enter.'
                : 'No matching tags. Press Enter to use "' + filterText + '" as a custom tag.';
            tagListbox.appendChild(empty);
            return;
        }

        matches.forEach(function(tag) {
            var option = document.createElement('div');
            option.className = 'kv-cache-tag-option' + (selectedTags.indexOf(tag) !== -1 ? ' selected' : '');
            option.setAttribute('role', 'option');
            option.textContent = tag;
            option.addEventListener('mousedown', function(event) {
                event.preventDefault();
                if (selectedTags.indexOf(tag) !== -1) {
                    removeSelectedTag(tag);
                } else {
                    addSelectedTag(tag);
                }
                renderTagOptions(tagSearchInput ? tagSearchInput.value : '');
            });
            tagListbox.appendChild(option);
        });
    }

    function openTagListbox() {
        if (!tagListbox) { return; }
        tagListbox.classList.remove('hidden');
        if (tagSearchInput) { tagSearchInput.setAttribute('aria-expanded', 'true'); }
    }

    function closeTagListbox() {
        if (!tagListbox) { return; }
        tagListbox.classList.add('hidden');
        if (tagSearchInput) { tagSearchInput.setAttribute('aria-expanded', 'false'); }
    }

    function setAvailableTags(tags) {
        availableTags = tags || [];
        if (tagListbox && !tagListbox.classList.contains('hidden')) {
            renderTagOptions(tagSearchInput ? tagSearchInput.value : '');
        }
    }

    if (tagSearchInput) {
        tagSearchInput.addEventListener('focus', function() {
            renderTagOptions(tagSearchInput.value);
            openTagListbox();
        });
        tagSearchInput.addEventListener('input', function() {
            renderTagOptions(tagSearchInput.value);
            openTagListbox();
        });
        tagSearchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                var value = tagSearchInput.value.trim();
                if (value !== '') {
                    addSelectedTag(value);
                    tagSearchInput.value = '';
                    renderTagOptions('');
                }
            } else if (event.key === 'Escape') {
                closeTagListbox();
            }
        });
        tagSearchInput.addEventListener('blur', function() {
            setTimeout(closeTagListbox, 150);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            applyFilter(searchInput.value);
        });
    }

    if (viewRawBtn) {
        viewRawBtn.addEventListener('click', function() {
            if (!lastDetails) { return; }
            openRawModal(lastDetails, 'Raw JSON');
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            if (!lastDetails) { return; }
            var text = '';
            try { text = JSON.stringify(lastDetails, null, 2); } catch (e) { text = String(lastDetails); }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(function() { copyBtn.textContent = 'Copy JSON'; }, 1500);
                });
            }
        });
    }

    function renderSkeleton() {
        if (!detailsContainer) { return; }
        detailsContainer.innerHTML = '';

        var section = document.createElement('details');
        section.className = 'kv-cache-section';
        section.open = true;

        var summary = document.createElement('summary');
        var label = document.createElement('span');
        label.textContent = 'rows';
        summary.appendChild(label);
        var badge = document.createElement('span');
        badge.className = 'kv-cache-badge';
        badge.textContent = 'loading…';
        summary.appendChild(badge);
        section.appendChild(summary);

        var body = document.createElement('div');
        body.className = 'kv-cache-section-body';
        var wrap = document.createElement('div');
        wrap.className = 'kv-cache-skeleton';
        for (var i = 0; i < 6; i++) {
            var row = document.createElement('div');
            row.className = 'kv-cache-skeleton-row';
            wrap.appendChild(row);
        }
        body.appendChild(wrap);
        section.appendChild(body);

        detailsContainer.appendChild(section);
    }

    function renderErrorNote(message) {
        if (!detailsContainer) { return; }
        detailsContainer.innerHTML = '';
        var note = document.createElement('div');
        note.className = 'kv-cache-note kv-cache-error-note';
        note.textContent = message;
        detailsContainer.appendChild(note);
    }

    function renderCheckStatus(message, isError, details) {
        if (!checkStatus) { return; }

        checkStatus.innerHTML = 'Status: <strong' + (isError ? ' class="error"' : '') + '>' + escapeHtml(message) + '</strong>';

        lastDetails = (details && typeof details === 'object') ? details : null;
        if (searchInput) { searchInput.value = ''; }

        if (lastDetails) {
            renderDetailsTree(lastDetails);
        } else {
            renderErrorNote(isError ? message : 'No cache data returned by the endpoint.');
        }

        setAvailableTags(collectAllTags(lastDetails));
    }

    function checkEndpoint() {
        if (!checkBtn) { return; }

        checkBtn.setAttribute('disabled', 'disabled');
        if (checkStatus) {
            checkStatus.innerHTML = 'Status: <strong>checking...</strong>';
        }
        renderSkeleton();

        postAction('toolkit/kv-cache/check', {}, function(response, ok) {
            var message = response && response.message ? response.message : 'check failed';
            renderCheckStatus(message, !ok, response && response.details ? response.details : null);
            checkBtn.removeAttribute('disabled');
        });
    }

    if (checkBtn) {
        checkBtn.addEventListener('click', checkEndpoint);
        checkEndpoint();
    }

    if (flushBtn && flushStatus) {
        flushBtn.addEventListener('click', function() {
            flushBtn.setAttribute('disabled', 'disabled');
            flushStatus.innerHTML = 'Status: <strong>flushing...</strong>';
            postAction('toolkit/kv-cache/flush', {}, function(response, ok) {
                if (ok) {
                    flushStatus.innerHTML = 'Status: <strong>flushed</strong>';
                    if (lastDetails && Array.isArray(lastDetails.rows)) {
                        lastDetails.rows = [];
                        renderDetailsTree(lastDetails);
                    }
                } else {
                    var message = response && response.message ? response.message : 'failed';
                    flushStatus.innerHTML = 'Status: <strong class="error">' + escapeHtml(message) + '</strong>';
                }
                flushBtn.removeAttribute('disabled');
            });
        });
    }

    if (purgeTagsBtn && tagsStatus) {
        purgeTagsBtn.addEventListener('click', function() {
            var tags = selectedTags.slice();

            if (tags.length === 0) {
                tagsStatus.innerHTML = 'Status: <strong class="error">Pick or type at least one tag.</strong>';
                return;
            }

            purgeTagsBtn.setAttribute('disabled', 'disabled');
            purgeTagsBtn.textContent = 'Purging...';
            tagsStatus.innerHTML = 'Status: <strong>purging...</strong>';
            postAction('toolkit/kv-cache/purge-tags', { tags: tags }, function(response, ok) {
                if (ok) {
                    tagsStatus.innerHTML = 'Status: <strong>purged</strong>';
                    removeRowsByTags(tags);
                    resetTagPicker();
                } else {
                    var message = response && response.message ? response.message : 'failed';
                    tagsStatus.innerHTML = 'Status: <strong class="error">' + escapeHtml(message) + '</strong>';
                }
                purgeTagsBtn.removeAttribute('disabled');
                purgeTagsBtn.textContent = 'Purge tags';
            });
        });
    }

    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('.kv-cache-tab'));
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
