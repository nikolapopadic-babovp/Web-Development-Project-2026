<?php
/** Regression test: Photon public API rejects lang=bs; no secrets, network or config accessed. */
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/PhotonService.php';
$requests = [];
$transport = static function (string $url) use (&$requests): array {
    $params = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    $requests[] = $params;
    if (isset($params['lang']) && !in_array($params['lang'], ['default', 'de', 'en', 'fr'], true)) {
        return [400, '{"lang":[{"message":"Language is not supported"}]}'];
    }
    return [200, '{"type":"FeatureCollection","features":[]}'];
};
$photon = new PhotonService($transport);
$named = $photon->findSportsHalls(43.8563, 18.4131, 8000, 20, 'Zetra');
$category = $photon->findSportsHalls(43.8563, 18.4131, 5000, 20);
if (($named['provider'] ?? null) !== 'Photon' || ($category['provider'] ?? null) !== 'Photon' || count($requests) !== 2) {
    fwrite(STDERR, "FAIL provider response/regression setup\n"); exit(1);
}
if (($requests[0]['q'] ?? null) !== 'Zetra' || !isset($requests[0]['bbox']) || isset($requests[0]['lang'])) {
    fwrite(STDERR, "FAIL named search invalid query params\n"); exit(1);
}
if (!isset($requests[1]['include']) || isset($requests[1]['lang'])) {
    fwrite(STDERR, "FAIL category search invalid query params\n"); exit(1);
}
echo "PASS: named Photon query omits unsupported language\n";
echo "PASS: category Photon query omits unsupported language\n";
echo "PASS: valid location, bbox, and q parameters retained\n";
echo "All HTTP 400 language regression checks passed (offline, no config.php).\n";
