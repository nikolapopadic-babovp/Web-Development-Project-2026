<?php
/** CLI-only isolated tests: PHP backend/tests/maplark_test.php
 * No database, real API key, internet access or API billing required.
 */
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/MapLarkService.php';

$passed = 0;
$failed = 0;
function check(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) { echo "PASS {$message}\n"; ++$passed; }
    else { echo "FAIL {$message}\n"; ++$failed; }
}
function expectStatus(callable $fn, int $expected, string $message): void
{
    try { $fn(); check(false, $message . ' (no exception)'); }
    catch (ServiceException $e) { check($e->status() === $expected, $message); }
}
$geojson = json_encode([
    'type' => 'FeatureCollection',
    'features' => [
        ['type' => 'Feature', 'id' => 'way/42', 'geometry' => ['type'=>'Polygon','coordinates'=>[[[18.40,43.85],[18.41,43.85],[18.41,43.86],[18.40,43.85]]]], 'properties' => ['tags'=>['name'=>'Basketball Park', 'surface'=>'asphalt','access'=>'yes']]],
        ['type'=>'Feature','id'=>'node/43','geometry'=>['type'=>'Point','coordinates'=>[18.43,43.87]],'properties'=>['name'=>'Park basket','sport'=>'basketball']],
        ['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[999,999]],'properties'=>[]],
    ],
], JSON_THROW_ON_ERROR);
$urlSeen = '';
$keySeen = '';
$success = new MapLarkService('fake-key-for-unit-tests', static function (string $url, string $key) use ($geojson, &$urlSeen, &$keySeen): array {
    $urlSeen = $url; $keySeen = $key;
    return [200, $geojson];
});
$result = $success->findCourts(43.8563,18.4131,3000,20);
check($result['count'] === 2, 'counts valid Point + Polygon courts and skips invalid geometry');
check($result['courts'][0]['name'] === 'Basketball Park', 'extracts nested OSM tags and name');
check($result['courts'][1]['name'] === 'Park basket', 'extracts flat OSM tags');
check($result['courts'][0]['osm_url'] === 'https://www.openstreetmap.org/way/42', 'constructs safe OSM URL');
check($result['courts'][0]['surface'] === 'asphalt', 'extracts court details');
check(str_contains($urlSeen, '/v3/osm_features?') && str_contains($urlSeen, 'sport%3Dbasketball') && str_contains($urlSeen, 'radius=3000'), 'upstream URL and filter use v3 MapLark query');
check($keySeen === 'fake-key-for-unit-tests' && !str_contains($urlSeen, 'fake-key'), 'API key stays out of upstream query URL');
check($result['attribution'] === '© OpenStreetMap contributors', 'OSM attribution included');
expectStatus(static fn() => $success->findCourts(91,18),400,'reject invalid latitude');
expectStatus(static fn() => $success->findCourts(43,181),400,'reject invalid longitude');
expectStatus(static fn() => $success->findCourts(43,18,100),400,'reject invalid small radius');
expectStatus(static fn() => $success->findCourts(43,18,3000,51),400,'reject invalid limit');
foreach ([401=>502,403=>502,429=>503,500=>502] as $code=>$expected) {
    $mock = new MapLarkService('fake',static fn(): array => [$code,'{"error":"example"}']);
    expectStatus(static fn() => $mock->findCourts(43.85,18.4),$expected,"upstream HTTP {$code} mapped safely");
}
$bad = new MapLarkService('fake',static fn():array=>[200,'invalid json']);
expectStatus(static fn()=>$bad->findCourts(43.85,18.4),502,'malformed JSON handled');
$bad = new MapLarkService('fake',static fn():array=>[200,'{"type":"Other","features":[]}']);
expectStatus(static fn()=>$bad->findCourts(43.85,18.4),502,'unexpected schema handled');
$bad = new MapLarkService('fake',static fn():array=>throw new RuntimeException('simulated timeout'));
expectStatus(static fn()=>$bad->findCourts(43.85,18.4),504,'connection/timeout handled');
$missing = new MapLarkService('');
expectStatus(static fn()=>$missing->findCourts(43.85,18.4),503,'missing API key handled');

printf("Result: %d passed, %d failed\n",$passed,$failed);
exit($failed === 0 ? 0 : 1);
