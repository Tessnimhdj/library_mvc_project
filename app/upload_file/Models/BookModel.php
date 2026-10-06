<?php

namespace app\upload_file\Models;
use Database;

include_once __DIR__ . '/../../../Config/Database.php';

class BookModel {
    private $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function beginTransaction() { return $this->db->beginTransaction(); }
    public function commit() { return $this->db->commit(); }

    public function rollBack() {
        if ($this->db->inTransaction()) {
            return $this->db->rollBack();
        }

        return false;
    }

    public function insertIfNotExists($inventory, $title, $author, $notes) {
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM books WHERE inventory_number = :inventory");
        $checkStmt->execute([':inventory' => $inventory]);
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            return false;
        }

        $stmt = $this->db->prepare("INSERT INTO books (inventory_number, title, author, notes)
            VALUES (:inventory, :title, :author, :notes)");

        $stmt->execute([
            ':inventory' => $inventory,
            ':title' => $title,
            ':author' => $author,
            ':notes' => $notes
        ]);

        return true;
    }
}
