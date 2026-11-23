<?php
class ReviewService extends BaseService
{
    private UserDao $users;
    private CourtDao $courts;

    public function __construct(?ReviewDao $dao = null, ?UserDao $users = null, ?CourtDao $courts = null)
    {
        parent::__construct($dao ?? new ReviewDao());
        $this->users = $users ?? new UserDao();
        $this->courts = $courts ?? new CourtDao();
    }

    protected function validate(array $data, bool $creating): array
    {
        Validation::fields($data, $creating ? ['user_id', 'court_id', 'rating', 'comment'] : ['rating', 'comment'],
            $creating ? ['user_id', 'court_id', 'rating'] : []);
        $clean = [];
        foreach (['user_id', 'court_id'] as $field) {
            if (array_key_exists($field, $data)) $clean[$field] = Validation::id($data[$field], $field);
        }
        if (array_key_exists('rating', $data)) $clean['rating'] = Validation::integer($data['rating'], 'rating', 1, 5);
        if (array_key_exists('comment', $data)) $clean['comment'] = Validation::text($data['comment'], 'comment', 5000, true);
        return $clean;
    }

    protected function beforeCreate(array $data): void
    {
        if (!$this->users->getById($data['user_id'])) throw new ServiceException('User not found.', 404);
        if (!$this->courts->getById($data['court_id'])) throw new ServiceException('Court not found.', 404);
        if ($this->dao->find($data['user_id'], $data['court_id'])) {
            throw new ServiceException('A user can review a court only once.', 409);
        }
    }
}
