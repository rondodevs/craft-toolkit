(function() {
    function activateTab(siteHandle) {
        var activeTab = null;

        document.querySelectorAll('.org-schema-tab').forEach(function(tab) {
            var isActive = tab.getAttribute('data-site-handle') === siteHandle;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                activeTab = tab;
            }
        });

        document.querySelectorAll('.org-schema-site').forEach(function(panel) {
            var isActive = panel.getAttribute('data-site-handle') === siteHandle;
            panel.classList.toggle('hidden', !isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });

        if (activeTab) {
            var siteTitle = document.querySelector('.org-schema-site-title');
            var siteUpdated = document.getElementById('org-schema-site-updated');
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

    document.querySelectorAll('.org-schema-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            activateTab(tab.getAttribute('data-site-handle'));
        });
    });

    var initialTab = document.querySelector('.org-schema-tab');
    if (initialTab) {
        activateTab(initialTab.getAttribute('data-site-handle'));
    }

    function readConditionalConfig() {
        var script = document.querySelector('.org-schema-conditional-config');

        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent);
        } catch (e) {
            return {};
        }
    }

    var conditionalConfig = readConditionalConfig();

    // "openingHours"/"priceRange" are only shown for LocalBusiness-like ("place")
    // types per schema.org (see schema.org/LocalBusiness, schema.org/Place) —
    // reuse that same type list to know when a type represents a physical place.
    var placeTypes = conditionalConfig.openingHours || [];

    function addAddressRow(addressesField) {
        var addressesContainer = addressesField.querySelector('[data-org-schema-addresses]');
        var addressTemplate = addressesField.querySelector('.org-schema-address-row-template');

        if (!addressTemplate || !addressesContainer) {
            return null;
        }

        var addressIndex = addressesContainer.querySelectorAll('.org-schema-address-row').length;
        var addressClone = addressTemplate.content.firstElementChild.cloneNode(true);
        var replaceAddressIndex = function(el) {
            el.setAttribute('name', el.getAttribute('name').replace(/__ADDRESSINDEX__/g, addressIndex));
        };

        addressClone.querySelectorAll('[name]').forEach(replaceAddressIndex);

        // The address row has its own nested opening-hours <template>; its
        // content lives in a separate DocumentFragment, so it isn't reached
        // by querySelectorAll() on the outer clone above and needs fixing up
        // separately, otherwise "Add hours" on a new address would submit
        // fields under the wrong (placeholder) address index.
        var nestedHoursTemplate = addressClone.querySelector('.org-schema-hours-row-template');

        if (nestedHoursTemplate) {
            nestedHoursTemplate.content.querySelectorAll('[name]').forEach(replaceAddressIndex);
        }

        addressesContainer.appendChild(addressClone);

        return addressClone;
    }

    function toggleConditionalFields(root, type) {
        root.querySelectorAll('[data-org-schema-conditional]').forEach(function(el) {
            var key = el.getAttribute('data-org-schema-conditional');
            var allowedTypes = conditionalConfig[key] || [];
            el.style.display = allowedTypes.indexOf(type) !== -1 ? '' : 'none';
        });
    }

    function currentType(siteContainer) {
        var select = siteContainer.querySelector('.org-schema-type-select');
        return select ? select.value : '';
    }

    function applyConditionalFields(siteContainer) {
        var select = siteContainer.querySelector('.org-schema-type-select');

        if (!select) {
            return;
        }

        var type = select.value;

        toggleConditionalFields(siteContainer, type);

        // priceRange/openingHours live on each address row, so a place-type
        // (LocalBusiness, MedicalClinic, EducationalOrganization, ...) with zero
        // addresses would have no visible way to set them at all — seed one
        // blank address automatically so those fields are immediately reachable.
        if (placeTypes.indexOf(type) !== -1) {
            var addressesField = siteContainer.querySelector('[data-org-schema-addresses]');
            addressesField = addressesField ? addressesField.closest('.field') : null;
            var existingCount = addressesField ? addressesField.querySelectorAll('.org-schema-address-row').length : 0;

            if (addressesField && existingCount === 0) {
                var newRow = addAddressRow(addressesField);

                if (newRow) {
                    toggleConditionalFields(newRow, type);
                }
            }
        }
    }

    document.querySelectorAll('.org-schema-site').forEach(function(siteContainer) {
        applyConditionalFields(siteContainer);
    });

    document.addEventListener('change', function(event) {
        var select = event.target.closest('.org-schema-type-select');

        if (!select) {
            return;
        }

        var siteContainer = select.closest('.org-schema-site');

        if (siteContainer) {
            applyConditionalFields(siteContainer);
        }
    });

    document.addEventListener('click', function(event) {
        var addButton = event.target.closest('.org-schema-sameas-add');

        if (addButton) {
            var siteContainer = addButton.closest('.org-schema-site');
            var template = siteContainer ? siteContainer.querySelector('.org-schema-sameas-row-template') : null;
            var rows = siteContainer ? siteContainer.querySelector('.org-schema-sameas-rows') : null;

            if (template && rows) {
                rows.appendChild(template.content.firstElementChild.cloneNode(true));
            }

            return;
        }

        var removeSameAsButton = event.target.closest('.org-schema-sameas-remove');

        if (removeSameAsButton) {
            var sameAsRow = removeSameAsButton.closest('.org-schema-sameas-row');

            if (sameAsRow) {
                sameAsRow.remove();
            }

            return;
        }

        var addAddressButton = event.target.closest('.org-schema-address-add');

        if (addAddressButton) {
            var addressesField = addAddressButton.closest('.field');
            var addAddressSiteContainer = addAddressButton.closest('.org-schema-site');

            if (addressesField) {
                var newAddressRow = addAddressRow(addressesField);

                // A new row is cloned from markup rendered at page-load time, so
                // its priceRange/openingHours visibility reflects whatever type
                // was selected back then — realign it with the type that's
                // actually selected right now (it may have been changed since).
                if (newAddressRow && addAddressSiteContainer) {
                    toggleConditionalFields(newAddressRow, currentType(addAddressSiteContainer));
                }
            }

            return;
        }

        var removeAddressButton = event.target.closest('.org-schema-address-remove');

        if (removeAddressButton) {
            var addressRow = removeAddressButton.closest('.org-schema-address-row');

            if (addressRow) {
                addressRow.remove();
            }

            return;
        }

        var addHoursButton = event.target.closest('.org-schema-hours-add');

        if (addHoursButton) {
            var hoursContainer = addHoursButton.closest('.field').querySelector('[data-org-schema-hours]');
            var hoursTemplate = addHoursButton.closest('.field').querySelector('.org-schema-hours-row-template');

            if (hoursTemplate && hoursContainer) {
                var hoursIndex = hoursContainer.querySelectorAll('.org-schema-hours-row').length;
                var hoursClone = hoursTemplate.content.firstElementChild.cloneNode(true);

                hoursClone.querySelectorAll('[name]').forEach(function(el) {
                    el.setAttribute('name', el.getAttribute('name').replace(/__HOURSINDEX__/g, hoursIndex));
                });

                hoursContainer.appendChild(hoursClone);
            }

            return;
        }

        var removeHoursButton = event.target.closest('.org-schema-hours-remove');

        if (removeHoursButton) {
            var hoursRow = removeHoursButton.closest('.org-schema-hours-row');

            if (hoursRow) {
                hoursRow.remove();
            }
        }
    });
})();
