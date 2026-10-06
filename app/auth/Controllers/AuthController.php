<?php

namespace app\auth\Controllers;

use app\auth\Models\UserModel;
use Services\Auth\AuthService;
use Services\Recaptcha\RecaptchaHelper;
use Services\Recaptcha\RecaptchaService;

/**
 * Signs staff in and out. This controller does not register accounts.
 */
class AuthController
{
    /**
     * Shows the login form, or sends an existing session to the upload page.
     */
    public function index()
    {
        // قبول طلبات GET فقط
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        try {
            // تخطي النموذج إذا كانت الجلسة صالحة
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

    /**
     * Checks reCAPTCHA and the password, then starts a staff session.
     */
    public function login()
    {
        // قبول طلبات POST فقط
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        try {
            // التحقق من reCAPTCHA قبل فحص بيانات الدخول
            $recaptchaService = new RecaptchaService();
            $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
            $recaptchaResult = $recaptchaService->verify($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);

            if (!$recaptchaResult['success']) {
                error_log('reCAPTCHA rejected the login: ' . ($recaptchaResult['error'] ?? 'unknown'));
                $this->redirectWithError('Verification failed. Please complete the check.');
            }

            // قبول اسم قصير وكلمة مرور غير مقصوصة
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

            // فتح الجلسة ثم الانتقال إلى صفحة الرفع
            AuthService::login((int) $user['id'], (string) $user['username'], (string) $user['role']);
            (new UserModel())->touchLastLogin((int) $user['id']);
            header('Location: ' . \Router::url('/upload'), true, 302);
            exit;
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            $this->showServerError();
        }
    }

    /**
     * Ends the staff session.
     */
    public function logout()
    {
        // قبول طلبات POST فقط
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

    /**
     * Stores a generic login error and returns to the login page.
     */
    private function redirectWithError(string $message): void
    {
        AuthService::startSession();
        $_SESSION['error_msg'] = $message;
        header('Location: ' . \Router::url('/auth'), true, 302);
        exit;
    }

    /**
     * Renders the login form.
     */
    private function renderLogin(string $errorMsg): void
    {
        $helper = new RecaptchaHelper();
        $recaptchaScript = $helper->renderScript('en');
        $recaptchaWidget = $helper->renderV2();
        $formAction = \Router::url('/auth/login');
        include __DIR__ . '/../Views/login.php';
    }

    /**
     * Shows a generic failure and hides database or path details.
     */
    private function showServerError(): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $this->renderLogin('Something went wrong. Please try again.');
    }
}
