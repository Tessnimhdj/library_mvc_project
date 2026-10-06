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

    public function import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $redirectUrl = \Router::url('/');

        $recaptchaService = new RecaptchaService();
        $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
        $recaptchaResult = $recaptchaService->verify($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);

        if (!$recaptchaResult['success']) {
            $this->redirectWithError($redirectUrl, 'Verification failed: ' . ($recaptchaResult['error'] ?? 'Please complete the check'));
        }

        if (!isset($_FILES['input_file']) || !is_array($_FILES['input_file'])) {
            $this->redirectWithError($redirectUrl, 'Please select a file.');
        }

        $file = $_FILES['input_file'];
        $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($uploadError === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
            $this->redirectWithError($redirectUrl, 'Please select a file.');
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->redirectWithError($redirectUrl, 'The file could not be uploaded.');
        }

        if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
            $this->redirectWithError($redirectUrl, 'The file is too large. The maximum size is 5 MB.');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!$this->isAllowedSpreadsheet((string) $file['tmp_name'], $extension)) {
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx or xls Excel file.');
        }

        $uploadPath = UPLOAD_DIR . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $this->redirectWithError($redirectUrl, 'The file could not be saved.');
        }

        try {
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
        } catch (\Throwable $e) {
            $this->logError('Import error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'The Excel file could not be read or the data could not be saved.';
        } finally {
            if (is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    private function redirectWithError(string $redirectUrl, string $message): void
    {
        $_SESSION['error_msg'] = $message;
        header('Location: ' . $redirectUrl);
        exit;
    }

    private function isAllowedSpreadsheet(string $tmpPath, string $extension): bool
    {
        if (!in_array($extension, ['xlsx', 'xls'], true) || !is_file($tmpPath)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if ($mime === false) {
            return false;
        }

        if ($extension === 'xlsx') {
            $allowedMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/x-zip-compressed',
            ];
            if (!in_array($mime, $allowedMimes, true)) {
                return false;
            }

            if (!class_exists(\ZipArchive::class)) {
                return $mime === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }

            $zip = new \ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                return false;
            }
            $isWorkbook = $zip->locateName('xl/workbook.xml') !== false;
            $zip->close();

            return $isWorkbook;
        }

        $allowedXlsMimes = [
            'application/vnd.ms-excel',
            'application/x-ole-storage',
            'application/vnd.ms-office',
        ];
        if (!in_array($mime, $allowedXlsMimes, true)) {
            return false;
        }

        $header = file_get_contents($tmpPath, false, null, 0, 8);

        return $header !== false && str_starts_with($header, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }

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
}
