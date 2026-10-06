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
            $html .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }
        $html .= '></div>';

        return $html;
    }
}
