<?php
class SavedCourtService extends BaseService
{
    private UserDao $users;
    private CourtDao $courts;

    public function __construct(?SavedCourtDao $dao = null, ?UserDao $users = null, ?CourtDao $courts = null)
    {
        parent::__construct($dao ?? new SavedCourtDao());
        $this->users = $users ?? new UserDao();
        $this->courts = $courts ?? new CourtDao();
    }

    protected function validate(array $data, bool $creating): array
    {
        Validation::fields($data, ['user_id', 'court_id'], $creating ? ['user_id', 'court_id'] : []);
        $clean = [];
        foreach (['user_id', 'court_id'] as $field) {
            if (array_key_exists($field, $data)) $clean[$field] = Validation::id($data[$field], $field);
        }
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        $this->checkRefs($data);
        if ($this->dao->find($data['user_id'], $data['court_id'])) {
            throw new ServiceException('Court is already saved by this user.', 409);
        }
    }

    protected function beforeUpdate(int $id, array $data, array $existing): void
    {
        $this->checkRefs($data);
        $new = array_merge($existing, $data);
        $duplicate = $this->dao->find((int) $new['user_id'], (int) $new['court_id']);
        if ($duplicate !== null && (int) $duplicate['saved_court_id'] !== $id) {
            throw new ServiceException('Court is already saved by this user.', 409);
        }
    }

    private function checkRefs(array $data): void
    {
        if (isset($data['user_id']) && !$this->users->getById($data['user_id'])) throw new ServiceException('User not found.', 404);
        if (isset($data['court_id']) && !$this->courts->getById($data['court_id'])) throw new ServiceException('Court not found.', 404);
    }
}
