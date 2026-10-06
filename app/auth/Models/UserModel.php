<?php

namespace app\auth\Models;

use Database;

include_once __DIR__ . '/../../../Config/Database.php';

/**
 * Reads staff accounts and checks passwords. This model does not register users.
 */
class UserModel
{
    private const DUMMY_HASH = '$2y$10$6qGUIVAzRdzguW0d6./Hc.e2bVPpUZ7DKis7ptD074BILKNjTH9ZO';

    private $db;

    /**
     * Opens the shared PDO connection.
     */
    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Returns the user row for a username, including the password hash, or null.
     */
    public function findByUsername(string $username): ?array
    {
        try {
            // البحث عن المستخدم باسم الدخول
            $statement = $this->db->prepare(
                'SELECT id, username, password_hash, role, is_active, last_login_at, created_at
                FROM users
                WHERE username = :username
                LIMIT 1'
            );
            $statement->bindValue(':username', $username, \PDO::PARAM_STR);
            $statement->execute();
            $row = $statement->fetch();

            return $row === false ? null : $row;
        } catch (\PDOException $e) {
            $this->fail($e);
        }
    }

    /**
     * Returns the user without the password hash when the password matches an active account.
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        try {
            // التحقق من كلمة المرور حتى عندما لا يوجد المستخدم
            $row = $this->findByUsername($username);
            if ($row === null) {
                password_verify($password, self::DUMMY_HASH);
                return null;
            }

            $hash = (string) ($row['password_hash'] ?? '');
            $valid = password_verify($password, $hash);
            $active = (int) ($row['is_active'] ?? 0) === 1;

            if (!$valid || !$active) {
                return null;
            }

            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $this->updatePasswordHash((int) $row['id'], password_hash($password, PASSWORD_DEFAULT));
            }

            unset($row['password_hash']);
            return $row;
        } catch (\PDOException $e) {
            $this->fail($e);
        }
    }

    /**
     * Records the current time as the user's last successful login.
     */
    public function touchLastLogin(int $id): void
    {
        try {
            // تحديث وقت آخر دخول
            $statement = $this->db->prepare(
                'UPDATE users SET last_login_at = NOW() WHERE id = :id'
            );
            $statement->bindValue(':id', $id, \PDO::PARAM_INT);
            $statement->execute();
        } catch (\PDOException $e) {
            $this->fail($e);
        }
    }

    /**
     * Replaces the stored password hash after a successful verification.
     */
    private function updatePasswordHash(int $id, string $hash): void
    {
        $statement = $this->db->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        $statement->bindValue(':password_hash', $hash, \PDO::PARAM_STR);
        $statement->bindValue(':id', $id, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Logs the database error and throws a message that contains no SQL.
     */
    private function fail(\PDOException $e): never
    {
        error_log($e->getMessage());
        throw new \RuntimeException('The request could not be completed.');
    }
}
