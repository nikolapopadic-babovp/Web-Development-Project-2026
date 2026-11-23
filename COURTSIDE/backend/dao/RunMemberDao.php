<?php
require_once __DIR__ . '/BaseDao.php';

class RunMemberDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(
            'run_members',
            'run_member_id',
            ['run_id', 'user_id', 'membership_status'],
            '*',
            $pdo
        );
    }

    /** Members of a run with their names. */
    public function getByRun(int $runId): array
    {
        return $this->query(
            'SELECT rm.*, u.full_name
               FROM run_members rm
               JOIN users u ON u.user_id = rm.user_id
              WHERE rm.run_id = :run_id
              ORDER BY rm.joined_at',
            [':run_id' => $runId]
        );
    }

    /** Runs a user took part in, with the run title and start time. */
    public function getByUser(int $userId): array
    {
        return $this->query(
            'SELECT rm.*, r.title, r.starts_at
               FROM run_members rm
               JOIN runs r ON r.run_id = rm.run_id
              WHERE rm.user_id = :user_id
              ORDER BY r.starts_at',
            [':user_id' => $userId]
        );
    }

    public function find(int $runId, int $userId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM run_members WHERE run_id = :run_id AND user_id = :user_id',
            [':run_id' => $runId, ':user_id' => $userId]
        );
    }

    /** How many players currently hold a place in the run. */
    public function countJoined(int $runId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM run_members WHERE run_id = :run_id AND membership_status = 'joined'",
            [':run_id' => $runId]
        );
        return (int) $row['total'];
    }

    /** Remove one user from one run. Returns the number of deleted rows. */
    public function deleteByRunAndUser(int $runId, int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM run_members WHERE run_id = :run_id AND user_id = :user_id');
        $stmt->execute([':run_id' => $runId, ':user_id' => $userId]);
        return $stmt->rowCount();
    }
}
