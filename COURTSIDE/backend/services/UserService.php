<?php
class UserService extends BaseService
{
    public function __construct(?UserDao $dao = null)
    {
        parent::__construct($dao ?? new UserDao());
    }

    protected function validate(array $data, bool $creating): array
    {
        Validation::fields($data, ['full_name', 'email', 'password', 'role'], $creating ? ['full_name', 'email', 'password'] : []);
        $clean = [];
        if (array_key_exists('full_name', $data)) $clean['full_name'] = Validation::text($data['full_name'], 'full_name', 100);
        if (array_key_exists('email', $data)) $clean['email'] = Validation::email($data['email']);
        if (array_key_exists('password', $data)) {
            // Never store plaintext or allow clients to set password_hash directly.
            $clean['password_hash'] = password_hash(Validation::password($data['password']), PASSWORD_BCRYPT);
        }
        if (array_key_exists('role', $data)) $clean['role'] = Validation::oneOf($data['role'], 'role', ['admin', 'player']);
        if ($creating && !isset($clean['role'])) $clean['role'] = 'player';
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        if ($this->dao->emailExists($data['email'])) {
            throw new ServiceException('Email address is already registered.', 409);
        }
    }

    protected function beforeUpdate(int $id, array $data, array $existing): void
    {
        if (isset($data['email']) && $data['email'] !== $existing['email'] && $this->dao->emailExists($data['email'])) {
            throw new ServiceException('Email address is already registered.', 409);
        }
    }
}
