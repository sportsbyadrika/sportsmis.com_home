<?php
namespace Core;

class Model
{
    protected static ?PDO $pdo = null;

    protected static function db(): \PDO
    {
        if (static::$pdo === null) {
            $cfg = require CONFIG_ROOT . '/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            static::$pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        }
        return static::$pdo;
    }

    protected static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected static function row(string $sql, array $params = []): ?array
    {
        return static::query($sql, $params)->fetch() ?: null;
    }

    protected static function rows(string $sql, array $params = []): array
    {
        return static::query($sql, $params)->fetchAll();
    }

    protected static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_keys($data));
        $plc  = implode(',', array_fill(0, count($data), '?'));
        static::query("INSERT INTO {$table} ({$cols}) VALUES ({$plc})", array_values($data));
        return (int) static::db()->lastInsertId();
    }

    protected static function update(string $table, array $data, array $where): int
    {
        $set  = implode(',', array_map(fn($k) => "{$k}=?", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($k) => "{$k}=?", array_keys($where)));
        $stmt = static::query(
            "UPDATE {$table} SET {$set} WHERE {$cond}",
            [...array_values($data), ...array_values($where)]
        );
        return $stmt->rowCount();
    }
}
