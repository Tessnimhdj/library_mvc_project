<?php

namespace Services\Recaptcha;

class RecaptchaService
{
    private $secretKey;
    private $siteKey;
    private $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = require __DIR__ . '/recaptcha.config.php';
        }

        $this->secretKey = $config['secret_key'];
        $this->siteKey = $config['site_key'];
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

    public function isValid($response, $remoteIp = null)
    {
        $result = $this->verify($response, $remoteIp);
        return $result['success'] === true;
    }

    public function getSiteKey()
    {
        return $this->siteKey;
    }

    public function verifyWithScore($response, $minScore = 0.5, $remoteIp = null)
    {
        $result = $this->verify($response, $remoteIp);

        if (!$result['success']) {
            return $result;
        }

        $score = $result['score'] ?? 0;

        if ($score < $minScore) {
            return [
                'success' => false,
                'error' => 'Score is too low. The request may be from a bot',
                'score' => $score
            ];
        }

        return $result;
    }
}
