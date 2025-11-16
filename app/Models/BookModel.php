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

    public function insertIfNotExists($inventory, $title, $author, $notes) {
        try {
            // تحقق أولاً إذا كانت القيمة موجودة
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM books WHERE inventory_number = :inventory");
            $checkStmt->execute([':inventory' => $inventory]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                // القيمة موجودة مسبقاً، لا ندخلها مرة أخرى
                return false;
            }

            // إذا لم توجد، نقوم بالإدخال
            $stmt = $this->db->prepare("INSERT INTO books (inventory_number, title, author, notes)
                VALUES (:inventory, :title, :author, :notes)");

            $result = $stmt->execute([
                ':inventory' => $inventory,
                ':title' => $title,
                ':author' => $author,
                ':notes' => $notes
            ]);

            return $result;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
