<?php

namespace app\books\Models;

use Database;

include_once __DIR__ . '/../../../Config/Database.php';

class BookCatalogModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function search(string $q, int $limit, int $offset): array
    {
        $params = [];
        $sql = $this->withSearchFilter(
            'SELECT id, inventory_number, title, author, notes
            FROM books',
            $q,
            $params
        );

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

    public function count(string $q): int
    {
        $params = [];
        $sql = $this->withSearchFilter('SELECT COUNT(*) FROM books', $q, $params);

        $statement = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function find(int $id): ?array
    {
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

    private function withSearchFilter(string $sql, string $q, array &$params): string
    {
        if ($q === '') {
            return $sql;
        }

        $pattern = $this->likePattern($q);
        $params[':title_pattern'] = $pattern;
        $params[':author_pattern'] = $pattern;
        $params[':inventory_pattern'] = $pattern;

        return $sql . ' WHERE title LIKE :title_pattern ESCAPE \'\\\\\'
            OR author LIKE :author_pattern ESCAPE \'\\\\\'
            OR inventory_number LIKE :inventory_pattern ESCAPE \'\\\\\'';
    }

    private function likePattern(string $q): string
    {
        $escaped = str_replace('\\', '\\\\', $q);
        $escaped = str_replace('%', '\\%', $escaped);
        $escaped = str_replace('_', '\\_', $escaped);

        return '%' . $escaped . '%';
    }
}
