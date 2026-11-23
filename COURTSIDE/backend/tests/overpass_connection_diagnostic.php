<?php
/** CLI-only external HTTPS diagnostic; does not include or access config.php or API keys. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
$endpoint = 'https://overpass.private.coffee/api/interpreter';
$statusUrl = 'https://overpass.private.coffee/api/status';
echo "COURTSIDE Overpass network check (no config.php or API key accessed)\n";
echo 'PHP ' . PHP_VERSION . ' | INI: ' . (php_ini_loaded_file() ?: 'none') . "\n";
echo 'cURL extension: ' . (function_exists('curl_init') ? 'enabled' : 'disabled (stream fallback)') . "\n";
echo 'OpenSSL extension: ' . (extension_loaded('openssl') ? 'enabled' : 'disabled') . "\n";
if (function_exists('curl_init')) {
    $checks = [
        ['label' => 'HTTPS status GET', 'url' => $statusUrl, 'post' => false],
        ['label' => 'Overpass query POST', 'url' => $endpoint, 'post' => true],
    ];
    $failed = false;
    foreach ($checks as $check) {
        $curl = curl_init($check['url']);
        if ($curl === false) { echo $check['label'] . ": curl_init failed\n"; $failed = true; continue; }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 22,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'COURTSIDE/1.0 (university project diagnostic)',
        ]);
        if ($check['post']) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(['data' => '[out:json][timeout:10];node(around:600,43.8563,18.4131)["leisure"="sports_centre"];out 1;']));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json']);
        }
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errno = (int) curl_errno($curl);
        curl_close($curl);
        echo $check['label'] . ': HTTP ' . $status . ', curl_errno ' . $errno . "\n";
        if ($response === false || $status < 200 || $status >= 300) $failed = true;
        if ($errno === 60 || $errno === 77) echo "  TLS trust failure. Configure a trusted CA bundle in XAMPP php.ini (do NOT disable certificate verification).\n";
        elseif ($errno === 6) echo "  DNS lookup failed. Check internet access, proxy and DNS.\n";
        elseif ($errno === 7) echo "  Cannot connect to host. Check firewall, proxy or server accessibility.\n";
        elseif ($errno === 28) echo "  Connection timed out. Check proxy/firewall and provider availability.\n";
        elseif ($status === 429) echo "  Public Overpass rate limit reached; wait rather than repeatedly retrying.\n";
        elseif ($status === 403 || $status === 406) echo "  Overpass has rejected this client/request; check upstream usage rules.\n";
    }
    exit($failed ? 1 : 0);
}
// Same fallback used by OverpassService when ext-curl is unavailable.
$context = stream_context_create([
    'http' => ['method' => 'GET', 'timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "User-Agent: COURTSIDE/1.0 (university project diagnostic)\r\n"],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$result = @file_get_contents($statusUrl, false, $context);
$status = 0;
foreach (($http_response_header ?? []) as $header) {
    if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $m)) { $status = (int) $m[1]; break; }
}
echo 'HTTPS status GET (stream): HTTP ' . $status . ', response ' . ($result === false ? 'failed' : 'received') . "\n";
if ($result === false) echo "  Check DNS, internet access, OpenSSL certificate trust, proxy and firewall.\n";
exit($result !== false && $status === 200 ? 0 : 1);
