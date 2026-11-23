<?php
class RunMemberService extends BaseService
{
    private RunDao $runs;
    private UserDao $users;

    public function __construct(?RunMemberDao $dao = null, ?RunDao $runs = null, ?UserDao $users = null)
    {
        parent::__construct($dao ?? new RunMemberDao());
        $this->runs = $runs ?? new RunDao();
        $this->users = $users ?? new UserDao();
    }

    protected function validate(array $data, bool $creating): array
    {
        Validation::fields($data, $creating ? ['run_id', 'user_id', 'membership_status'] : ['membership_status'],
            $creating ? ['run_id', 'user_id'] : []);
        $clean = [];
        foreach (['run_id', 'user_id'] as $field) {
            if (array_key_exists($field, $data)) $clean[$field] = Validation::id($data[$field], $field);
        }
        if (array_key_exists('membership_status', $data)) {
            $clean['membership_status'] = Validation::oneOf($data['membership_status'], 'membership_status', ['joined', 'waitlisted', 'cancelled']);
        }
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        if (!$this->users->getById($data['user_id'])) throw new ServiceException('User not found.', 404);
        if ($this->dao->find($data['run_id'], $data['user_id'])) {
            throw new ServiceException('User already has a membership in this run.', 409);
        }
        $this->validateEntry($data['run_id'], $data['membership_status'] ?? 'joined');
    }

    protected function beforeUpdate(int $id, array $data, array $existing): void
    {
        if (($data['membership_status'] ?? '') !== 'cancelled' &&
            ($data['membership_status'] ?? '') !== $existing['membership_status']) {
            $this->validateEntry((int) $existing['run_id'], $data['membership_status']);
        }
    }

    private function validateEntry(int $runId, string $status): void
    {
        $run = $this->runs->getById($runId);
        if (!$run) throw new ServiceException('Run not found.', 404);
        if ($status === 'cancelled') return;
        if ($run['status'] !== 'open' || $run['starts_at'] <= gmdate('Y-m-d H:i:s')) {
            throw new ServiceException('Players can only join or waitlist a future, open run.', 409);
        }
        if ($status === 'joined' && $this->dao->countJoined($runId) >= (int) $run['max_players']) {
            throw new ServiceException('Run is full. Choose waitlisted instead.', 409);
        }
    }
}
