<?php
/** Offline filter regression tests; does NOT load config.php, API key, or make requests. */
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/PhotonService.php';
require_once __DIR__ . '/../services/SportsDiscoveryService.php';
$passed = 0; $failed = 0;
function checkVenue(bool $value, string $message): void {
    global $passed, $failed;
    if ($value) { echo "PASS: {$message}\n"; $passed++; }
    else { echo "FAIL: {$message}\n"; $failed++; }
}
function feature(string $name, string $key, string $value, int $id, ?string $sport = null, float $lon = 18.4131, float $lat = 43.8563): array {
    $props = ['name'=>$name,'osm_key'=>$key,'osm_value'=>$value,'osm_type'=>'N','osm_id'=>$id];
    if ($sport !== null) $props['extra'] = ['sport'=>$sport];
    return ['type'=>'Feature','properties'=>$props,'geometry'=>['type'=>'Point','coordinates'=>[$lon,$lat]]];
}
$areaFeatures = ['type'=>'FeatureCollection','features'=>[
    feature('Grbavica', 'place','suburb',1, null,18.4015,43.8505),
    feature('Grbavica I','place','suburb',2, null,18.4045,43.8520),
    feature('Grbavica I bus stop','highway','bus_stop',3),
]];
$places = ['type'=>'FeatureCollection','features'=>[
    feature('Grbavica bus stop','highway','bus_stop',4),
    feature('Grbavica Pharmacy','amenity','pharmacy',5),
    feature('Grbavica Bakery','shop','bakery',6),
    feature('Stadion Grbavica','leisure','stadium',7,'soccer'),
    feature('Goran Čengić hall','leisure','sports_hall',8,null,18.4016,43.8506),
    feature('Basketball playground','leisure','pitch',9,'basketball',18.4017,43.8507),
    feature('Grbavica soccer field','leisure','pitch',10,'soccer',18.4018,43.8508),
    feature('Only tennis arena','building','arena',11,'tennis',18.4019,43.8509),
    feature('Skenderija multisport centre','leisure','sports_centre',12,'basketball;handball',18.4020,43.8510),
    feature('Volleyball sports hall','leisure','sports_hall',13,'volleyball',18.4021,43.8511),
    feature('Multisport hall','building','sports_hall',14,'multi',18.4022,43.8512),
    feature('Nearby outdoor basketball stadium','leisure','stadium',15,'basketball',18.4023,43.8513),
    feature('Distant basketball court','leisure','pitch',16,'basketball',19.1,44.3),
]];
$requests = [];
$transport = static function (string $url) use (&$requests, $areaFeatures, $places): array {
    parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
    $requests[] = $params;
    // A neighbourhood geocoding request has q=Grbavica WITHOUT include filter.
    if (($params['q'] ?? '') === 'Grbavica' && !isset($params['include'])) {
        return [200,json_encode($areaFeatures)];
    }
    return [200,json_encode($places)];
};
$service = new PhotonService($transport);
$result = $service->findSportsHalls(43.8563,18.4131,1500,20,null,'Grbavica');
$names = array_column($result['halls'], 'name');
checkVenue($result['provider'] === 'Photon','provider remains Photon');
checkVenue($result['search_area'] === 'Grbavica','neighbourhood retained');
checkVenue(count($requests) === 2,'neighbourhood geocoding followed by category search');
checkVenue(($requests[0]['q'] ?? '') === 'Grbavica' && !isset($requests[0]['include']),'neighbourhood geocoded as place');
checkVenue(!isset($requests[1]['q']) && isset($requests[1]['include']),'sports queried around area, not by neighbourhood name');
checkVenue(isset($requests[1]['bbox']) && isset($requests[1]['lat']) && abs((float)$requests[1]['lat']-43.8505)<0.00001,'sports query centred on Grbavica coordinates');
checkVenue(in_array('Goran Čengić hall',$names,true),'differently named hall discovered near Grbavica');
checkVenue(in_array('Basketball playground',$names,true),'basketball-tagged outdoor court included');
checkVenue(in_array('Skenderija multisport centre',$names,true),'multisport with basketball included');
checkVenue(in_array('Multisport hall',$names,true),'unknown indoor hall appears as potential venue');
checkVenue(in_array('Nearby outdoor basketball stadium',$names,true),'stadium included only when basketball explicitly tagged');
foreach (['Grbavica bus stop','Grbavica Pharmacy','Grbavica Bakery','Stadion Grbavica','Grbavica soccer field','Only tennis arena','Volleyball sports hall','Distant basketball court'] as $bad) {
    checkVenue(!in_array($bad,$names,true),'excluded irrelevant venue: '.$bad);
}
checkVenue($result['count']===5,'count matches eligible facilities only');
checkVenue($result['halls'][0]['basketball_evidence']==='confirmed','confirmed basketball venue ranked first');
checkVenue(in_array('possible',array_column($result['halls'],'basketball_evidence'),true),'unverified indoor hall explicitly labelled possible');
$requests=[];
$named = $service->findSportsHalls(43.8563,18.4131,5000,20,'Goran Čengić');
checkVenue(($requests[0]['q'] ?? '') === 'Goran Čengić' && isset($requests[0]['include']),'named query includes category filter');
checkVenue(!in_array('Grbavica bus stop',array_column($named['halls'],'name'),true),'named query cannot expose bus stops');
try {$service->findSportsHalls(43.8563,18.4131,3000,20,null,str_repeat('a',81)); checkVenue(false,'long neighbourhood rejected');}
catch (ServiceException $e) {checkVenue($e->status()===400,'long neighbourhood rejected');}
$notFound = new PhotonService(static fn()=>[200,'{"features":[]}']);
try { $notFound->findSportsHalls(43.8563,18.4131,3000,20,null,'Nonexistentarea');checkVenue(false,'unknown neighbourhood rejected'); }
catch(ServiceException $e) {checkVenue($e->status()===404,'unknown neighbourhood returns 404');}
$serviceWithMaplark = new SportsDiscoveryService(
    static fn()=>['halls'=>[
        ['name'=>'Hall','category'=>'sports_hall','sport'=>null],
        ['name'=>'Bus stop','category'=>'bus_stop','sport'=>null],
        ['name'=>'Soccer field','category'=>'pitch','sport'=>'soccer'],
        ['name'=>'Basketball court','category'=>'pitch','sport'=>'basketball']
    ],'count'=>4,'provider'=>'MapLark'], null, null
);
$filtered = $serviceWithMaplark->findSportsHalls(43.8563,18.4131);
checkVenue($filtered['count']===2,'all providers filtered, not only Photon');
checkVenue($filtered['halls'][0]['basketball_evidence']==='possible' && $filtered['halls'][1]['basketball_evidence']==='confirmed','provider results carry evidence label');
$areaArgs=[];
$byArea = new SportsDiscoveryService(
    static fn()=>throw new LogicException('MapLark must not run for neighbourhood search'),
    null,
    static function (...$args) use (&$areaArgs): array { $areaArgs=$args;return ['halls'=>[], 'count'=>0,'provider'=>'Photon']; }
);
$byArea->findSportsHalls(43.8563,18.4131,1500,20,null,'Grbavica');
checkVenue(($areaArgs[6] ?? '')==='Grbavica','neighbourhood query skips MapLark and reaches Photon');
$wrongSport = PhotonService::basketballEvidence('leisure','sports_centre','tennis');
checkVenue($wrongSport===null,'explicit tennis-only sports centre excluded');
checkVenue(PhotonService::basketballEvidence('leisure','pitch',null)===null,'untagged football-type pitch excluded');
echo "VENUE FILTER MOCK TESTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
