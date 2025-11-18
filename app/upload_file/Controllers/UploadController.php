<?php

namespace app\upload_file\Controllers;
use app\upload_file\Models\BookModel;


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


include_once __DIR__ . '/../Models/BookModel.php';
require_once __DIR__ . '/../../../vendor/autoload.php';     
require_once __DIR__ . '/../../../config/Config.php';        


class UploadController {
    private $bookModel;

    public function __construct() {
        $this->bookModel = new BookModel();
    }

    public function index() {
        $msg = $_SESSION['err_msg'] ?? '';
        unset($_SESSION['err_msg']);
        include __DIR__ . '/../Views/upload.php';
    }

    public function import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); exit("Method Not Allowed");
        }

        if (!isset($_FILES['input_file']) || $_FILES['input_file']['name'] === '') {
            $_SESSION['err_msg'] = "Please select a file.";
            header("Location: index.php"); exit;
        }

        $file = $_FILES['input_file'];

        if (!in_array($file['type'], ALLOWED_MIMES)) {
            $_SESSION['err_msg'] = "Invalid file type.";
            header("Location: index.php"); exit;
        }

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            $_SESSION['err_msg'] = "File too large. Max 5MB.";
            header("Location: index.php"); exit;
        }

       $upload_path = UPLOAD_DIR . time() . '_' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $_SESSION['err_msg'] = "Failed to upload file.";
            header("Location: index.php"); exit;
        }



        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        try {
            $spreadsheet = $reader->load($upload_path);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows);

            $this->bookModel->beginTransaction();
            foreach ($rows as $row) {
                $inventory = trim($row[0] ?? '');
                if ($inventory === '') continue;

                $this->bookModel->insertIfNotExists(
                    $inventory,
                    trim($row[1] ?? ''),
                    trim($row[2] ?? ''),
                    trim($row[3] ?? '')
                );
            }
            $this->bookModel->commit();
            $_SESSION['err_msg'] = "Data imported successfully!";
        } catch (\Exception $e) {
            $this->bookModel->rollBack();
            error_log($e->getMessage());
            $_SESSION['err_msg'] = "Error reading Excel file.";
        } 
           header("Location: http://localhost/mes_projet/library_mvc_project/"); exit;


    }
}
