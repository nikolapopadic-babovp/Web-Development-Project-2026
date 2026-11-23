<?php
require_once __DIR__ . '/BaseDao.php';

class SavedCourtDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(
            'saved_courts',
            'saved_court_id',
            ['user_id', 'court_id'],
            '*',
            $pdo
        );
    }

    /** A user's saved courts with the court details. */
    public function getByUser(int $userId): array
    {
        return $this->query(
            'SELECT sc.saved_court_id, sc.created_at AS saved_at, c.*
               FROM saved_courts sc
               JOIN courts c ON c.court_id = sc.court_id
              WHERE sc.user_id = :user_id
              ORDER BY sc.created_at DESC',
            [':user_id' => $userId]
        );
    }

    public function find(int $userId, int $courtId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM saved_courts WHERE user_id = :user_id AND court_id = :court_id',
            [':user_id' => $userId, ':court_id' => $courtId]
        );
    }

    /** Un-save a court. Returns the number of deleted rows. */
    public function deleteByUserAndCourt(int $userId, int $courtId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM saved_courts WHERE user_id = :user_id AND court_id = :court_id');
        $stmt->execute([':user_id' => $userId, ':court_id' => $courtId]);
        return $stmt->rowCount();
    }
}
