(function() {
    var RECOMMENDED_WIDTH = 1200;
    var RECOMMENDED_HEIGHT = 630;

    function input(container, key) {
        return container.querySelector('[data-seo-input="' + key + '"]');
    }

    // What an input stands for: its value, else its placeholder (the social inputs' fallback)
    function shownValue(field) {
        if (!field) {
            return '';
        }

        return field.value.trim() !== '' ? field.value.trim() : (field.getAttribute('placeholder') || '').trim();
    }

    function readPreview(container) {
        try {
            return JSON.parse(container.dataset.preview || '{}');
        } catch (e) {
            return {};
        }
    }

    function values(container) {
        var preview = container._seoPreview;
        var title = shownValue(input(container, 'title'));
        var description = shownValue(input(container, 'description'));
        var ogTitle = input(container, 'ogTitle');
        var ogDescription = input(container, 'ogDescription');

        // The social inputs fall back to the live title and description
        if (ogTitle) {
            ogTitle.setAttribute('placeholder', title);
        }
        if (ogDescription) {
            ogDescription.setAttribute('placeholder', description);
        }

        return {
            title: title,
            description: description,
            ogTitle: shownValue(ogTitle) || title,
            ogDescription: shownValue(ogDescription) || description,
            siteName: preview.siteName || '',
            domain: preview.domain || '',
            image: container._seoCustomImage !== undefined ? container._seoCustomImage : (preview.customImage || preview.autoImage || null),
        };
    }

    function checks(v) {
        var list = [];

        if (!v.title) {
            list.push(['error', 'No title.']);
        } else if (v.title.length > 60) {
            list.push(['warning', 'Title is ' + v.title.length + ' characters: Google cuts it at about 60.']);
        }

        if (!v.description) {
            list.push(['warning', 'No description: Google and social networks pick some text from the page.']);
        } else if (v.description.length > 160) {
            list.push(['warning', 'Description is ' + v.description.length + ' characters: Google cuts it at about 160.']);
        }

        if (!v.image) {
            list.push(['warning', 'No social image: links are shared without a picture, or with the site fallback.']);
        } else if (v.image.width && v.image.height && (v.image.width < RECOMMENDED_WIDTH || v.image.height < RECOMMENDED_HEIGHT)) {
            list.push(['warning', 'Image is ' + v.image.width + ' × ' + v.image.height + ': at least 1200 × 630 looks sharp everywhere.']);
        } else if (v.image.width && v.image.height && Math.abs(v.image.width / v.image.height - 1.91) > 0.3) {
            list.push(['notice', 'Image is ' + v.image.width + ' × ' + v.image.height + ': networks crop it to 1.91:1, check the previews.']);
        }

        if (!list.length) {
            list.push(['ok', 'Title, description and image look good.']);
        }

        return list;
    }

    function render(container) {
        var v = values(container);

        container.querySelectorAll('[data-seo-bind]').forEach(function(el) {
            el.textContent = v[el.dataset.seoBind] || '';
        });

        container.querySelectorAll('[data-seo-image]').forEach(function(el) {
            var img = el.querySelector('img');
            el.classList.toggle('is-empty', !v.image);

            if (v.image && img.getAttribute('src') !== v.image.url) {
                img.setAttribute('src', v.image.url);
            }
        });

        var checkList = container.querySelector('[data-seo-checks]');
        if (checkList) {
            checkList.innerHTML = '';
            checks(v).forEach(function(check) {
                var li = document.createElement('li');
                li.className = 'toolkit-seo-check toolkit-seo-check-' + check[0];
                li.textContent = check[1];
                checkList.appendChild(li);
            });
        }

        container.querySelectorAll('[data-seo-input]').forEach(function(field) {
            updateCounter(container, field);
        });

        container.querySelectorAll('[data-seo-reset]').forEach(function(button) {
            button.hidden = isAuto(container, button.dataset.seoReset);
        });
    }

    function autoFlag(container, key) {
        return container.querySelector('[data-seo-auto-flag="' + key + '"]');
    }

    // Whether a prefilled input still holds its automatic value
    function isAuto(container, key) {
        var flag = autoFlag(container, key);
        return flag ? flag.value === '1' : input(container, key).value.trim() === '';
    }

    // Title and description start as their automatic values: typing makes them
    // custom, typing them back (or Reset) makes them automatic again
    function initAutoInputs(container) {
        container.querySelectorAll('[data-seo-auto-flag]').forEach(function(flag) {
            var field = input(container, flag.dataset.seoAutoFlag);

            field.addEventListener('input', function() {
                flag.value = field.value.trim() === (field.dataset.seoAuto || '').trim() ? '1' : '0';
                render(container);
            });
        });

        container.querySelectorAll('[data-seo-reset]').forEach(function(button) {
            button.addEventListener('click', function() {
                var key = button.dataset.seoReset;
                var field = input(container, key);

                field.value = field.dataset.seoAuto || '';
                autoFlag(container, key).value = '1';
                render(container);
            });
        });
    }

    function updateCounter(container, field) {
        var counter = container.querySelector('[data-seo-counter="' + field.dataset.seoInput + '"]');

        if (!counter) {
            return;
        }

        var limit = parseInt(field.dataset.seoLimit, 10) || 0;
        var length = shownValue(field).length;

        var automatic = autoFlag(container, field.dataset.seoInput) ? isAuto(container, field.dataset.seoInput) : field.value.trim() === '';
        counter.textContent = length + (limit ? ' / ' + limit : '') + (automatic ? ' · automatic' : ' · custom');
        counter.classList.toggle('toolkit-seo-counter-over', limit > 0 && length > limit);
    }

    // The custom image changes in Craft's element select: fetch a preview-sized thumbnail for it
    function watchImage(container) {
        var wrapper = container.querySelector('[data-seo-image-select]');

        if (!wrapper) {
            return;
        }

        var selectedId = function() {
            var chip = wrapper.querySelector('.elements [data-id]');
            return chip ? chip.dataset.id : null;
        };
        var lastId = selectedId();

        new MutationObserver(function() {
            var id = selectedId();

            if (id === lastId) {
                return;
            }
            lastId = id;

            if (!id) {
                container._seoCustomImage = container._seoPreview.autoImage || null;
                render(container);
                return;
            }

            Craft.sendActionRequest('POST', 'toolkit/seo/image-preview', {data: {id: id}})
                .then(function(response) {
                    if (lastId === id) {
                        container._seoCustomImage = response.data.image;
                        render(container);
                    }
                })
                .catch(function() {});
        }).observe(wrapper, {childList: true, subtree: true});
    }

    function initTabs(container) {
        var tabs = container.querySelectorAll('[data-seo-tab]');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(other) {
                    other.classList.toggle('active', other === tab);
                });
                container.querySelectorAll('[data-seo-panel]').forEach(function(panel) {
                    panel.hidden = panel.dataset.seoPanel !== tab.dataset.seoTab;
                });
            });
        });
    }

    function init(container) {
        if (container.dataset.seoReady) {
            return;
        }
        container.dataset.seoReady = '1';
        container._seoPreview = readPreview(container);

        container.querySelectorAll('[data-seo-input]').forEach(function(field) {
            field.addEventListener('input', function() {
                render(container);
            });
        });

        initTabs(container);
        initAutoInputs(container);
        watchImage(container);
        render(container);
    }

    function initAll() {
        document.querySelectorAll('[data-toolkit-seo]').forEach(init);
    }

    initAll();

    // Slideouts and element editors render the field after load
    new MutationObserver(initAll).observe(document.body, {childList: true, subtree: true});
})();
