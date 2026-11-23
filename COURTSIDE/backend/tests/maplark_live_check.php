<?php
/** CLI-only safe live test. Makes one authenticated MapLark request with your local config.
 * Reports the result and safe error status; never displays the API key or upstream body.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/ServiceException.php';
require_once __DIR__ . '/../services/Validation.php';
require_once __DIR__ . '/../services/ExternalApiLog.php';
require_once __DIR__ . '/../services/MapLarkService.php';
if (!is_file(__DIR__ . '/../config/config.php')) {
    fwrite(STDERR,"ERROR: backend/config/config.php is missing.\n"); exit(1);
}
try {
    $halls=(new MapLarkService())->findSportsHalls(43.8563,18.4131,5000,20);
    echo "SUCCESS: authenticated request returned " . (int)$halls['count'] . " mapped features.\n";
    echo "Note: zero results is valid. A specific named venue is not guaranteed to be mapped.\n";
} catch (ServiceException $e) {
    fwrite(STDERR,"FAILED: " . $e->getMessage() . " (application HTTP status " . $e->status() . ")\n");
    fwrite(STDERR,"For provider HTTP status or network error code check backend/logs/error.log and info.log.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR,"FAILED: Unexpected local setup error; inspect PHP/Apache logs.\n");
    exit(1);
}
