<?php
require_once __DIR__ . '/Database.php';

/**
 * Reusable CRUD methods for every table.
 * Child classes only say which table, primary key and columns they own.
 *
 * Values are always sent through PDO prepared statements. Table and column names
 * cannot be bound, so they are never taken from user input: column names in
 * insert()/update() are filtered through the $columns whitelist of the child class.
 */
abstract class BaseDao
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey;
    /** @var string[] columns that may be written by insert()/update() */
    protected array $columns;
    /** columns returned by SELECT (a child can hide e.g. password_hash) */
    protected string $selectColumns;

    public function __construct(string $table, string $primaryKey, array $columns, string $selectColumns = '*', ?PDO $pdo = null)
    {
        $this->table         = $table;
        $this->primaryKey    = $primaryKey;
        $this->columns       = $columns;
        $this->selectColumns = $selectColumns;
        $this->pdo           = $pdo ?? Database::connect();
    }

    /** Read all rows (paged). */
    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $sql  = "SELECT {$this->selectColumns} FROM `{$this->table}` ORDER BY `{$this->primaryKey}` LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Read one row by primary key, or null if it does not exist. */
    public function getById(int $id): ?array
    {
        $sql  = "SELECT {$this->selectColumns} FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Create a row. Returns the new primary key. */
    public function insert(array $data): int
    {
        $data = $this->onlyAllowedColumns($data);
        if ($data === []) {
            throw new InvalidArgumentException('No valid columns to insert.');
        }

        $names        = array_keys($data);
        $columnList   = '`' . implode('`, `', $names) . '`';
        $placeholders = ':' . implode(', :', $names);

        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` ($columnList) VALUES ($placeholders)");
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    /** Update a row by primary key. Returns the number of matched rows (0 = id not found). */
    public function update(int $id, array $data): int
    {
        $data = $this->onlyAllowedColumns($data);
        if ($data === []) {
            throw new InvalidArgumentException('No valid columns to update.');
        }

        $assignments = [];
        foreach (array_keys($data) as $name) {
            $assignments[] = "`$name` = :$name";
        }

        $stmt = $this->pdo->prepare(
            "UPDATE `{$this->table}` SET " . implode(', ', $assignments) . " WHERE `{$this->primaryKey}` = :pk_id"
        );
        $stmt->execute($data + [':pk_id' => $id]);
        return $stmt->rowCount();
    }

    /** Delete a row by primary key. Returns the number of deleted rows (0 = id not found). */
    public function delete(int $id): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    /** Helper for child classes: run a prepared SELECT and return all rows. */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Helper for child classes: run a prepared SELECT and return one row or null. */
    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Keep only whitelisted columns (protects the generated SQL). */
    private function onlyAllowedColumns(array $data): array
    {
        return array_intersect_key($data, array_flip($this->columns));
    }
}
