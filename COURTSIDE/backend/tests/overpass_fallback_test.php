<?php
/** Credential-free mocked checks. Does not load config.php and makes no network requests. */
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/OverpassService.php';
require_once __DIR__ . '/../services/SportsDiscoveryService.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $label): void {
    global $passed, $failed;
    if ($condition) { $passed++; echo "PASS $label\n"; }
    else { $failed++; echo "FAIL $label\n"; }
}
$body = json_encode(['elements' => [
    ['type'=>'node','id'=>123,'lat'=>43.8563,'lon'=>18.4131,'tags'=>['name'=>'Skenderija example','leisure'=>'sports_centre']],
    ['type'=>'way','id'=>456,'center'=>['lat'=>43.88,'lon'=>18.39],'tags'=>['name'=>'Example arena','building'=>'arena']],
    ['type'=>'relation','id'=>789,'center'=>['lat'=>43.89,'lon'=>18.41],'tags'=>['name'=>'Example hall','leisure'=>'sports_hall']],
    ['type'=>'node','id'=>999,'lat'=>125,'lon'=>18.4,'tags'=>[]],
]]);
$querySeen = '';
$overpass = new OverpassService(static function (string $url, string $query) use ($body, &$querySeen): array {
    $querySeen = $query;
    check(str_contains($url, 'https://overpass.private.coffee/api/interpreter'), 'fixed Overpass host');
    return [200, $body];
});
$result = $overpass->findSportsHalls(43.8563,18.4131,5000,20);
check($result['count'] === 3, 'maps three valid OSM elements');
check($result['provider'] === 'Overpass', 'provider attribution');
check($result['halls'][0]['name'] === 'Skenderija example', 'name normalized');
check($result['halls'][1]['latitude'] === 43.88, 'way center parsed');
check($result['halls'][2]['osm_url'] === 'https://www.openstreetmap.org/relation/789', 'relation OSM URL');
check(str_contains($querySeen,'sports_centre') && str_contains($querySeen,'sports_hall'), 'correct halls query');
check(str_contains($querySeen,'around:5000,43.856300,18.413100'), 'safe numeric radius and coordinates');
check(!str_contains(json_encode($result), 'api_key'), 'no key in payload');
$overpass->findCourts(43.8563,18.4131,3000,10);
check(str_contains($querySeen, 'basketball'), 'basketball query');
try { $overpass->findSportsHalls(98,18.4); check(false,'reject invalid latitude'); }
catch (ServiceException $e) { check($e->status() === 400,'reject invalid latitude'); }
try { $overpass->findSportsHalls(43.8,18.4,10); check(false,'reject invalid radius'); }
catch (ServiceException $e) { check($e->status() === 400,'reject invalid radius'); }
$empty = new OverpassService(static fn():array=>[200,'{"elements":[]}']);
check($empty->findSportsHalls(43.8,18.4)['count'] === 0,'valid empty results');
$bad = new OverpassService(static fn():array=>[200,'not-json']);
try { $bad->findSportsHalls(43.8,18.4); check(false,'reject malformed JSON'); }
catch (ServiceException $e) { check($e->status()===502,'reject malformed JSON'); }
$rateLimit = new OverpassService(static fn():array=>[429,'']);
try { $rateLimit->findSportsHalls(43.8,18.4); check(false,'rate limit'); }
catch (ServiceException $e) { check($e->status()===503,'rate limit'); }
$failNetwork = new OverpassService(static function():array { throw new RuntimeException('mocked error'); });
try { $failNetwork->findSportsHalls(43.8,18.4); check(false,'network failure'); }
catch (ServiceException $e) { check($e->status()===504,'network failure'); }
$mapCalls = 0; $fallbackCalls = 0;
$service = new SportsDiscoveryService(
    static function() use (&$mapCalls): array { $mapCalls++; throw new ServiceException('MapLark access denied',502); },
    static function(string $kind) use (&$fallbackCalls): array { $fallbackCalls++; return ['halls'=>[],'count'=>0,'provider'=>'Overpass']; }
);
check($service->findSportsHalls(43.8,18.4)['provider'] === 'Overpass','provider fallback after denied auth');
check($mapCalls===1 && $fallbackCalls===1,'fallback invoked once');
$service2 = new SportsDiscoveryService(static fn():array=>['halls'=>[],'provider'=>'MapLark'], static function():array{throw new Exception('should not run');});
check($service2->findSportsHalls(43.8,18.4)['provider']==='MapLark','no fallback on success');
$service3 = new SportsDiscoveryService(static fn():array=>throw new ServiceException('Bad request',400), static function():array{throw new Exception('should not run');});
try { $service3->findSportsHalls(43.8,18.4); check(false,'bad input failure'); }
catch (ServiceException $e) { check($e->status()===400,'no fallback on validation error'); }
check(count(array_keys($result)) === 4,'exact response envelope fields');
echo "OVERPASS MOCK TESTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
