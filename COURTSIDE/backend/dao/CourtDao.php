<?php
require_once __DIR__ . '/BaseDao.php';

class CourtDao extends BaseDao
{
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(
            'courts',
            'court_id',
            ['name', 'address', 'city', 'latitude', 'longitude', 'surface_type', 'is_indoor', 'amenities', 'source', 'external_id'],
            '*',
            $pdo
        );
    }

    public function getByCity(string $city): array
    {
        return $this->query('SELECT * FROM courts WHERE city = :city ORDER BY name', [':city' => $city]);
    }

    /**
     * Search with optional filters: q (name/city text), city, surface_type, is_indoor.
     * Only the filters that are given are added to the WHERE clause; all values are bound.
     */
    public function search(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            // Real prepared statements do not allow the same named placeholder twice, so use three.
            $where[]       = '(name LIKE :q1 OR city LIKE :q2 OR address LIKE :q3)';
            $like          = '%' . $filters['q'] . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }
        if (!empty($filters['city'])) {
            $where[]         = 'city = :city';
            $params[':city'] = $filters['city'];
        }
        if (!empty($filters['surface_type'])) {
            $where[]            = 'surface_type = :surface_type';
            $params[':surface_type'] = $filters['surface_type'];
        }
        if (isset($filters['is_indoor']) && $filters['is_indoor'] !== '') {
            $where[]             = 'is_indoor = :is_indoor';
            $params[':is_indoor'] = (int) $filters['is_indoor'];
        }

        $sql = 'SELECT * FROM courts' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY name';
        return $this->query($sql, $params);
    }

    /** Find a court imported from an external provider (avoids duplicate imports). */
    public function getByExternalId(string $source, string $externalId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM courts WHERE source = :source AND external_id = :external_id',
            [':source' => $source, ':external_id' => $externalId]
        );
    }
}
