<?php

namespace app\books\Controllers;

use app\books\Models\BookCatalogModel;

/**
 * Shows the public read-only book list and one book.
 */
class BookController
{
    private const PER_PAGE = 20;

    /**
     * Lists books for the requested search and page.
     */
    public function index()
    {
        // قبول طلبات GET فقط
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // قراءة البحث ورقم الصفحة
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q, 'UTF-8') > 100) {
            $q = mb_substr($q, 0, 100, 'UTF-8');
        }

        $page = (int) ($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $books = [];
        $total = 0;
        $total_pages = 1;
        $error_msg = '';

        try {
            // حساب الصفحات ثم جلب الصفحة الحالية
            $catalog = new BookCatalogModel();
            $total = $catalog->count($q);
            $total_pages = $total > 0 ? (int) ceil($total / self::PER_PAGE) : 1;

            if ($page > $total_pages) {
                $page = $total_pages;
            }

            $offset = ($page - 1) * self::PER_PAGE;
            $books = $catalog->search($q, self::PER_PAGE, $offset);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
            }
            $books = [];
            $total = 0;
            $total_pages = 1;
            $page = 1;
            $error_msg = 'Something went wrong. Please try again.';
        }

        include __DIR__ . '/../Views/index.php';
    }

    /**
     * Shows one book, or a not-found page when the id is missing.
     */
    public function show()
    {
        // قبول طلبات GET فقط
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // قراءة سياق القائمة ومعرف الكتاب
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q, 'UTF-8') > 100) {
            $q = mb_substr($q, 0, 100, 'UTF-8');
        }

        $page = (int) ($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $book = null;
        $error_msg = '';

        if ($id < 1) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            include __DIR__ . '/../Views/show.php';
            return;
        }

        try {
            // جلب الكتاب أو اعتباره غير موجود
            $catalog = new BookCatalogModel();
            $book = $catalog->find($id);
            if ($book === null && !headers_sent()) {
                http_response_code(404);
            }
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
            }
            $book = null;
            $error_msg = 'Something went wrong. Please try again.';
        }

        include __DIR__ . '/../Views/show.php';
    }
}
