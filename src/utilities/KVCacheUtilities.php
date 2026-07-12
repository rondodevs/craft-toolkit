<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use rondodevs\toolkit\Toolkit;

class KVCacheUtilities extends Utility
{
    public static function displayName(): string
    {
        return 'KV Cache Utility';
    }

    public static function id(): string
    {
        return 'toolkit-kv-cache';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->kvCache;
        $defaults = $service->getDefaultSettings();
        $overrides = $service->getOverrides();
        $resolved = $service->getResolvedSettings();
        $view = Craft::$app->getView();
        $checkUrl = UrlHelper::actionUrl('toolkit/kv-cache/check');
        $flushUrl = UrlHelper::actionUrl('toolkit/kv-cache/flush');
        $csrfParam = Craft::$app->getRequest()->csrfParam;
        $redirectPath = self::redirectPath();

        $html = '<div class="pane">';
        $html .= '<h2>' . Html::encode(self::displayName()) . '</h2>';
        $html .= '<p class="light">Configure the frontend KV cache endpoints used by automatic on-save invalidation, manual flush, and connectivity checks.</p>';

        $html .= '<form method="post" accept-charset="UTF-8" action="' . Html::encode(UrlHelper::actionUrl('toolkit/kv-cache/save')) . '">';
        $html .= Html::csrfInput();
        $html .= Html::actionInput('toolkit/kv-cache/save');
        $html .= Html::redirectInput($redirectPath);

        $html .= Cp::lightswitchFieldHtml([
            'label' => 'Enable automatic on-save invalidation',
            'id' => 'toolkit-kv-enabled',
            'name' => 'enabled',
            'on' => (bool)$resolved['enabled'],
            'instructions' => 'Controls only automatic purge requests triggered on save. Manual check and manual flush remain available.',
        ]);

        $html .= self::textField(
            'Frontend URL',
            'frontendUrl',
            'toolkit-kv-frontend-url',
            (string)($overrides['frontendUrl'] ?? ''),
            (string)$defaults['frontendUrl'],
            'Absolute base URL of the frontend receiving purge requests.'
        );

        $html .= self::textField(
            'Auth token',
            'authToken',
            'toolkit-kv-auth-token',
            (string)($overrides['authToken'] ?? ''),
            (string)$defaults['authToken'],
            'Optional token sent in the configured auth header.'
        );

        $html .= self::textField(
            'Auth header name',
            'authHeaderName',
            'toolkit-kv-auth-header',
            (string)($overrides['authHeaderName'] ?? ''),
            (string)$defaults['authHeaderName'],
            'Header used to authenticate purge requests.'
        );

        $html .= self::textField(
            'Purge tags path',
            'purgeTagsPath',
            'toolkit-kv-purge-tags-path',
            (string)($overrides['purgeTagsPath'] ?? ''),
            (string)$defaults['purgeTagsPath'],
            'Relative path used for automatic tag-based invalidation on save.'
        );

        $html .= self::textField(
            'Flush-all path',
            'flushAllPath',
            'toolkit-kv-flush-all-path',
            (string)($overrides['flushAllPath'] ?? ''),
            (string)$defaults['flushAllPath'],
            'Relative path used by the manual full-flush button.'
        );

        $html .= self::numberField(
            'Request timeout (seconds)',
            'requestTimeout',
            'toolkit-kv-request-timeout',
            $overrides['requestTimeout'] ?? null,
            (int)$defaults['requestTimeout'],
            'Overall HTTP timeout for purge requests.'
        );

        $html .= self::numberField(
            'Connect timeout (seconds)',
            'connectTimeout',
            'toolkit-kv-connect-timeout',
            $overrides['connectTimeout'] ?? null,
            (int)$defaults['connectTimeout'],
            'Maximum time allowed to establish the connection.'
        );

        $html .= '<div class="buttons">';
        $html .= '<button type="submit" class="btn submit">Save settings</button>';
        $html .= '</div>';
        $html .= '</form>';

        $html .= '<hr>';
        $html .= '<div class="field">';
        $html .= '<p><strong>Connection check</strong></p>';
        $html .= '<p class="light">Checks the frontend stats endpoint automatically when this page loads, even if invalidation is disabled.</p>';
        $html .= '<div id="toolkit-kv-check-status" style="margin-bottom:10px;">Status: <strong>checking...</strong></div>';
        $html .= '<button type="button" class="btn kv-check-btn">Check endpoint</button>';
        $html .= '</div>';
        $html .= '<div class="field">';
        $html .= '<p><strong>Manual flush</strong></p>';
        $html .= '<p class="light">Executes the configured flush-all endpoint with the current resolved settings, even if automatic invalidation is disabled.</p>';
        $html .= '<div id="toolkit-kv-status" style="margin-bottom:10px;">Status: <strong>ready</strong></div>';
        $html .= '<button type="button" class="btn submit kv-flush-btn">Flush KV Cache</button>';
        $html .= '</div>';
        $html .= '</div>';

        $js = <<<JS
(function() {
    var checkBtn = document.querySelector('.kv-check-btn');
    var btn = document.querySelector('.kv-flush-btn');
    var checkStatus = document.getElementById('toolkit-kv-check-status');
    var status = document.getElementById('toolkit-kv-status');
    var csrfName = '{$csrfParam}';
    var csrfValue = typeof Craft !== 'undefined' ? (Craft.csrfTokenValue || '') : '';

    function renderCheckStatus(message, isError, details) {
        if (!checkStatus) {
            return;
        }

        var detailText = '';
        if (details && typeof details === 'object') {
            try {
                detailText = '<br><span class="light">' + JSON.stringify(details) + '</span>';
            } catch (e) {
                detailText = '';
            }
        }

        checkStatus.innerHTML = 'Status: <strong' + (isError ? ' class="error"' : '') + '>' + message + '</strong>' + detailText;
    }

    function checkEndpoint() {
        if (!checkBtn) {
            return;
        }

        checkBtn.setAttribute('disabled', 'disabled');
        renderCheckStatus('checking...', false);

        if (typeof Craft !== 'undefined' && Craft.postActionRequest) {
            Craft.postActionRequest('toolkit/kv-cache/check', {}, function(response) {
                var message = response && response.message ? response.message : 'check failed';
                renderCheckStatus(message, !(response && response.success), response && response.details ? response.details : null);
                checkBtn.removeAttribute('disabled');
            });
            return;
        }

        fetch('{$checkUrl}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: encodeURIComponent(csrfName) + '=' + encodeURIComponent(csrfValue)
        })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                var payload = json && json.data ? json.data : json;
                var message = payload && payload.message ? payload.message : 'check failed';
                renderCheckStatus(message, !(json && json.success), payload && payload.details ? payload.details : null);
            })
            .catch(function() {
                renderCheckStatus('check failed', true);
            })
            .finally(function() {
                checkBtn.removeAttribute('disabled');
            });
    }

