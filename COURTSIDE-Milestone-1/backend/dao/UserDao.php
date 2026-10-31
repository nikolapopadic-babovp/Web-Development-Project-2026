<?php
require_once __DIR__ . '/BaseDao.php';

class UserDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        // The default SELECT hides password_hash so it is never returned by accident.
        parent::__construct(
            'users',
            'user_id',
            ['full_name', 'email', 'password_hash', 'role'],
            'user_id, full_name, email, role, created_at',
            $pdo
        );
    }

    
/**
 * Only admin and player roles are allowed.
 */
private function validateRole(array $data): void
{
    if (
        isset($data['role']) &&
        !in_array($data['role'], ['admin', 'player'], true)
    ) {
        throw new InvalidArgumentException(
            'Invalid user role. Allowed roles: admin, player.'
        );
    }
}

/**
 * Create a user after validating the role.
 */
public function insert(array $data): int
{
    $this->validateRole($data);

    return parent::insert($data);
}

/**
 * Update a user after validating the role.
 */
public function update(int $id, array $data): int
{
    $this->validateRole($data);

    return parent::update($id, $data);
}


    /** Used by the login flow (later milestone): the only method that returns password_hash. */
    public function getByEmailWithHash(string $email): ?array
    {
        return $this->queryOne('SELECT * FROM users WHERE email = :email', [':email' => $email]);
    }

    public function emailExists(string $email): bool
    {
        return $this->queryOne('SELECT 1 AS found FROM users WHERE email = :email', [':email' => $email]) !== null;
    }

    public function getByRole(string $role): array
    {
        return $this->query(
            'SELECT user_id, full_name, email, role, created_at FROM users WHERE role = :role ORDER BY full_name',
            [':role' => $role]
        );
    }
}
