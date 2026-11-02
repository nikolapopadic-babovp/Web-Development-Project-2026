<?php
/**
 * COURTSIDE Milestone 2 - DAO test script.
 * Run from the project folder:  php backend/tests/dao_test.php
 * Needs backend/config/config.php and the imported database/courtside.sql.
 * Everything runs inside ONE transaction that is rolled back at the end, so your data is not changed.
 */
require_once __DIR__ . '/../dao/UserDao.php';
require_once __DIR__ . '/../dao/CourtDao.php';
require_once __DIR__ . '/../dao/RunDao.php';
require_once __DIR__ . '/../dao/RunMemberDao.php';
require_once __DIR__ . '/../dao/SavedCourtDao.php';
require_once __DIR__ . '/../dao/ReviewDao.php';

$passed = 0; $failed = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    if ($ok) { $passed++; echo "  PASS  $name\n"; }
    else     { $failed++; echo "  FAIL  $name  $detail\n"; }
}
function throws(callable $fn): bool {
    try { $fn(); } catch (Throwable $e) { return true; }
    return false;
}

$pdo = Database::connect();
$pdo->beginTransaction();

$users = new UserDao(); $courts = new CourtDao(); $runs = new RunDao();
$members = new RunMemberDao(); $saved = new SavedCourtDao(); $reviews = new ReviewDao();

echo "BaseDao read\n";
check('getAll returns 5 sample users', count($users->getAll()) === 5);
check('getAll(limit 2) respects the limit', count($users->getAll(2)) === 2);
check('getAll(limit 2, offset 4) respects the offset', count($users->getAll(2, 4)) === 1);
check('getById(1) returns the admin', ($users->getById(1)['role'] ?? '') === 'admin');
check('getById(9999) returns null', $users->getById(9999) === null);

