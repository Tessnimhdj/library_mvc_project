<?php

namespace app\books\Controllers;

use app\books\Models\BookCatalogModel;

/**
 * Shows the public read-only book list.
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
}
