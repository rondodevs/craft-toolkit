(function() {
    // Loaded again by every slideout and preview modal: set up once
    if (window.toolkitStreamingVideoEditor) {
        return;
    }
    window.toolkitStreamingVideoEditor = true;

    var HLS_JS_URL = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.light.min.js';
    var hlsJsPromise = null;

    function loadHlsJs() {
        if (window.Hls) {
            return Promise.resolve(window.Hls);
        }
        if (!hlsJsPromise) {
            hlsJsPromise = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = HLS_JS_URL;
                script.onload = function() { resolve(window.Hls); };
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }
        return hlsJsPromise;
    }

    function errorMessage(error, fallback) {
        return (error.response && error.response.data && error.response.data.message) || fallback;
    }

    function editorOf(element) {
        return element.closest('form') || document;
    }

    // ---------------------------------------------------------------------
    // Quality menu (Original, Auto, 1080p, 720p…) under a video, to watch
    // each encoded quality. Used under Craft's preview in the sidebar and in
    // the preview modal.
    // ---------------------------------------------------------------------

    // The qualities listed in the master playlist, largest first
    function readLevels(masterUrl) {
        return fetch(masterUrl).then(function(response) {
            return response.text();
        }).then(function(text) {
            var levels = [];
            var lines = text.split('\n');
            lines.forEach(function(line, index) {
                if (line.indexOf('#EXT-X-STREAM-INF') !== 0) {
                    return;
                }
                var resolution = /RESOLUTION=\d+x(\d+)/.exec(line);
                var bandwidth = /BANDWIDTH=(\d+)/.exec(line);
                var uri = (lines[index + 1] || '').trim();
                levels.push({
                    uri: new URL(uri, masterUrl).href,
                    height: resolution ? parseInt(resolution[1], 10) : null,
                    name: uri.replace(/\.m3u8$/, ''),
                    bandwidth: bandwidth ? parseInt(bandwidth[1], 10) : 0,
                });
            });
            return levels.sort(function(a, b) { return b.bandwidth - a.bandwidth; });
        });
    }

    function attachQualityBar(video, bar) {
        if (bar.dataset.ready) {
            return;
        }
        bar.dataset.ready = '1';

        var masterUrl = new URL(bar.dataset.hlsSrc, window.location.href).href;
        var originalSrc = bar.dataset.originalSrc;
        var hls = null;
        var levels = [];
        bar.innerHTML = '<label class="light">Quality</label><div class="select small"><select></select></div>';
        var menu = bar.querySelector('select');
        var label = bar.querySelector('label');
        menu.id = 'toolkit-quality-' + Math.random().toString(36).slice(2);
        label.htmlFor = menu.id;
        menu.addEventListener('change', function() {
            select(menu.value);
        });

        function addOption(key, text, title) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = text;
            option.title = title;
            menu.appendChild(option);
            return option;
        }

        function markActive(key) {
            menu.value = key;
        }

        // Switches source without losing the position or the play state
        function keepingPosition(change) {
            var time = video.currentTime;
            var playing = !video.paused;
            change();
            video.addEventListener('loadedmetadata', function() {
                video.currentTime = time;
                if (playing) {
                    video.play().catch(function() {});
                }
            }, {once: true});
        }

        function destroyHls() {
            if (hls) {
                hls.destroy();
                hls = null;
            }
        }

        function select(key) {
            markActive(key);
            autoOption.textContent = 'Auto';

            if (key === 'original') {
                keepingPosition(function() {
                    destroyHls();
                    video.src = originalSrc;
                    video.load();
                });
                return;
            }

            var level = key === 'auto' ? null : levels[parseInt(key, 10)];

            loadHlsJs().then(function(Hls) {
                if (!Hls || !Hls.isSupported()) {
                    // Safari without MSE: native HLS plays the master or one quality's playlist
                    keepingPosition(function() {
                        video.src = level ? level.uri : masterUrl;
                        video.load();
                    });
                    return;
                }

                if (hls) {
                    hls.currentLevel = level ? hlsLevelIndex(level) : -1;
                    return;
                }

                keepingPosition(function() {
                    hls = new Hls({capLevelToPlayerSize: false});
                    hls.on(Hls.Events.MANIFEST_PARSED, function() {
                        hls.currentLevel = level ? hlsLevelIndex(level) : -1;
                    });
                    hls.on(Hls.Events.LEVEL_SWITCHED, function(event, data) {
                        var playing = hls.levels[data.level];
                        if (hls.autoLevelEnabled && playing) {
                            autoOption.textContent = 'Auto · ' + playing.height + 'p';
                        }
                    });
                    hls.on(Hls.Events.ERROR, function(event, data) {
                        if (data.fatal) {
                            select('original');
                        }
                    });
                    video.removeAttribute('src');
                    hls.loadSource(masterUrl);
                    hls.attachMedia(video);
                });
            }, function() {
                select('original');
            });
        }

        function hlsLevelIndex(level) {
            // hls.js keeps the playlist URL as `uri` (a string) or `url` (an array)
            return hls.levels.findIndex(function(candidate) {
                var uri = candidate.uri || (Array.isArray(candidate.url) ? candidate.url[0] : candidate.url) || '';
                return uri.indexOf(level.name + '.m3u8') !== -1;
            });
        }

        // The modal closes by removing the video
        var observer = new MutationObserver(function() {
            if (!document.contains(video)) {
                destroyHls();
                observer.disconnect();
            }
        });
        observer.observe(document.body, {childList: true, subtree: true});

        addOption('original', 'Original', 'The uploaded file');
        var autoOption = addOption('auto', 'Auto', 'HLS, the quality picked by the player');

        readLevels(masterUrl).then(function(found) {
            levels = found;
            levels.forEach(function(level, index) {
                addOption(String(index), level.height ? level.height + 'p' : level.name, 'HLS, ' + Math.round(level.bandwidth / 1000) + ' kbps peak');
            });
        }).catch(function() {});

        select(bar.dataset.start || 'original');
    }

    // Under Craft's video preview in the sidebar, once the field says HLS is ready
    function initSidebarQualities(field) {
        if (!field.dataset.hlsSrc) {
            return;
        }
        var video = editorOf(field).querySelector('video.preview-thumb');
        if (!video || video.dataset.qualityBar) {
            return;
        }
        video.dataset.qualityBar = '1';

        var bar = document.createElement('div');
        bar.className = 'toolkit-quality-bar';
        bar.dataset.hlsSrc = field.dataset.hlsSrc;
        bar.dataset.originalSrc = field.dataset.originalSrc;
        (video.closest('.preview-thumb-container') || video).insertAdjacentElement('afterend', bar);
        attachQualityBar(video, bar);
    }

    // In the preview modal, the bar follows the video
    function initModalQualities(bar) {
        var video = bar.previousElementSibling;
        if (video && video.tagName === 'VIDEO') {
            attachQualityBar(video, bar);
        }
    }

    // ---------------------------------------------------------------------
    // Poster frame picker: the slider, the timestamp and the suggested
    // moments only move the preview; "Use this frame" applies it
    // ---------------------------------------------------------------------

    function formatTime(seconds) {
        var minutes = Math.floor(seconds / 60);
        var rest = (seconds - minutes * 60).toFixed(1);
        return minutes + ':' + (rest < 10 ? '0' : '') + rest;
    }

    // "83.4", "83,4" or "1:23.4" to seconds
    function parseTime(text) {
        var parts = String(text).trim().replace(',', '.').split(':');
        var seconds = 0;
        for (var i = 0; i < parts.length; i++) {
            var value = parseFloat(parts[i]);
            if (isNaN(value)) {
                return null;
            }
            seconds = seconds * 60 + value;
        }
        return Math.max(0, seconds);
    }

    function initFramePicker(picker) {
        if (picker.dataset.ready) {
            return;
        }
        picker.dataset.ready = '1';

        var video = picker.querySelector('.toolkit-frame-video');
        var slider = picker.querySelector('.toolkit-frame-slider');
        var timestamp = picker.querySelector('.toolkit-frame-timestamp');
        var candidatesEl = picker.querySelector('.toolkit-frame-candidates');
        var useButton = picker.querySelector('.toolkit-frame-use');

        picker.toolkitSelectedTime = function() {
            return parseTime(timestamp.value);
        };

        // "Use this frame" only for a frame that isn't the poster already
        picker.toolkitUpdateUseButton = function() {
            var current = parseFloat(picker.dataset.currentTime);
            var selected = parseTime(timestamp.value);
            if (useButton) {
                useButton.hidden = !isNaN(current) && selected !== null && Math.abs(selected - current) < 0.05;
            }
        };

        function preview(time, from) {
            if (video && video.duration) {
                time = Math.min(time, video.duration);
            }
            if (from !== 'timestamp') {
                timestamp.value = formatTime(time);
            }
            if (video) {
                video.currentTime = time;
                slider.value = time;
            }
            Array.prototype.forEach.call(picker.querySelectorAll('.toolkit-frame-candidate'), function(button) {
                button.classList.toggle('active', Math.abs(parseFloat(button.dataset.time) - time) < 0.05);
            });
            picker.toolkitUpdateUseButton();
        }

        timestamp.value = formatTime(parseFloat(picker.dataset.time) || 0);
        picker.toolkitUpdateUseButton();
        timestamp.addEventListener('input', picker.toolkitUpdateUseButton);

        timestamp.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                // Enter would submit the whole asset form
                event.preventDefault();
                var time = parseTime(timestamp.value);
                if (time !== null) {
                    preview(time);
                }
            }
        });
        timestamp.addEventListener('change', function() {
            var time = parseTime(timestamp.value);
            if (time !== null) {
                preview(time, 'timestamp');
            }
        });

        if (!video) {
            return;
        }

        slider.addEventListener('input', function() {
            preview(parseFloat(slider.value));
        });

        video.addEventListener('loadedmetadata', function() {
            slider.max = Math.max(0, video.duration - 0.1).toFixed(1);
            preview(parseFloat(picker.dataset.time) || 0);

            var times = JSON.parse(picker.dataset.candidates || 'null') || [0.25, 0.5, 0.75].map(function(fraction) {
                return Math.round(video.duration * fraction * 10) / 10;
            });
            renderCandidates(times);
        }, {once: true});

        // Thumbnails drawn from a second, hidden copy of the video, one seek at a time
        function renderCandidates(times) {
            var buttons = times.map(function(time, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'toolkit-frame-candidate';
                button.dataset.time = time;
                button.setAttribute('aria-label', 'Suggested moment ' + (index + 1) + ', ' + formatTime(time));
                button.innerHTML = '<canvas width="320" height="180"></canvas><span>' + formatTime(time) + '</span>';
                button.addEventListener('click', function() {
                    preview(time);
                });
                candidatesEl.appendChild(button);
                return button;
            });
            preview(parseFloat(slider.value) || 0);

            var sampler = document.createElement('video');
            sampler.muted = true;
            sampler.playsInline = true;
            sampler.preload = 'auto';
            sampler.src = video.currentSrc.split('#')[0];

            var index = 0;
            sampler.addEventListener('loadeddata', function() {
                sampler.currentTime = times[0];
            }, {once: true});
            sampler.addEventListener('seeked', function() {
                var canvas = buttons[index].querySelector('canvas');
                canvas.height = Math.round(canvas.width * sampler.videoHeight / sampler.videoWidth) || 180;
                canvas.getContext('2d').drawImage(sampler, 0, 0, canvas.width, canvas.height);
                index++;
                if (index < times.length) {
                    sampler.currentTime = times[index];
                } else {
                    sampler.removeAttribute('src');
                    sampler.load();
                }
            });
        }
    }

    // ---------------------------------------------------------------------
    // Poster changes: staged in hidden inputs and shown at once, applied by
    // the asset's Save; "Restore last" goes back to the saved poster
    // ---------------------------------------------------------------------

    function setMessage(field, text) {
        field.querySelector('.toolkit-poster-message').textContent = text;
    }

    // The frame the poster shows, so "Use this frame" hides on it
    function setCurrentTime(field, time) {
        var picker = field.querySelector('.toolkit-frame-picker');
        picker.dataset.currentTime = time === null || time === undefined ? '' : time;
        picker.toolkitUpdateUseButton();
    }

    // The image, snapshot or placeholder at the start of the poster box
    function replacePosterImage(field, element) {
        field.querySelector('.toolkit-poster-current').firstElementChild.replaceWith(element);
    }

    function posterImage(url) {
        var img = document.createElement('img');
        img.alt = 'Poster';
        img.src = url;
        return img;
    }

    function placeholder(text) {
        var span = document.createElement('span');
        span.className = 'light';
        span.textContent = text;
        return span;
    }

    // The frame drawn from the preview video, until the real file is extracted
    function snapshot(field) {
        var video = field.querySelector('.toolkit-frame-video');
        if (!video || !video.videoWidth) {
            return placeholder('Extracted after saving.');
        }
        var canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = Math.round(640 * video.videoHeight / video.videoWidth);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas;
    }

    function inputs(field) {
        return {
            time: field.querySelector('.toolkit-poster-time'),
            image: field.querySelector('.toolkit-poster-image'),
            reset: field.querySelector('.toolkit-poster-reset-input'),
        };
    }

    // The saved state, for "Restore last"
    function rememberSaved(field) {
        if (field.toolkitSaved) {
            return;
        }
        var fields = inputs(field);
        field.toolkitSaved = {
            image: field.querySelector('.toolkit-poster-current').firstElementChild.cloneNode(true),
            kind: field.querySelector('.toolkit-poster-kind').textContent,
            time: fields.time.value,
            currentTime: field.querySelector('.toolkit-frame-picker').dataset.currentTime,
        };
    }

    function setInputs(field, values) {
        var fields = inputs(field);
        ['time', 'image', 'reset'].forEach(function(key) {
            fields[key].value = values[key];
            // Craft notices the change and warns before leaving unsaved
            fields[key].dispatchEvent(new Event('change', {bubbles: true}));
        });
    }

    function stage(field, change) {
        rememberSaved(field);

        if (change.type === 'frame') {
            setInputs(field, {time: change.time.toFixed(1), image: '', reset: ''});
            replacePosterImage(field, snapshot(field));
            field.querySelector('.toolkit-poster-kind').textContent = 'Frame at ' + change.time.toFixed(1) + 's';
            setCurrentTime(field, change.time);
        } else if (change.type === 'image') {
            setInputs(field, {time: '', image: String(change.imageId), reset: ''});
            replacePosterImage(field, posterImage(change.thumbUrl));
            field.querySelector('.toolkit-poster-kind').textContent = 'Custom image';
            setCurrentTime(field, null);
        } else {
            setInputs(field, {time: '', image: '', reset: '1'});
            replacePosterImage(field, placeholder('The best moment, extracted after saving.'));
            field.querySelector('.toolkit-poster-kind').textContent = 'Automatic';
            setCurrentTime(field, null);
        }

        field.querySelector('.toolkit-poster-undo').hidden = false;
        setMessage(field, 'Applied when you save the asset.');
    }

    function undo(field) {
        var saved = field.toolkitSaved;
        if (!saved) {
            return;
        }
        setInputs(field, {time: saved.time, image: '', reset: ''});
        replacePosterImage(field, saved.image.cloneNode(true));
        field.querySelector('.toolkit-poster-kind').textContent = saved.kind;
        setCurrentTime(field, saved.currentTime === '' ? null : parseFloat(saved.currentTime));
        field.querySelector('.toolkit-poster-undo').hidden = true;
        setMessage(field, '');
    }

    // Jobs pushed by a save from a slideout don't start the queue on their
    // own (no worker in DDEV, for one): start it like Craft does after an upload
    function runQueue() {
        if (Craft.cp && typeof Craft.cp.runQueue === 'function') {
            Craft.cp.runQueue();
        }
    }

    // After a save that changed the poster: the new one shows once the queue extracts it
    function waitForPoster(field, attempt) {
        if (attempt >= 90) {
            setMessage(field, 'The poster is still in the queue: it changes once the worker gets to it.');
            return;
        }

        setTimeout(function() {
            Craft.sendActionRequest('GET', 'toolkit/streaming-video/poster', {params: {assetId: field.dataset.assetId}})
                .then(function(response) {
                    // Changed by the editor meanwhile: leave their pending change alone
                    if (field.toolkitSaved && !field.querySelector('.toolkit-poster-undo').hidden) {
                        return;
                    }
                    if (response.data.pending) {
                        waitForPoster(field, attempt + 1);
                        return;
                    }
                    if (response.data.thumbUrl) {
                        replacePosterImage(field, posterImage(response.data.thumbUrl));
                    }
                    field.querySelector('.toolkit-poster-kind').textContent = response.data.kind;
                    setCurrentTime(field, response.data.time);
                    field.toolkitSaved = null;
                    setMessage(field, 'Poster updated.');
                })
                .catch(function() {
                    waitForPoster(field, attempt + 1);
                });
        }, 2000);
    }

    function initPosterField(field) {
        var poster = field.querySelector('.toolkit-poster');
        if (!poster || poster.dataset.ready) {
            return;
        }
        poster.dataset.ready = '1';

        if (poster.dataset.pending) {
            runQueue();
            waitForPoster(field, 0);
        }
    }

    document.addEventListener('click', function(event) {
        var field = event.target.closest('.toolkit-streaming');

        if (!field) {
            return;
        }

        if (event.target.closest('.toolkit-frame-use')) {
            var time = field.querySelector('.toolkit-frame-picker').toolkitSelectedTime();
            if (time === null) {
                setMessage(field, 'Type the timestamp as 83.4 or 1:23.4.');
                return;
            }
            stage(field, {type: 'frame', time: time});
        }

        if (event.target.closest('.toolkit-poster-reset')) {
            stage(field, {type: 'reset'});
        }

        if (event.target.closest('.toolkit-poster-undo')) {
            undo(field);
        }

        if (event.target.closest('.toolkit-poster-choose')) {
            Craft.createElementSelectorModal('craft\\elements\\Asset', {
                sources: ['volume:' + field.querySelector('.toolkit-poster').dataset.volumeUid],
                criteria: {kind: ['image']},
                multiSelect: false,
                onSelect: function(elements) {
                    if (!elements.length) {
                        return;
                    }
                    Craft.sendActionRequest('GET', 'toolkit/streaming-video/image', {params: {imageId: elements[0].id}})
                        .then(function(response) {
                            stage(field, {type: 'image', imageId: response.data.imageId, thumbUrl: response.data.thumbUrl});
                        })
                        .catch(function(error) {
                            setMessage(field, errorMessage(error, 'Could not use that image.'));
                        });
                },
            });
        }

        var reprocess = event.target.closest('.toolkit-reprocess');

        if (reprocess) {
            var message = field.querySelector('.toolkit-reprocess-message');
            reprocess.classList.add('loading');

            Craft.sendActionRequest('POST', 'toolkit/streaming-video/reprocess', {data: {assetId: field.dataset.assetId}})
                .then(function(response) {
                    message.textContent = response.data.message;
                    runQueue();
                })
                .catch(function(error) {
                    message.textContent = errorMessage(error, 'Could not queue the video.');
                })
                .finally(function() {
                    reprocess.classList.remove('loading');
                });
        }
    });

    // The uploaded image is stored at once (it has to go somewhere), but only
    // becomes the poster with the asset's Save
    document.addEventListener('change', function(event) {
        if (!event.target.classList.contains('toolkit-poster-upload') || !event.target.files.length) {
            return;
        }

        var field = event.target.closest('.toolkit-streaming');
        var data = new FormData();
        data.append('assetId', field.dataset.assetId);
        data.append('poster', event.target.files[0]);
        event.target.value = '';
        setMessage(field, 'Uploading…');

        Craft.sendActionRequest('POST', 'toolkit/streaming-video/upload-poster', {data: data})
            .then(function(response) {
                stage(field, {type: 'image', imageId: response.data.imageId, thumbUrl: response.data.thumbUrl});
            })
            .catch(function(error) {
                setMessage(field, errorMessage(error, 'Could not upload the image.'));
            });
    });

    // ---------------------------------------------------------------------
    // Setup, now and for slideouts and modals added later
    // ---------------------------------------------------------------------

    function init(root) {
        Array.prototype.forEach.call(root.querySelectorAll('.toolkit-frame-picker'), initFramePicker);
        Array.prototype.forEach.call(root.querySelectorAll('.toolkit-streaming'), function(field) {
            initSidebarQualities(field);
            initPosterField(field);
        });
        Array.prototype.forEach.call(root.querySelectorAll('.toolkit-quality-bar:not([data-ready])'), function(bar) {
            if (bar.dataset.start) {
                initModalQualities(bar);
            }
        });
    }

    init(document);
    new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            Array.prototype.forEach.call(mutation.addedNodes, function(node) {
                if (node.nodeType === 1) {
                    init(node.parentNode || node);
                }
            });
        });
    }).observe(document.body, {childList: true, subtree: true});
})();
