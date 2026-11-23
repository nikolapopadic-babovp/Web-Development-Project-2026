<?php
/** Single public FlightPHP entrypoint, for http://localhost/.../backend/api/courts */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => null, 'message' => 'Backend dependencies missing. Run composer install inside backend/.']);
    exit;
}
require_once $autoload;

Flight::set('flight.debug', false);
Flight::set('flight.allow_method_override', false);
Flight::map('notFound', static function (): void {
    ApiResponder::send(null, 404, 'Route not found.');
});
Flight::map('error', static function (Throwable $error): void {
    ApiResponder::error($error);
});

require_once __DIR__ . '/routes/api.php';
require_once __DIR__ . '/routes/external.php';
require_once __DIR__ . '/routes/docs.php';

Flight::start();