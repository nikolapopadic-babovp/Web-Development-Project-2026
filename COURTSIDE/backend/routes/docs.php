<?php
/** Public documentation pages. Swagger UI consumes the exact OpenAPI JSON served here. */
Flight::route('GET /api/openapi.json', static function (): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    readfile(__DIR__ . '/../openapi.json');
});
Flight::route('GET /api/docs', static function (): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    readfile(__DIR__ . '/../swagger/index.html');
});
