<?php
namespace Models;

use Core\Model;

class User extends Model
{
    public static function findByEmail(string $email): ?array
    {
        return static::row('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function findById(int $id): ?array
    {
        return static::row('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function create(string $email, string $password, string $role): int
    {
        return static::insert('users', [
            'email'    => $email,
            'password' => $password,
            'role'     => $role,
            'status'   => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateLastLogin(int $id): void
    {
        static::update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        static::update('users', ['password' => $hash], ['id' => $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        static::update('users', ['status' => $status], ['id' => $id]);
    }

    public static function storeResetToken(string $email, string $token): void
    {
        static::query(
            'INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()',
            [$email, $token]
        );
    }

    public static function findResetToken(string $token): ?array
    {
        return static::row(
            'SELECT * FROM password_resets WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)',
            [$token]
        );
    }

    public static function deleteResetToken(string $email): void
    {
        static::query('DELETE FROM password_resets WHERE email = ?', [$email]);
    }
}