    if (checkBtn) {
        checkBtn.addEventListener('click', checkEndpoint);
        checkEndpoint();
    }

    if (!btn) return;
    btn.addEventListener('click', function() {
        btn.setAttribute('disabled','disabled');
        status.innerHTML = 'Status: <strong>flushing...</strong>';
        if (typeof Craft !== 'undefined' && Craft.postActionRequest) {
            Craft.postActionRequest('toolkit/kv-cache/flush', {}, function(response) {
                if (response && response.success) {
                    status.innerHTML = 'Status: <strong>flushed</strong>';
                } else {
                    var message = response && response.message ? response.message : 'failed';
                    status.innerHTML = 'Status: <strong class="error">' + message + '</strong>';
                }
                btn.removeAttribute('disabled');
            });
        } else {
            fetch('{$flushUrl}', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: encodeURIComponent(csrfName) + '=' + encodeURIComponent(csrfValue)
            })
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if (json && json.success) {
                        status.innerHTML = 'Status: <strong>flushed</strong>';
                    } else {
                        var message = json && json.message ? json.message : 'failed';
                        status.innerHTML = 'Status: <strong class="error">' + message + '</strong>';
                    }
                })
                .catch(function(){
                    status.innerHTML = 'Status: <strong class="error">failed</strong>';
                })
                .finally(function(){ btn.removeAttribute('disabled'); });
        }
    });
})();
JS;

        $view->registerJs($js);

        return $html;
    }

    private static function redirectPath(): string
    {
        $path = trim((string)Craft::$app->getRequest()->getPathInfo(), '/');

        if ($path !== '') {
            return $path;
        }

        return 'utilities/' . self::id();
    }

    private static function textField(
        string $label,
        string $name,
        string $id,
        string $value,
        string $placeholder,
        string $hint
    ): string {
        $html = '<div class="field">';
        $html .= '<div class="heading"><label for="' . Html::encode($id) . '">' . Html::encode($label) . '</label></div>';
        $html .= '<div class="input ltr">';
        $html .= Html::textInput($name, $value, [
            'id' => $id,
            'class' => 'text fullwidth',
            'placeholder' => $placeholder,
        ]);
        $html .= '</div>';
        $html .= '<p class="light">' . Html::encode($hint) . '</p>';
        $html .= '<p class="light">Default: ' . Html::encode($placeholder) . '</p>';
        $html .= '</div>';

        return $html;
    }

    private static function numberField(
        string $label,
        string $name,
        string $id,
        mixed $value,
        int $placeholder,
        string $hint
    ): string {
        $html = '<div class="field">';
        $html .= '<div class="heading"><label for="' . Html::encode($id) . '">' . Html::encode($label) . '</label></div>';
        $html .= '<div class="input ltr">';
        $html .= Html::input('number', $name, $value === null ? '' : (string)$value, [
            'id' => $id,
            'class' => 'text fullwidth',
            'min' => 1,
            'placeholder' => (string)$placeholder,
        ]);
        $html .= '</div>';
        $html .= '<p class="light">' . Html::encode($hint) . '</p>';
        $html .= '<p class="light">Default: ' . Html::encode((string)$placeholder) . '</p>';
        $html .= '</div>';

        return $html;
    }
}
