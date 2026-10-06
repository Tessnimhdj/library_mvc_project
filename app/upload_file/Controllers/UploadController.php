<?php

namespace app\upload_file\Controllers;

use app\upload_file\Models\BookModel;
use Core\Log;
use Core\View;
use Services\Recaptcha\RecaptchaHelper;
use Services\Recaptcha\RecaptchaService;

class UploadController
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const MAX_ROWS = 20000;
    private const READ_CHUNK = 1000;
    private const FAILED_REPORT_LIMIT = 50;

    private const ALLOWED_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    private BookModel $bookModel;

    public function __construct()
    {
        $this->bookModel = new BookModel();
    }

    public function index(): void
    {
        $successMsg = $_SESSION['success_msg'] ?? '';
        $errorMsg = $_SESSION['error_msg'] ?? '';
        $importReport = $_SESSION['import_report'] ?? null;
        unset($_SESSION['success_msg'], $_SESSION['error_msg'], $_SESSION['err_msg'], $_SESSION['import_report']);

        $helper = new RecaptchaHelper();
        View::render(__DIR__ . '/../Views/upload.php', [
            'successMsg' => $successMsg,
            'errorMsg' => $errorMsg,
            'import_report' => $importReport,
            'recaptchaScript' => $helper->renderScript('en'),
            'recaptchaWidget' => $helper->renderV2(),
        ], [
            'nav' => 'upload',
            'status' => 200,
        ]);
    }

    public function import(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            View::renderError(405, 'Method Not Allowed');
            return;
        }

        require_once __DIR__ . '/../../../Config/Config.php';

        $redirectUrl = \Router::url('/upload');

        if (!$this->captchaAccepted()) {
            $this->redirectWithError($redirectUrl, 'Verification failed. Please complete the check.');
        }

        $tmpName = $this->acceptedUpload($redirectUrl);
        $uploadPath = null;

        try {
            $uploadPath = UPLOAD_DIR . bin2hex(random_bytes(16)) . '.xlsx';
            if (!move_uploaded_file($tmpName, $uploadPath)) {
                Log::error('move_uploaded_file failed');
                $_SESSION['error_msg'] = 'The file could not be saved.';
            } else {
                $this->storeImportResult($this->importWorkbook($uploadPath));
            }
        } catch (\Throwable $e) {
            Log::error('Import error: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'The Excel file could not be read or the data could not be saved.';
        } finally {
            if (is_string($uploadPath) && is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    private function captchaAccepted(): bool
    {
        $result = (new RecaptchaService())->verify(
            $_POST['g-recaptcha-response'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? null
        );

        if (!empty($result['success'])) {
            return true;
        }

        Log::error('reCAPTCHA rejected the request: ' . ($result['error'] ?? 'unknown'));

        return false;
    }

    private function acceptedUpload(string $redirectUrl): string
    {
        if (!isset($_FILES['input_file']) || !is_array($_FILES['input_file'])) {
            Log::error('Upload missing input_file');
            $this->redirectWithError($redirectUrl, 'Please select a file.');
        }

        $file = $_FILES['input_file'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError !== UPLOAD_ERR_OK) {
            Log::error('Upload error code: ' . $uploadError);
            $this->redirectWithError($redirectUrl, $this->uploadErrorMessage($uploadError));
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
            Log::error('Upload rejected because tmp_name is not an uploaded file');
            $this->redirectWithError($redirectUrl, 'The file could not be uploaded.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size > self::MAX_FILE_SIZE) {
            Log::error('Upload rejected because the file exceeds 5 MB');
            $this->redirectWithError($redirectUrl, 'The file is too large. The maximum size is 5 MB.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            Log::error('Upload rejected because the extension is not xlsx');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        if (!$this->hasAllowedMimeType($tmpName)) {
            Log::error('Upload rejected because the MIME type is not an xlsx workbook');
            $this->redirectWithError($redirectUrl, 'This file type is not allowed. Use an xlsx Excel file.');
        }

        return $tmpName;
    }

    private function storeImportResult(?array $report): void
    {
        if ($report === null) {
            $_SESSION['error_msg'] = 'The file has too many rows.';
            return;
        }

        if ($report['added'] === 0 && $report['skipped'] === 0 && $report['failed_count'] === 0) {
            $_SESSION['error_msg'] = 'The file has no rows to import.';
            return;
        }

        $message = "Import complete: {$report['added']} added, {$report['skipped']} skipped.";
        if ($report['failed_count'] > 0) {
            $message .= ", {$report['failed_count']} failed";
        }

        $_SESSION['success_msg'] = $message;
        $_SESSION['import_report'] = $report;
    }

    private function importWorkbook(string $path): ?array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $worksheetInfo = $reader->listWorksheetInfo($path);
        $totalRows = (int) ($worksheetInfo[0]['totalRows'] ?? 0);

        if ($totalRows > self::MAX_ROWS) {
            Log::error('Import rejected because the worksheet has too many rows');
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

    private function redirectWithError(string $redirectUrl, string $message): never
    {
        $_SESSION['error_msg'] = $message;
        header('Location: ' . $redirectUrl);
        exit;
    }
}
