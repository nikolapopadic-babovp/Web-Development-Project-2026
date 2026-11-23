<?php
class RunService extends BaseService
{
    private CourtDao $courts;
    private UserDao $users;
    private RunMemberDao $members;

    public function __construct(?RunDao $dao = null, ?CourtDao $courts = null, ?UserDao $users = null, ?RunMemberDao $members = null)
    {
        parent::__construct($dao ?? new RunDao());
        $this->courts = $courts ?? new CourtDao();
        $this->users = $users ?? new UserDao();
        $this->members = $members ?? new RunMemberDao();
    }

    protected function validate(array $data, bool $creating): array
    {
        Validation::fields($data,
            ['court_id', 'creator_user_id', 'title', 'starts_at', 'max_players', 'skill_level', 'notes', 'status'],
            $creating ? ['court_id', 'creator_user_id', 'title', 'starts_at', 'max_players'] : []);
        $clean = [];
        foreach (['court_id', 'creator_user_id'] as $name) {
            if (array_key_exists($name, $data)) $clean[$name] = Validation::id($data[$name], $name);
        }
        if (array_key_exists('title', $data)) $clean['title'] = Validation::text($data['title'], 'title', 150);
        if (array_key_exists('notes', $data)) $clean['notes'] = Validation::text($data['notes'], 'notes', 5000, true);
        if (array_key_exists('starts_at', $data)) $clean['starts_at'] = Validation::utcDate($data['starts_at']);
        if (array_key_exists('max_players', $data)) $clean['max_players'] = Validation::integer($data['max_players'], 'max_players', 2, 40);
        if (array_key_exists('skill_level', $data)) $clean['skill_level'] = Validation::oneOf($data['skill_level'], 'skill_level', ['beginner', 'intermediate', 'competitive', 'all']);
        if (array_key_exists('status', $data)) $clean['status'] = Validation::oneOf($data['status'], 'status', ['open', 'full', 'cancelled', 'completed']);
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        $this->checkReferences($data);
        if (($data['status'] ?? 'open') === 'open') $this->checkFuture($data['starts_at']);
    }

    protected function beforeUpdate(int $id, array $data, array $existing): void
    {
        $this->checkReferences($data);
        if (isset($data['starts_at']) && ($data['status'] ?? $existing['status']) === 'open') {
            $this->checkFuture($data['starts_at']);
        }
        if (isset($data['max_players']) && $data['max_players'] < $this->members->countJoined($id)) {
            throw new ServiceException('max_players cannot be lower than the number of joined players.', 409);
        }
    }

    private function checkReferences(array $data): void
    {
        if (isset($data['court_id']) && !$this->courts->getById($data['court_id'])) {
            throw new ServiceException('Court not found.', 404);
        }
        if (isset($data['creator_user_id']) && !$this->users->getById($data['creator_user_id'])) {
            throw new ServiceException('Creator user not found.', 404);
        }
    }

    private function checkFuture(string $utc): void
    {
        if ($utc <= gmdate('Y-m-d H:i:s')) {
            throw new ServiceException('An open run must start in the future (UTC).');
        }
    }
}