echo "UserDao\n";
check('getById does not expose password_hash', !array_key_exists('password_hash', $users->getById(1)));
check('getAll does not expose password_hash', !array_key_exists('password_hash', $users->getAll()[0]));
$login = $users->getByEmailWithHash('admin@courtside.test');
check('getByEmailWithHash returns the hash', $login !== null && str_starts_with($login['password_hash'], '$2y$'));
check('stored hash verifies Admin123!', $login !== null && password_verify('Admin123!', $login['password_hash']));
check('stored hash verifies Player123! (player account)', password_verify('Player123!', $users->getByEmailWithHash('player@courtside.test')['password_hash']));
check('emailExists true / false', $users->emailExists('lejla@courtside.test') && !$users->emailExists('nobody@x.test'));
check('getByRole(admin) = 1, getByRole(player) = 4', count($users->getByRole('admin')) === 1 && count($users->getByRole('player')) === 4);
$newId = $users->insert(['full_name' => 'Test User', 'email' => 'test@courtside.test', 'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'role' => 'player']);
check('insert returns a new id', $newId > 5);
check('insert ignores columns outside the whitelist', $users->insert(['full_name' => 'A', 'email' => 'a@courtside.test', 'password_hash' => 'h', 'is_admin' => 1, 'user_id' => 999]) !== 999);
check('update changes the row (returns 1)', $users->update($newId, ['full_name' => 'Renamed']) === 1 && $users->getById($newId)['full_name'] === 'Renamed');
check('update with same values still returns 1 (matched rows)', $users->update($newId, ['full_name' => 'Renamed']) === 1);
check('update of missing id returns 0', $users->update(9999, ['full_name' => 'X']) === 0);
check('update with only invalid columns throws', throws(fn() => $users->update($newId, ['bogus' => 1])));
check('insert with no valid columns throws', throws(fn() => $users->insert(['bogus' => 1])));
check('duplicate email is rejected by the database', throws(fn() => $users->insert(['full_name' => 'Dup', 'email' => 'test@courtside.test', 'password_hash' => 'h'])));
check('invalid role is rejected', throws(fn() => $users->insert(['full_name' => 'Bad', 'email' => 'bad@courtside.test', 'password_hash' => 'h', 'role' => 'superuser'])));
check('SQL injection text in a value is stored as plain text', ($users->getById($users->insert(['full_name' => "x'; DROP TABLE users;--", 'email' => 'inj@courtside.test', 'password_hash' => 'h']))['full_name'] ?? '') === "x'; DROP TABLE users;--" && $users->getById(1) !== null);
check('delete returns 1, then row is gone', $users->delete($newId) === 1 && $users->getById($newId) === null);
check('delete of missing id returns 0', $users->delete(9999) === 0);

echo "CourtDao\n";
check('getAll returns 6 courts', count($courts->getAll()) === 6);
check('getByCity(Sarajevo) = 3', count($courts->getByCity('Sarajevo')) === 3);
check('search q=sarajevo finds courts (name/city/address)', count($courts->search(['q' => 'sarajevo'])) === 4, 'got ' . count($courts->search(['q' => 'sarajevo'])));
check('search is_indoor=1 = 2', count($courts->search(['is_indoor' => 1])) === 2);
check('search is_indoor=0 = 4', count($courts->search(['is_indoor' => 0])) === 4);
check('search surface_type=wood + city=Sarajevo = 1', count($courts->search(['surface_type' => 'wood', 'city' => 'Sarajevo'])) === 1);
check('search with no filters returns all 6', count($courts->search()) === 6);
check('getByExternalId finds sample-court-3', ($courts->getByExternalId('sample', 'sample-court-3')['name'] ?? '') === 'East Sarajevo Sports Hall');
$cId = $courts->insert(['name' => 'Test Court', 'city' => 'Zenica', 'latitude' => 44.2, 'longitude' => 17.9, 'surface_type' => 'concrete', 'is_indoor' => 0, 'source' => 'manual', 'external_id' => 'm-1']);
check('court insert + getById', $courts->getById($cId)['city'] === 'Zenica');
check('duplicate (source, external_id) is rejected', throws(fn() => $courts->insert(['name' => 'Dup', 'city' => 'Zenica', 'source' => 'manual', 'external_id' => 'm-1'])));
check('court update + delete', $courts->update($cId, ['name' => 'Renamed Court']) === 1 && $courts->delete($cId) === 1);

echo "RunDao\n";
check('getAll returns 5 runs', count($runs->getAll()) === 5);
check('getByCourt(1) = 1 run', count($runs->getByCourt(1)) === 1);
check('getByCreator(2) = 2 runs', count($runs->getByCreator(2)) === 2);
$upcoming = $runs->getUpcoming(10);
check('getUpcoming runs without error and includes court_name', $upcoming === [] || isset($upcoming[0]['court_name']));
$rId = $runs->insert(['court_id' => 1, 'creator_user_id' => 2, 'title' => 'Test Run', 'starts_at' => '2030-01-01 18:00:00', 'max_players' => 8, 'skill_level' => 'all']);
check('run insert + getUpcoming now contains it', in_array($rId, array_column($runs->getUpcoming(50), 'run_id')));
check('max_players = 0 is rejected (CHECK)', throws(fn() => $runs->insert(['court_id' => 1, 'creator_user_id' => 2, 'title' => 'Bad', 'starts_at' => '2030-01-01 18:00:00', 'max_players' => 0])));
check('run with unknown court is rejected (FK)', throws(fn() => $runs->insert(['court_id' => 9999, 'creator_user_id' => 2, 'title' => 'Bad', 'starts_at' => '2030-01-01 18:00:00', 'max_players' => 4])));
check('deleting a court that has runs is blocked (RESTRICT)', throws(fn() => $courts->delete(1)));
check('deleting a user that created runs is blocked (RESTRICT)', throws(fn() => $users->delete(2)));
check('run update', $runs->update($rId, ['status' => 'cancelled']) === 1 && $runs->getById($rId)['status'] === 'cancelled');

echo "RunMemberDao\n";
check('getByRun(1) = 3 members with full_name', count($members->getByRun(1)) === 3 && isset($members->getByRun(1)[0]['full_name']));
check('getByUser(2) = 3 entries with title', count($members->getByUser(2)) === 3 && isset($members->getByUser(2)[0]['title']));
check('find(1, 2) found / find(1, 5) null', $members->find(1, 2) !== null && $members->find(1, 5) === null);
check('countJoined(3) = 2 (waitlisted not counted)', $members->countJoined(3) === 2, 'got ' . $members->countJoined(3));
check('countJoined(4) = 1 (cancelled not counted)', $members->countJoined(4) === 1);
$mId = $members->insert(['run_id' => 2, 'user_id' => 4]);
check('member insert defaults to joined', $members->getById($mId)['membership_status'] === 'joined');
check('same user twice in one run is rejected (UNIQUE)', throws(fn() => $members->insert(['run_id' => 2, 'user_id' => 4])));
check('deleteByRunAndUser returns 1 then 0', $members->deleteByRunAndUser(2, 4) === 1 && $members->deleteByRunAndUser(2, 4) === 0);
$tmpRun = $runs->insert(['court_id' => 2, 'creator_user_id' => 3, 'title' => 'Cascade run', 'starts_at' => '2030-02-01 18:00:00', 'max_players' => 4]);
$members->insert(['run_id' => $tmpRun, 'user_id' => 5]);
$runs->delete($tmpRun);
check('deleting a run removes its members (CASCADE)', $members->find($tmpRun, 5) === null);

echo "SavedCourtDao\n";
check('getByUser(2) = 2 courts with court name', count($saved->getByUser(2)) === 2 && isset($saved->getByUser(2)[0]['name']));
check('find(2, 1) found / find(2, 2) null', $saved->find(2, 1) !== null && $saved->find(2, 2) === null);
$sId = $saved->insert(['user_id' => 4, 'court_id' => 1]);
check('saved insert', $saved->getById($sId) !== null);
check('saving the same court twice is rejected (UNIQUE)', throws(fn() => $saved->insert(['user_id' => 4, 'court_id' => 1])));
check('deleteByUserAndCourt returns 1 then 0', $saved->deleteByUserAndCourt(4, 1) === 1 && $saved->deleteByUserAndCourt(4, 1) === 0);

echo "ReviewDao\n";
check('getByCourt(1) = 2 reviews with full_name', count($reviews->getByCourt(1)) === 2 && isset($reviews->getByCourt(1)[0]['full_name']));
check('getByUser(3) = 2 reviews with court_name', count($reviews->getByUser(3)) === 2 && isset($reviews->getByUser(3)[0]['court_name']));
$sum = $reviews->getRatingSummary(1);
check('rating summary for court 1 = 2 reviews, average 4.5', $sum['review_count'] === 2 && $sum['average_rating'] === 4.5, json_encode($sum));
$none = $reviews->getRatingSummary(4);
check('rating summary for court without reviews = 0 / null', $none['review_count'] === 0 && $none['average_rating'] === null);
check('find(2, 1) found / find(2, 2) null', $reviews->find(2, 1) !== null && $reviews->find(2, 2) === null);
$rvId = $reviews->insert(['user_id' => 4, 'court_id' => 1, 'rating' => 5, 'comment' => 'Test']);
check('review insert', $reviews->getById($rvId)['rating'] === 5);
check('rating 6 is rejected (CHECK)', throws(fn() => $reviews->insert(['user_id' => 5, 'court_id' => 1, 'rating' => 6])));
check('rating 0 is rejected (CHECK)', throws(fn() => $reviews->insert(['user_id' => 5, 'court_id' => 1, 'rating' => 0])));
check('second review by the same user for the same court is rejected (UNIQUE)', throws(fn() => $reviews->insert(['user_id' => 4, 'court_id' => 1, 'rating' => 3])));
check('review update + delete', $reviews->update($rvId, ['rating' => 3]) === 1 && $reviews->delete($rvId) === 1);
check('deleting a user that wrote reviews is blocked (RESTRICT)', throws(fn() => $users->delete(4)));
$tmpCourt = $courts->insert(['name' => 'Cascade court', 'city' => 'Tuzla']);
$tmpRev = $reviews->insert(['user_id' => 5, 'court_id' => $tmpCourt, 'rating' => 4]);
$courts->delete($tmpCourt);
check('deleting a court removes its reviews (CASCADE)', $reviews->getById($tmpRev) === null);

$pdo->rollBack();
check('rollback restored the sample data (5 users, 5 runs)', count($users->getAll()) === 5 && count($runs->getAll()) === 5);

echo "\nResult: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
