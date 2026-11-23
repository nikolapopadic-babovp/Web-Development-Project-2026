<?php
/** CLI mock tests for sports hall/arena discovery; no API key or database necessary. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/MapLarkService.php';
$pass = 0; $fail = 0;
function verify(bool $ok, string $msg): void {
    global $pass,$fail;
    if ($ok) { $pass++; echo "PASS $msg\n"; }
    else { $fail++; echo "FAIL $msg\n"; }
}
function expectError(callable $call, int $status, string $msg): void {
    try { $call(); verify(false,$msg); }
    catch (ServiceException $e) { verify($e->status() === $status,$msg); }
}
$fixture = json_encode(['type'=>'FeatureCollection', 'features'=>[
    ['type'=>'Feature','id'=>'way/123','geometry'=>['type'=>'Point','coordinates'=>[18.413,43.856]],
     'properties'=>['tags'=>['name'=>'Skenderija demo record','leisure'=>'sports_centre','sport'=>'basketball']]],
    ['type'=>'Feature','id'=>'way/456','geometry'=>['type'=>'Polygon','coordinates'=>[[[18.415,43.86],[18.416,43.86],[18.416,43.861],[18.415,43.86]]]],
     'properties'=>['name'=>'Arena demo record', 'building'=>'stadium']],
    ['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[199,92]], 'properties'=>['name'=>'Invalid point']],
],],JSON_THROW_ON_ERROR);
$url='';$keyReceived='';
$service = new MapLarkService('unit-test-key',static function($requestUrl,$key) use (&$url,&$keyReceived,$fixture): array {
    $url=$requestUrl;$keyReceived=$key;return [200,$fixture];
});
$result=$service->findSportsHalls(43.8563,18.4131,5000,20);
verify(isset($result['halls']) && !isset($result['courts']), 'halls response name');
verify($result['count']===2, '2 valid GeoJSON venue coordinates');
verify($result['halls'][0]['name']==='Skenderija demo record', 'uses OSM venue name');
verify($result['halls'][0]['category']==='sports_centre', 'sports centre category');
verify($result['halls'][1]['category']==='stadium', 'arena/stadium tag is represented truthfully');
verify(str_contains($url,'/v3/osm_features?'), 'calls supported MapLark v3 endpoint');
verify(str_contains($url,'sports_centre') && str_contains($url,'sports_hall') && str_contains($url,'radius=5000'), 'sports venue OSM filters and radius');
verify(!str_contains($url,$keyReceived) && $keyReceived==='unit-test-key','API key not in URL');
verify($result['halls'][0]['osm_url']==='https://www.openstreetmap.org/way/123', 'OSM attribution link');
verify($result['attribution']==='© OpenStreetMap contributors', 'attribution preserved');
expectError(static fn()=>$service->findSportsHalls(91,18),400,'latitude bounds');
expectError(static fn()=>$service->findSportsHalls(43,181),400,'longitude bounds');
expectError(static fn()=>$service->findSportsHalls(43,18,50),400,'radius bounds');
expectError(static fn()=>$service->findSportsHalls(43,18,5000,51),400,'result limit bounds');
foreach ([400=>502,401=>502,403=>502,429=>503,500=>502] as $http=>$status) {
    $bad=new MapLarkService('mock-key',static fn():array=>[$http,'{}']);
    expectError(static fn()=>$bad->findSportsHalls(43.856,18.413),$status,"safe HTTP $http mapping");
}
$bad=new MapLarkService('mock-key',static fn():array=>[200,'not json']);
expectError(static fn()=>$bad->findSportsHalls(43.856,18.413),502,'invalid upstream JSON');
$bad=new MapLarkService('mock-key',static fn():array=>throw new RuntimeException('timeout',28));
expectError(static fn()=>$bad->findSportsHalls(43.856,18.413),504,'network timeout');
verify(count($result['halls']) <= 20, 'limit applied to output');
printf("Result: %d passed, %d failed\n",$pass,$fail);
exit($fail===0?0:1);
