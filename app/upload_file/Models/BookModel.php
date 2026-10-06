<?php

namespace app\upload_file\Models;
use Database;

include_once __DIR__ . '/../../../Config/Database.php';

class BookModel {
    private const CHUNK_SIZE = 500;
    private const FAILED_LIMIT = 50;
    private const MAX_INVENTORY_LENGTH = 64;
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_AUTHOR_LENGTH = 255;
    private const MAX_NOTES_BYTES = 65535;

    private $db;

    /**
     * Opens the shared PDO connection.
     */
    public function __construct() {
        $this->db = Database::connect();
    }

    /**
     * Starts a PDO transaction.
     */
    public function beginTransaction() { return $this->db->beginTransaction(); }

    /**
     * Commits the current PDO transaction.
     */
    public function commit() { return $this->db->commit(); }

    /**
     * Rolls back the current PDO transaction when one is open.
     */
    public function rollBack() {
        if ($this->db->inTransaction()) {
            return $this->db->rollBack();
        }

        return false;
    }

    /**
     * Inserts one book or reports an existing inventory number.
     * Returns true when the row is new and false when it already exists.
     */
    public function insertIfNotExists($inventory, $title, $author, $notes) {
        $statement = $this->insertStatement();
        $statement->execute([
            ':inventory' => $inventory,
            ':title' => $title,
            ':author' => $author,
            ':notes' => $notes,
        ]);

        return $statement->rowCount() === 1;
    }

    /**
     * Imports rows in chunks and returns added, skipped, and failed counts.
     * $rows contains data rows only. $firstExcelRow is the worksheet number of the first data row.
     * This method starts its own transactions and must not be called inside an open transaction.
     *
     * @return array{added: int, skipped: int, failed: array<int, array{row: int, reason: string}>, failed_count: int}
     */
    public function importRows(array $rows, int $firstExcelRow = 2): array
    {
        $result = [
            'added' => 0,
            'skipped' => 0,
            'failed' => [],
            'failed_count' => 0,
        ];

        // التحقق من الصفوف قبل الإدخال
        $pending = [];
        foreach ($rows as $offset => $row) {
            $excelRow = $firstExcelRow + (int) $offset;
            $validation = $this->validateRow($row);
            if ($validation['reason'] !== null) {
                $this->recordFailure($result, $excelRow, $validation['reason']);
                continue;
            }

            $pending[] = [
                'row' => $excelRow,
                'data' => $validation['data'],
            ];
        }

        if ($pending === []) {
            return $result;
        }

        // تجهيز جملة واحدة تعاد لكل الصفوف
        $statement = $this->insertStatement();

        // إدخال كل 500 صف في معاملة مستقلة
        foreach (array_chunk($pending, self::CHUNK_SIZE) as $chunk) {
            $this->insertChunk($statement, $chunk, $result);
        }

        return $result;
    }

    /**
     * Trims a row and rejects missing or oversized values.
     *
     * @return array{reason: ?string, data: array{inventory: string, title: string, author: string, notes: string}}
     */
    private function validateRow($row): array
    {
        $empty = [
            'inventory' => '',
            'title' => '',
            'author' => '',
            'notes' => '',
        ];

        if (!is_array($row)) {
            return ['reason' => 'invalid row', 'data' => $empty];
        }

        $inventory = trim((string) ($row[0] ?? ''));
        $title = trim((string) ($row[1] ?? ''));
        $author = trim((string) ($row[2] ?? ''));
        $notes = trim((string) ($row[3] ?? ''));

        if ($inventory === '') {
            return ['reason' => 'missing inventory number', 'data' => $empty];
        }

        if ($title === '') {
            return ['reason' => 'missing title', 'data' => $empty];
        }

        if (mb_strlen($inventory, 'UTF-8') > self::MAX_INVENTORY_LENGTH) {
            return ['reason' => 'inventory number is too long', 'data' => $empty];
        }

        if (mb_strlen($title, 'UTF-8') > self::MAX_TITLE_LENGTH) {
            return ['reason' => 'title is too long', 'data' => $empty];
        }

        if (mb_strlen($author, 'UTF-8') > self::MAX_AUTHOR_LENGTH) {
            return ['reason' => 'author is too long', 'data' => $empty];
        }

        if (strlen($notes) > self::MAX_NOTES_BYTES) {
            return ['reason' => 'notes are too long', 'data' => $empty];
        }

        return [
            'reason' => null,
            'data' => [
                'inventory' => $inventory,
                'title' => $title,
                'author' => $author,
                'notes' => $notes,
            ],
        ];
    }

    /**
     * Prepares the no-op duplicate insert once.
     */
    private function insertStatement(): \PDOStatement
    {
        return $this->db->prepare(
            'INSERT INTO books (inventory_number, title, author, notes)
            VALUES (:inventory, :title, :author, :notes)
            ON DUPLICATE KEY UPDATE id = id'
        );
    }

    /**
     * Inserts one chunk and classifies each row from the affected-row count.
     */
    private function insertChunk(\PDOStatement $statement, array $chunk, array &$result): void
    {
        $added = 0;
        $skipped = 0;

        try {
            $this->db->beginTransaction();

            foreach ($chunk as $item) {
                $statement->execute([
                    ':inventory' => $item['data']['inventory'],
                    ':title' => $item['data']['title'],
                    ':author' => $item['data']['author'],
                    ':notes' => $item['data']['notes'],
                ]);

                // 1 صف جديد، و0 صف مكرر لم يتغير
                if ($statement->rowCount() === 1) {
                    $added++;
                } else {
                    $skipped++;
                }
            }

            $this->db->commit();
            $result['added'] += $added;
            $result['skipped'] += $skipped;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log($e->getMessage());

            foreach ($chunk as $item) {
                $this->recordFailure($result, (int) $item['row'], 'could not save this row');
            }
        }
    }

    /**
     * Records a failed row without returning more than the first 50 details.
     */
    private function recordFailure(array &$result, int $row, string $reason): void
    {
        $result['failed_count']++;

        if (count($result['failed']) < self::FAILED_LIMIT) {
            $result['failed'][] = [
                'row' => $row,
                'reason' => $reason,
            ];
        }
    }
}
