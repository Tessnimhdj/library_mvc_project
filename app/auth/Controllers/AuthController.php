<?php

namespace app\auth\Controllers;

use app\auth\Models\UserModel;
use Services\Auth\AuthService;
use Services\Recaptcha\RecaptchaHelper;
use Services\Recaptcha\RecaptchaService;

class AuthController
{
    public function index()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        try {
            if (AuthService::check()) {
                header('Location: ' . \Router::url('/upload'), true, 302);
                exit;
            }

            $errorMsg = $_SESSION['error_msg'] ?? '';
            unset($_SESSION['error_msg']);
            $this->renderLogin((string) $errorMsg);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $this->showServerError();
        }
    }

    public function login()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        try {
            $recaptchaService = new RecaptchaService();
            $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
            $recaptchaResult = $recaptchaService->verify($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);

            if (!$recaptchaResult['success']) {
                error_log('reCAPTCHA rejected the login: ' . ($recaptchaResult['error'] ?? 'unknown'));
                $this->redirectWithError('Verification failed. Please complete the check.');
            }

            $username = trim((string) ($_POST['username'] ?? ''));
            $password = $_POST['password'] ?? '';

            if (
                $username === ''
                || mb_strlen($username, 'UTF-8') > 64
                || !is_string($password)
                || $password === ''
                || mb_strlen($password, 'UTF-8') > 1024
            ) {
                $this->redirectWithError('Invalid username or password.');
            }

            $user = (new UserModel())->verifyCredentials($username, $password);
            if ($user === null) {
                $this->redirectWithError('Invalid username or password.');
            }

            AuthService::login((int) $user['id'], (string) $user['username'], (string) $user['role']);
            (new UserModel())->touchLastLogin((int) $user['id']);
            header('Location: ' . \Router::url('/upload'), true, 302);
            exit;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $this->showServerError();
        }
    }

    public function logout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        try {
            AuthService::logout();
            header('Location: ' . \Router::url('/auth'), true, 302);
            exit;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $this->showServerError();
        }
    }

    private function redirectWithError(string $message): void
    {
        AuthService::startSession();
        $_SESSION['error_msg'] = $message;
        header('Location: ' . \Router::url('/auth'), true, 302);
        exit;
    }

    private function renderLogin(string $errorMsg): void
    {
        $helper = new RecaptchaHelper();
        $recaptchaScript = $helper->renderScript('en');
        $recaptchaWidget = $helper->renderV2();
        $formAction = \Router::url('/auth/login');
        include __DIR__ . '/../Views/login.php';
    }

    private function showServerError(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $this->renderLogin('Something went wrong. Please try again.');
    }
}
