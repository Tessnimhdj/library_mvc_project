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
    private const MAX_ROWS = 20000;
    private const READ_CHUNK = 1000;
    private const FAILED_REPORT_LIMIT = 50;

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
        $import_report = $_SESSION['import_report'] ?? null;
        unset($_SESSION['success_msg'], $_SESSION['error_msg'], $_SESSION['err_msg'], $_SESSION['import_report']);

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
            $this->logError('reCAPTCHA rejected the request: ' . ($recaptchaResult['error'] ?? 'unknown'));
            $this->redirectWithError($redirectUrl, 'Verification failed. Please complete the check.');
        }

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

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            $this->logError('Upload rejected because tmp_name is not an uploaded file');
            $this->redirectWithError($redirectUrl, 'The file could not be uploaded.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > self::MAX_FILE_SIZE) {
            $this->logError('Upload rejected because the file exceeds 5 MB');
            $this->redirectWithError($redirectUrl, 'The file is too large. The maximum size is 5 MB.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $this->logError('Upload rejected because the extension is not xlsx');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        if (!$this->hasAllowedMimeType($tmpName)) {
            $this->logError('Upload rejected because the MIME type is not an xlsx workbook');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        $uploadPath = null;

        try {
            $uploadPath = UPLOAD_DIR . bin2hex(random_bytes(16)) . '.xlsx';
            if (!move_uploaded_file($tmpName, $uploadPath)) {
                $this->logError('move_uploaded_file failed');
                $_SESSION['error_msg'] = 'The file could not be saved.';
            } else {
                $report = $this->importWorkbook($uploadPath);
                if ($report === null) {
                    $_SESSION['error_msg'] = 'The file has too many rows.';
                } elseif ($report['added'] === 0 && $report['skipped'] === 0 && $report['failed_count'] === 0) {
                    $_SESSION['error_msg'] = 'The file has no rows to import.';
                } else {
                    $message = "Import complete: {$report['added']} added, {$report['skipped']} skipped.";
                    if ($report['failed_count'] > 0) {
                        $message .= ", {$report['failed_count']} failed";
                    }
                    $_SESSION['success_msg'] = $message;
                    $_SESSION['import_report'] = $report;
                }
            }
        } catch (\Throwable $e) {
            $this->logError('Import error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'The Excel file could not be read or the data could not be saved.';
        } finally {
            if (is_string($uploadPath) && is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    private function importWorkbook(string $path): ?array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalRows = (int) ($worksheetInfo[0]['totalRows'] ?? 0);

        if ($totalRows > self::MAX_ROWS) {
            $this->logError('Import rejected because the worksheet has too many rows');
            return null;
        }

        $report = [
            'added' => 0,
            'skipped' => 0,
            'failed' => [],
            'failed_count' => 0,
        ];

        for ($startRow = 2; $startRow <= $totalRows; $startRow += self::READ_CHUNK) {
            $endRow = min($startRow + self::READ_CHUNK - 1, $totalRows);
            $rows = $this->readWorksheetWindow($reader, $path, $startRow, $endRow);
            $chunk = $this->bookModel->importRows($rows, $startRow);
            $this->mergeImportReport($report, $chunk);
            unset($rows, $chunk);
        }

        return $report;
    }

    private function readWorksheetWindow($reader, string $path, int $startRow, int $endRow): array
    {
        $reader->setReadFilter(new class($startRow, $endRow) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function __construct(private int $startRow, private int $endRow)
            {
            }

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= $this->startRow && $row <= $this->endRow;
            }
        });

        $spreadsheet = $reader->load($path);
        $rows = $spreadsheet->getActiveSheet()->rangeToArray(
            'A' . $startRow . ':D' . $endRow,
            null,
            false,
            false,
            false
        );

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    private function mergeImportReport(array &$report, array $chunk): void
    {
        $report['added'] += (int) ($chunk['added'] ?? 0);
        $report['skipped'] += (int) ($chunk['skipped'] ?? 0);
        $report['failed_count'] += (int) ($chunk['failed_count'] ?? 0);

        foreach ($chunk['failed'] ?? [] as $failure) {
            if (count($report['failed']) >= self::FAILED_REPORT_LIMIT) {
                break;
            }
            $report['failed'][] = $failure;
        }
    }

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

    private function hasAllowedMimeType(string $tmpPath): bool
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        return is_string($mime) && in_array($mime, self::ALLOWED_MIME_TYPES, true);
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

    private function redirectWithError(string $redirectUrl, string $message): void
    {
        $_SESSION['error_msg'] = $message;
        header('Location: ' . $redirectUrl);
        exit;
    }
}
