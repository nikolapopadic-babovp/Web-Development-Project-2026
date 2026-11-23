<?php
require_once __DIR__ . '/BaseDao.php';

class ReviewDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(
            'reviews',
            'review_id',
            ['user_id', 'court_id', 'rating', 'comment'],
            '*',
            $pdo
        );
    }

    /** Reviews of a court with the reviewer's name, newest first. */
    public function getByCourt(int $courtId): array
    {
        return $this->query(
            'SELECT rv.*, u.full_name
               FROM reviews rv
               JOIN users u ON u.user_id = rv.user_id
              WHERE rv.court_id = :court_id
              ORDER BY rv.created_at DESC',
            [':court_id' => $courtId]
        );
    }

    public function getByUser(int $userId): array
    {
        return $this->query(
            'SELECT rv.*, c.name AS court_name
               FROM reviews rv
               JOIN courts c ON c.court_id = rv.court_id
              WHERE rv.user_id = :user_id
              ORDER BY rv.created_at DESC',
            [':user_id' => $userId]
        );
    }

    public function find(int $userId, int $courtId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM reviews WHERE user_id = :user_id AND court_id = :court_id',
            [':user_id' => $userId, ':court_id' => $courtId]
        );
    }

    /** Average rating and number of reviews for a court. */
    public function getRatingSummary(int $courtId): array
    {
        $row = $this->queryOne(
            'SELECT COUNT(*) AS review_count, ROUND(AVG(rating), 1) AS average_rating FROM reviews WHERE court_id = :court_id',
            [':court_id' => $courtId]
        );
        return [
            'review_count'   => (int) $row['review_count'],
            'average_rating' => $row['average_rating'] === null ? null : (float) $row['average_rating'],
        ];
    }
}
