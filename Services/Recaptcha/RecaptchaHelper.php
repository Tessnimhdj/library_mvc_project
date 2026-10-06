<?php

namespace Services\Recaptcha;

class RecaptchaHelper
{
    private $siteKey;
    private $version;
    private $theme;
    private $size;

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = require __DIR__ . '/recaptcha.config.php';
        }

        $this->siteKey = $config['site_key'];
        $this->version = $config['version'] ?? 'v2';
        $this->theme = $config['theme'] ?? 'light';
        $this->size = $config['size'] ?? 'normal';
    }

    public function renderScript($language = null)
    {
        if ($this->version === 'v3') {
            $url = "https://www.google.com/recaptcha/api.js?render={$this->siteKey}";
        } else {
            $url = "https://www.google.com/recaptcha/api.js";
            if ($language) {
                $url .= "?hl={$language}";
            }
        }

        return "<script src=\"{$url}\" async defer></script>";
    }

    public function renderV2($options = [])
    {
        $theme = $options['theme'] ?? $this->theme;
        $size = $options['size'] ?? $this->size;
        $callback = $options['callback'] ?? '';
        $expiredCallback = $options['expired-callback'] ?? '';
        $errorCallback = $options['error-callback'] ?? '';

        $attributes = [
            'class' => 'g-recaptcha',
            'data-sitekey' => $this->siteKey,
            'data-theme' => $theme,
            'data-size' => $size
        ];

        if ($callback) {
            $attributes['data-callback'] = $callback;
        }
        if ($expiredCallback) {
            $attributes['data-expired-callback'] = $expiredCallback;
        }
        if ($errorCallback) {
            $attributes['data-error-callback'] = $errorCallback;
        }

        $html = '<div';
        foreach ($attributes as $key => $value) {
            $html .= " {$key}=\"{$value}\"";
        }
        $html .= '></div>';

        return $html;
    }

    public function renderV3($action, $formId)
    {
        return "
<script>
    grecaptcha.ready(function() {
        document.getElementById('{$formId}').addEventListener('submit', function(e) {
            e.preventDefault();
            grecaptcha.execute('{$this->siteKey}', {action: '{$action}'}).then(function(token) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'g-recaptcha-response';
                input.value = token;
                document.getElementById('{$formId}').appendChild(input);
                document.getElementById('{$formId}').submit();
            });
        });
    });
</script>";
    }

    public function render($options = [])
    {
        $language = $options['language'] ?? null;
        $html = $this->renderScript($language) . "\n";

        if ($this->version === 'v3') {
            $action = $options['action'] ?? 'submit';
            $formId = $options['form_id'] ?? 'recaptcha-form';
            $html .= $this->renderV3($action, $formId);
        } else {
            $html .= $this->renderV2($options);
        }

        return $html;
    }

    public function renderInvisible($buttonId, $options = [])
    {
        $callback = $options['callback'] ?? 'onSubmit';

        $html = $this->renderScript($options['language'] ?? null) . "\n";
        $html .= "<button id=\"{$buttonId}\" class=\"g-recaptcha\" data-sitekey=\"{$this->siteKey}\" data-callback=\"{$callback}\" data-size=\"invisible\">";
        $html .= $options['button_text'] ?? 'Submit';
        $html .= "</button>\n";
        $html .= "<script>
function {$callback}(token) {
    document.getElementById('" . ($options['form_id'] ?? 'recaptcha-form') . "').submit();
}
</script>";

        return $html;
    }

    public function getSiteKey()
    {
        return $this->siteKey;
    }

    public function getVersion()
    {
        return $this->version;
    }
}
