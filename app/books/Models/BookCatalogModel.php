<?php

namespace app\books\Models;

use Database;

include_once __DIR__ . '/../../../Config/Database.php';

/**
 * Reads books for the public catalog. This model does not write to the database.
 */
class BookCatalogModel
{
    private $db;

    /**
     * Opens the shared PDO connection.
     */
    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Returns one page of books, optionally filtered by title, author, or inventory number.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $q, int $limit, int $offset): array
    {
        // بناء الاستعلام مع نمط واحد للبحث
        $sql = 'SELECT id, inventory_number, title, author, notes
            FROM books';
        $params = [];

        if ($q !== '') {
            $sql .= ' WHERE title LIKE :title_pattern ESCAPE \'\\\\\'
                OR author LIKE :author_pattern ESCAPE \'\\\\\'
                OR inventory_number LIKE :inventory_pattern ESCAPE \'\\\\\'';
            $pattern = $this->likePattern($q);
            $params[':title_pattern'] = $pattern;
            $params[':author_pattern'] = $pattern;
            $params[':inventory_pattern'] = $pattern;
        }

        $sql .= ' ORDER BY title ASC, id ASC
            LIMIT :limit OFFSET :offset';

        $statement = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Counts books that match the same filter as search().
     */
    public function count(string $q): int
    {
        // عد الصفوف بنفس شرط البحث
        $sql = 'SELECT COUNT(*) FROM books';
        $params = [];

        if ($q !== '') {
            $sql .= ' WHERE title LIKE :title_pattern ESCAPE \'\\\\\'
                OR author LIKE :author_pattern ESCAPE \'\\\\\'
                OR inventory_number LIKE :inventory_pattern ESCAPE \'\\\\\'';
            $pattern = $this->likePattern($q);
            $params[':title_pattern'] = $pattern;
            $params[':author_pattern'] = $pattern;
            $params[':inventory_pattern'] = $pattern;
        }

        $statement = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Returns one book by its id, or null when no row matches.
     */
    public function find(int $id): ?array
    {
        // جلب كتاب واحد بالمعرف
        $statement = $this->db->prepare(
            'SELECT id, inventory_number, title, author, notes
            FROM books
            WHERE id = :id
            LIMIT 1'
        );
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Builds one LIKE pattern and escapes backslash, percent, and underscore.
     */
    private function likePattern(string $q): string
    {
        $escaped = str_replace('\\', '\\\\', $q);
        $escaped = str_replace('%', '\\%', $escaped);
        $escaped = str_replace('_', '\\_', $escaped);

        return '%' . $escaped . '%';
    }
}
