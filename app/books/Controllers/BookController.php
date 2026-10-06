<?php

namespace app\books\Controllers;

use app\books\Models\BookCatalogModel;
use Core\Log;
use Core\View;

class BookController
{
    private const PER_PAGE = 20;

    public function index()
    {
        if ($this->rejectUnlessGet()) {
            return;
        }

        [$q, $page] = $this->listQuery();
        $books = [];
        $total = 0;
        $total_pages = 1;

        try {
            $catalog = new BookCatalogModel();
            $total = $catalog->count($q);
            $total_pages = $total > 0 ? (int) ceil($total / self::PER_PAGE) : 1;

            if ($page > $total_pages) {
                $page = $total_pages;
            }

            $offset = ($page - 1) * self::PER_PAGE;
            $books = $catalog->search($q, self::PER_PAGE, $offset);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            View::renderError(500, 'Something went wrong. Please try again.');
            return;
        }

        View::render(__DIR__ . '/../Views/index.php', [
            'books' => $books,
            'q' => $q,
            'page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
        ], [
            'nav' => 'books',
            'status' => 200,
        ]);
    }

    public function show()
    {
        if ($this->rejectUnlessGet()) {
            return;
        }

        [$q, $page] = $this->listQuery();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id < 1) {
            View::renderError(404, 'Book not found.');
            return;
        }

        try {
            $catalog = new BookCatalogModel();
            $book = $catalog->find($id);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            View::renderError(500, 'Something went wrong. Please try again.');
            return;
        }

        if ($book === null) {
            View::renderError(404, 'Book not found.');
            return;
        }

        View::render(__DIR__ . '/../Views/show.php', [
            'book' => $book,
            'q' => $q,
            'page' => $page,
        ], [
            'nav' => 'books',
            'status' => 200,
        ]);
    }

    private function rejectUnlessGet(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            return false;
        }

        header('Allow: GET');
        View::renderError(405, 'Method Not Allowed');

        return true;
    }

    private function listQuery(): array
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q, 'UTF-8') > 100) {
            $q = mb_substr($q, 0, 100, 'UTF-8');
        }

        $page = (int) ($_GET['page'] ?? 1);
        if ($page < 1) {
            $page = 1;
        }

        return [$q, $page];
    }
}
