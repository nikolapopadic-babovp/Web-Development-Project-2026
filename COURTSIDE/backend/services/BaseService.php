<?php
/** Base service: shared CRUD flow; child services supply entity validation and rules. */
abstract class BaseService
{
    protected BaseDao $dao;

    public function __construct(BaseDao $dao)
    {
        $this->dao = $dao;
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        return $this->dao->getAll($limit, $offset);
    }

    public function getById(int $id): array
    {
        $row = $this->dao->getById($id);
        if ($row === null) {
            throw new ServiceException('Resource not found.', 404);
        }
        return $row;
    }

    public function create(array $data): array
    {
        $clean = $this->validate($data, true);
        $this->beforeCreate($clean);
        $id = $this->dao->insert($clean);
        return $this->getById($id);
    }

    public function update(int $id, array $data): array
    {
        $existing = $this->getById($id);
        $clean = $this->validate($data, false);
        $this->beforeUpdate($id, $clean, $existing);
        $this->dao->update($id, $clean);
        return $this->getById($id);
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->dao->delete($id);
    }

    abstract protected function validate(array $data, bool $creating): array;

    protected function beforeCreate(array $data): void {}
    protected function beforeUpdate(int $id, array $data, array $existing): void {}
}
