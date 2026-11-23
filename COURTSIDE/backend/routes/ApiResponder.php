<?php
/** HTTP formatting belongs to the presentation layer, never to DAOs/services. */
final class ApiResponder
{
    public static function handle(callable $action, int $successCode = 200, string $message = 'OK'): void
    {
        try {
            self::send($action(), $successCode, $message);
        } catch (Throwable $error) {
            self::error($error);
        }
    }

    public static function send(mixed $data, int $status = 200, string $message = 'OK'): void
    {
        Flight::json(['data' => $data, 'message' => $message], $status);
    }

    public static function error(Throwable $error): void
    {
        if ($error instanceof ServiceException) {
            self::send(null, $error->status(), $error->getMessage());
            return;
        }
        if ($error instanceof PDOException) {
            // MySQL constraint violations: duplicate, cannot delete referenced row, invalid FK.
            $code = (int) ($error->errorInfo[1] ?? 0);
            if ($code === 1062) {
                self::send(null, 409, 'This record already exists.');
                return;
            }
            if ($code === 1451 || $code === 1452) {
                self::send(null, 409, 'The operation conflicts with a related record.');
                return;
            }
        }
        // Do not leak exception messages, database credentials, SQL, or file paths.
        error_log('[COURTSIDE API] ' . get_class($error) . ': ' . $error->getMessage());
        self::send(null, 500, 'An internal server error occurred.');
    }

    public static function body(): array
    {
        $raw = Flight::request()->getBody();
        if ($raw === '' || trim($raw) === '') throw new ServiceException('JSON request body is required.');
        try {
            // Require an object, not a scalar or JSON array.
            $object = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ServiceException('Malformed JSON request body.');
        }
        if (!is_object($object)) throw new ServiceException('JSON request body must be an object.');
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
