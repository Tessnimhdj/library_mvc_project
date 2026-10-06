<?php

namespace app\auth\Controllers;

use app\auth\Models\UserModel;
use Core\Log;
use Core\View;
use Services\Auth\AuthService;
use Services\Recaptcha\RecaptchaHelper;
use Services\Recaptcha\RecaptchaService;

class AuthController
{
    public function index()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            View::renderError(405, 'Method Not Allowed');
            return;
        }

        try {
            if (AuthService::check()) {
                header('Location: ' . \Router::url('/upload'), true, 302);
                exit;
            }

            $errorMsg = $_SESSION['error_msg'] ?? '';
            unset($_SESSION['error_msg']);
            $this->renderLogin((string) $errorMsg, 200);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->showServerError();
        }
    }

    public function login()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            View::renderError(405, 'Method Not Allowed');
            return;
        }

        try {
            $recaptchaService = new RecaptchaService();
            $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
            $recaptchaResult = $recaptchaService->verify($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);

            if (!$recaptchaResult['success']) {
                Log::error('reCAPTCHA rejected the login: ' . ($recaptchaResult['error'] ?? 'unknown'));
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

            $users = new UserModel();
            $user = $users->verifyCredentials($username, $password);
            if ($user === null) {
                $this->redirectWithError('Invalid username or password.');
            }

            AuthService::login((int) $user['id'], (string) $user['username'], (string) $user['role']);
            $users->touchLastLogin((int) $user['id']);
            header('Location: ' . \Router::url('/upload'), true, 302);
            exit;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            $this->showServerError();
        }
    }

    public function logout()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            View::renderError(405, 'Method Not Allowed');
            return;
        }

        try {
            AuthService::logout();
            header('Location: ' . \Router::url('/auth'), true, 302);
            exit;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
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

    private function renderLogin(string $errorMsg, int $status): void
    {
        $helper = new RecaptchaHelper();
        View::render(__DIR__ . '/../Views/login.php', [
            'errorMsg' => $errorMsg,
            'recaptchaScript' => $helper->renderScript('en'),
            'recaptchaWidget' => $helper->renderV2(),
        ], [
            'nav' => 'auth',
            'status' => $status,
        ]);
    }

    private function showServerError(): void
    {
        View::renderError(500, 'Something went wrong. Please try again.');
    }
}
