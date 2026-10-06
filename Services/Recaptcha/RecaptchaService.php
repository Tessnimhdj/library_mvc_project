<?php

namespace Services\Recaptcha;

class RecaptchaService
{
    private $secretKey;
    private $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = require __DIR__ . '/recaptcha.config.php';
        }

        $this->secretKey = $config['secret_key'];
    }

    public function verify($response, $remoteIp = null)
    {
        if (empty($response)) {
            return [
                'success' => false,
                'error' => 'Please complete the reCAPTCHA check'
            ];
        }

        $data = [
            'secret' => $this->secretKey,
            'response' => $response
        ];

        if ($remoteIp !== null) {
            $data['remoteip'] = $remoteIp;
        }

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($this->verifyUrl, false, $context);

        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Could not reach the Google reCAPTCHA server'
            ];
        }

        $resultJson = json_decode($result, true);

        if (!isset($resultJson['success'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from the reCAPTCHA server'
            ];
        }

        if ($resultJson['success']) {
            return [
                'success' => true,
                'score' => $resultJson['score'] ?? null,
                'action' => $resultJson['action'] ?? null,
                'challenge_ts' => $resultJson['challenge_ts'] ?? null,
                'hostname' => $resultJson['hostname'] ?? null
            ];
        }

        return [
            'success' => false,
            'error' => 'reCAPTCHA verification failed',
            'error_codes' => $resultJson['error-codes'] ?? []
        ];
    }
}
