<?php
/** Credential-free Photon provider and discovery flow tests: no config.php and no real network. */
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/PhotonService.php';
require_once __DIR__ . '/../services/SportsDiscoveryService.php';
$passed=0; $failed=0;
function ck($ok,$name) {global $passed,$failed; if($ok){$passed++;echo "PASS: $name\n";}else{$failed++;echo "FAIL: $name\n";}}
$features=['type'=>'FeatureCollection','features'=>[
  ['type'=>'Feature','properties'=>['name'=>'Zetra','osm_key'=>'leisure','osm_value'=>'sports_centre','osm_type'=>'W','osm_id'=>123,'extra'=>['sport'=>'basketball','access'=>'yes']],'geometry'=>['type'=>'Point','coordinates'=>[18.4131,43.8563]]],
  ['type'=>'Feature','properties'=>['name'=>'Far away','osm_key'=>'leisure','osm_value'=>'sports_centre','osm_type'=>'W','osm_id'=>124],'geometry'=>['type'=>'Point','coordinates'=>[19.1,44.1]]],
  ['type'=>'Feature','properties'=>['name'=>'Bad point','osm_key'=>'leisure','osm_value'=>'sports_centre','osm_type'=>'W','osm_id'=>125],'geometry'=>['type'=>'Point','coordinates'=>[400,43.85]]],
]];
$seen=''; $svc=new PhotonService(static function($url)use(&$seen,$features){$seen=$url;return [200,json_encode($features)];});
$r=$svc->findSportsHalls(43.8563,18.4131,5000,20);
ck($r['provider']==='Photon','provider identified');
ck($r['count']===1,'geographic filtering excludes bad/far points');
ck($r['halls'][0]['name']==='Zetra','actual name, no fabricated data');
ck($r['halls'][0]['osm_url']==='https://www.openstreetmap.org/way/123','valid OSM link');
ck($r['halls'][0]['sport']==='basketball','optional sport mapped');
ck(str_contains($seen,'photon.komoot.io/api/')&&str_contains($seen,'include='),'category-based query');
$svc->findSportsHalls(43.8563,18.4131,5000,20,'Zetra');
ck(str_contains($seen,'q=Zetra')&&str_contains($seen,'include='),'named search query');
ck(!str_contains($seen,'lang=bs')&&!str_contains($seen,'lang='),'Photon request does not pass unsupported language');
ck(str_contains($seen,'bbox='),'bounding box used');
try{$svc->findSportsHalls(43.8,18.4,5000,20,' ');ck(false,'blank name blocked');}catch(ServiceException $e){ck($e->status()===400,'blank name blocked');}
try{$svc->findSportsHalls(43.8,18.4,5000,20,str_repeat('X',81));ck(false,'long name blocked');}catch(ServiceException $e){ck($e->status()===400,'long name blocked');}
try{$svc->findSportsHalls(95,18.4);ck(false,'invalid coordinates blocked');}catch(ServiceException $e){ck($e->status()===400,'invalid coordinates blocked');}
$empty=(new PhotonService(static fn()=>[200,'{"features":[]}']))->findSportsHalls(43.8,18.4);
ck($empty['count']===0&&count($empty['halls'])===0,'empty response valid');
foreach ([[200,'bad-json'],[200,'{"wrong":[]}'],[403,'denied'],[429,'throttle']] as [$code,$body]){
 try{(new PhotonService(static fn()=>[$code,$body]))->findSportsHalls(43.8,18.4);ck(false,'error '.$code);}catch(ServiceException $e){ck($e->status()>=500,'error '.$code.' handled');}
}
try{(new PhotonService(static function(){throw new RuntimeException('mock');}))->findSportsHalls(43.8,18.4);ck(false,'network exception');}catch(ServiceException $e){ck($e->status()===504,'network exception');}
$called=0;
$discovery=new SportsDiscoveryService(
 static fn()=>throw new ServiceException('MapLark blocked',502),
 null,
 static function($kind)use(&$called){$called++;return ['halls'=>[],'count'=>0,'provider'=>'Photon'];}
);
ck($discovery->findSportsHalls(43.8,18.4)['provider']==='Photon','MapLark access failure triggers Photon');
ck($called===1,'Photon fallback called once');
ck($discovery->findSportsHalls(43.8,18.4,5000,20,'Zetra')['provider']==='Photon','named search bypasses MapLark');
ck($called===2,'named search calls Photon only');
$discovery2=new SportsDiscoveryService(static fn()=>['provider'=>'MapLark','halls'=>[],'count'=>0],null, static function(){throw new Exception('never called');});
ck($discovery2->findSportsHalls(43.8,18.4)['provider']==='MapLark','MapLark success remains first');
$discovery3=new SportsDiscoveryService(static fn()=>throw new ServiceException('invalid',400),null,static function(){throw new Exception('never called');});
try{$discovery3->findSportsHalls(43.8,18.4);ck(false,'validation not swallowed');}catch(ServiceException $e){ck($e->status()===400,'validation not swallowed');}
echo "PHOTON MOCK TESTS: $passed passed, $failed failed\n";exit($failed?1:0);
