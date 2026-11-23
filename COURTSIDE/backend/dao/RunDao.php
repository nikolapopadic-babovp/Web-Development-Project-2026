<?php
require_once __DIR__ . '/BaseDao.php';

class RunDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(
            'runs',
            'run_id',
            ['court_id', 'creator_user_id', 'title', 'starts_at', 'max_players', 'skill_level', 'notes', 'status'],
            '*',
            $pdo
        );
    }

    public function getByCourt(int $courtId): array
    {
        return $this->query('SELECT * FROM runs WHERE court_id = :court_id ORDER BY starts_at', [':court_id' => $courtId]);
    }

    public function getByCreator(int $userId): array
    {
        return $this->query('SELECT * FROM runs WHERE creator_user_id = :user_id ORDER BY starts_at', [':user_id' => $userId]);
    }

    /** Open runs that have not started yet, with the court name for display. */
    public function getUpcoming(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.name AS court_name, c.city AS court_city
               FROM runs r
               JOIN courts c ON c.court_id = r.court_id
              WHERE r.status = 'open' AND r.starts_at >= UTC_TIMESTAMP()
              ORDER BY r.starts_at
              LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
