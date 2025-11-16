<?php
include_once __DIR__ . '/Database.php';

class BookModel {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function beginTransaction() { return $this->db->beginTransaction(); }
    public function commit() { return $this->db->commit(); }
    public function rollBack() { return $this->db->rollBack(); }

    public function insertOrUpdate($inventory, $title, $author, $notes) {
        try {
            $stmt = $this->db->prepare("INSERT INTO books (inventory_number, title, author, notes)
                VALUES (:inventory, :title, :author, :notes)
                ON DUPLICATE KEY UPDATE title = VALUES(title), author = VALUES(author), notes = VALUES(notes)");

            $stmt->execute([
                ':inventory' => $inventory,
                ':title' => $title,
                ':author' => $author,
                ':notes' => $notes
            ]);
            return true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
