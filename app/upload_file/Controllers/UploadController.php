<?php

namespace app\upload_file\Controllers;

use app\upload_file\Models\BookModel;
use Services\Recaptcha\RecaptchaHelper;
use Services\Recaptcha\RecaptchaService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../Config/Config.php';

class UploadController {
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    private $bookModel;

    public function __construct() {
        $this->bookModel = new BookModel();
    }

    public function index() {
        $successMsg = $_SESSION['success_msg'] ?? '';
        $errorMsg = $_SESSION['error_msg'] ?? '';
        unset($_SESSION['success_msg'], $_SESSION['error_msg'], $_SESSION['err_msg']);

        $helper = new RecaptchaHelper();
        $recaptchaScript = $helper->renderScript('en');
        $recaptchaWidget = $helper->renderV2();
        $formAction = \Router::url('/upload/import');

        include __DIR__ . '/../Views/upload.php';
    }

    /**
     * Imports a posted xlsx file after reCAPTCHA and file checks.
     * The upload view reads success_msg and error_msg from the session.
     */
    public function import() {
        // قبول طلبات POST فقط
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $redirectUrl = \Router::url('/');

        // التحقق من reCAPTCHA قبل فحص الملف
        $recaptchaService = new RecaptchaService();
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        $recaptchaResult = $recaptchaService->verify($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);

        if (!$recaptchaResult['success']) {
            $this->logError('reCAPTCHA rejected the request: ' . ($recaptchaResult['error'] ?? 'unknown'));
            $this->redirectWithError($redirectUrl, 'Verification failed. Please complete the check.');
        }

        // التحقق من وجود الملف ورمز الخطأ
        if (!isset($_FILES['input_file']) || !is_array($_FILES['input_file'])) {
            $this->logError('Upload missing input_file');
            $this->redirectWithError($redirectUrl, 'Please select a file.');
        }

        $file = $_FILES['input_file'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->logError('Upload error code: ' . $uploadError);
            $this->redirectWithError($redirectUrl, $this->uploadErrorMessage($uploadError));
        }

        // التأكد أن الملف مرفوع عبر HTTP
        $tmpName = $file['tmp_name'] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            $this->logError('Upload rejected because tmp_name is not an uploaded file');
            $this->redirectWithError($redirectUrl, 'The file could not be uploaded.');
        }

        // التحقق من الحجم
        $size = (int) ($file['size'] ?? 0);
        if ($size > self::MAX_FILE_SIZE) {
            $this->logError('Upload rejected because the file exceeds 5 MB');
            $this->redirectWithError($redirectUrl, 'The file is too large. The maximum size is 5 MB.');
        }

        // قبول امتداد xlsx فقط
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $this->logError('Upload rejected because the extension is not xlsx');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        // التحقق من نوع المحتوى
        if (!$this->hasAllowedMimeType($tmpName)) {
            $this->logError('Upload rejected because the MIME type is not an xlsx workbook');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        $uploadPath = null;

        try {
            // حفظ الملف باسم عشوائي
            $uploadPath = UPLOAD_DIR . bin2hex(random_bytes(16)) . '.xlsx';
            if (!move_uploaded_file($tmpName, $uploadPath)) {
                $this->logError('move_uploaded_file failed');
                $_SESSION['error_msg'] = 'The file could not be saved.';
            } else {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($uploadPath);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($uploadPath);
                $rows = $spreadsheet->getActiveSheet()->toArray();
                array_shift($rows);

                $added = 0;
                $skipped = 0;

                $this->bookModel->beginTransaction();
                try {
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $inventory = trim((string) ($row[0] ?? ''));
                        $title = trim((string) ($row[1] ?? ''));
                        $author = trim((string) ($row[2] ?? ''));
                        $notes = trim((string) ($row[3] ?? ''));

                        if ($inventory === '' && $title === '' && $author === '' && $notes === '') {
                            continue;
                        }

                        if ($inventory === '') {
                            $skipped++;
                            continue;
                        }

                        if ($this->bookModel->insertIfNotExists($inventory, $title, $author, $notes)) {
                            $added++;
                        } else {
                            $skipped++;
                        }
                    }

                    $this->bookModel->commit();
                } catch (\Throwable $e) {
                    $this->bookModel->rollBack();
                    throw $e;
                }

                if ($added === 0 && $skipped === 0) {
                    $_SESSION['error_msg'] = 'The file has no rows to import.';
                } else {
                    $_SESSION['success_msg'] = "Import complete: {$added} added, {$skipped} skipped.";
                }
            }
        } catch (\Throwable $e) {
            $this->logError('Import error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'The Excel file could not be read or the data could not be saved.';
        } finally {
            // حذف الملف المؤقت دائمًا
            if (is_string($uploadPath) && is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Returns a generic message for a PHP upload error code.
     */
    private function uploadErrorMessage(int $code): string
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'The file is too large. The maximum size is 5 MB.';
        }

        if ($code === UPLOAD_ERR_NO_FILE) {
            return 'Please select a file.';
        }

        return 'The file could not be uploaded.';
    }

    /**
     * Checks the uploaded file content against the allowed xlsx MIME types.
     */
    private function hasAllowedMimeType(string $tmpPath): bool
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        return is_string($mime) && in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }

    /**
     * Stores a technical message in the server log.
     */
    private function logError(string $message): void
    {
        error_log($message);

        $logFile = __DIR__ . '/../../../Core/logs/errors.log';
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Redirects back to the upload form with a generic error message.
     */
    private function redirectWithError(string $redirectUrl, string $message): void
    {
        $_SESSION['error_msg'] = $message;
        header('Location: ' . $redirectUrl);
        exit;
    }
}
