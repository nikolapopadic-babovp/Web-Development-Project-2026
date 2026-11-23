<?php
/** Local CLI-only diagnostic: reports configuration status without printing the API key. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$file = __DIR__ . '/../config/config.php';
if (!is_file($file)) {
    fwrite(STDERR, "MapLark configuration: config.php is missing.\n");
    exit(1);
}

try {
    $config = require $file;
} catch (Throwable $error) {
    fwrite(STDERR, "MapLark configuration: config.php could not be loaded.\n");
    exit(1);
}
$key = is_array($config) ? ($config['maplark_api_key'] ?? '') : '';
if (!is_string($key) || trim($key) === '' || in_array(trim($key), [
    'maplark-api-key', 'YOUR_REAL_API_KEY_HERE', 'YOUR_ACTUAL_MAPLARK_KEY',
], true)) {
    fwrite(STDERR, "MapLark configuration: key missing or placeholder.\n");
    exit(1);
}
echo "MapLark configuration: key appears configured (value not displayed).\n";
