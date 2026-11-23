<?php
class CourtService extends BaseService
{
    public function __construct(?CourtDao $dao = null)
    {
        parent::__construct($dao ?? new CourtDao());
    }

    protected function validate(array $data, bool $creating): array
    {
        $allowed = ['name', 'address', 'city', 'latitude', 'longitude', 'surface_type', 'is_indoor', 'amenities', 'source', 'external_id'];
        Validation::fields($data, $allowed, $creating ? ['name', 'city'] : []);
        $clean = [];
        foreach (['name' => 150, 'city' => 100] as $field => $size) {
            if (array_key_exists($field, $data)) $clean[$field] = Validation::text($data[$field], $field, $size);
        }
        foreach (['address' => 255, 'amenities' => 3000, 'external_id' => 100] as $field => $size) {
            if (array_key_exists($field, $data)) $clean[$field] = Validation::text($data[$field], $field, $size, true);
        }
        if (array_key_exists('surface_type', $data)) {
            $clean['surface_type'] = Validation::oneOf($data['surface_type'], 'surface_type', ['asphalt', 'concrete', 'rubber', 'wood', 'other']);
        }
        if (array_key_exists('source', $data)) {
            $clean['source'] = Validation::oneOf($data['source'], 'source', ['manual', 'sample', 'maplark']);
        }
        if (array_key_exists('is_indoor', $data)) $clean['is_indoor'] = Validation::boolean($data['is_indoor'], 'is_indoor');
        if (array_key_exists('latitude', $data)) {
            $clean['latitude'] = $data['latitude'] === null ? null : Validation::number($data['latitude'], 'latitude', -90.0, 90.0);
        }
        if (array_key_exists('longitude', $data)) {
            $clean['longitude'] = $data['longitude'] === null ? null : Validation::number($data['longitude'], 'longitude', -180.0, 180.0);
        }
        if ($creating && !array_key_exists('source', $clean)) $clean['source'] = 'manual';
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        $this->checkCoordinates($data);
        $this->checkExternalId($data);
    }

    protected function beforeUpdate(int $id, array $data, array $existing): void
    {
        $merged = array_merge($existing, $data);
        $this->checkCoordinates($merged);
        $this->checkExternalId($merged, $id);
    }

    private function checkCoordinates(array $data): void
    {
        if (($data['latitude'] ?? null) === null xor ($data['longitude'] ?? null) === null) {
            throw new ServiceException('latitude and longitude must both be provided or both be null.');
        }
    }

    private function checkExternalId(array $data, ?int $currentId = null): void
    {
        if (($data['external_id'] ?? null) === null) return;
        $found = $this->dao->getByExternalId($data['source'] ?? 'sample', $data['external_id']);
        if ($found && (int) $found['court_id'] !== $currentId) {
            throw new ServiceException('This external court has already been added.', 409);
        }
    }

    /** Optional search filters, still called through a service (not the route). */
    public function search(array $query, int $limit = 50, int $offset = 0): array
    {
        $filters = [];
        foreach (['q' => 150, 'city' => 100] as $field => $size) {
            if (isset($query[$field]) && $query[$field] !== '') {
                $filters[$field] = Validation::text($query[$field], $field, $size);
            }
        }
        if (isset($query['surface_type']) && $query['surface_type'] !== '') {
            $filters['surface_type'] = Validation::oneOf($query['surface_type'], 'surface_type', ['asphalt', 'concrete', 'rubber', 'wood', 'other']);
        }
        if (isset($query['is_indoor']) && $query['is_indoor'] !== '') {
            $filters['is_indoor'] = Validation::boolean($query['is_indoor'], 'is_indoor');
        }
        if (!$filters) return $this->getAll($limit, $offset);
        return array_slice($this->dao->search($filters), $offset, $limit);
    }
}
