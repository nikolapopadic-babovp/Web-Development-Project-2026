<?php
/** Small, safe Milestone 3 logger for the external API; M4 adds request middleware. */
final class ExternalApiLog
{
    public static function info(string $message): void { self::write('info.log', 'INFO', $message); }
    public static function failure(string $message): void { self::write('error.log', 'ERROR', $message); }

    private static function write(string $filename, string $level, string $message): void
    {
        // Messages are developer-controlled (never include the API key, response body or coordinates).
        $entry = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $level . ' ExternalAPI ' . $message . PHP_EOL;
        $path = __DIR__ . '/../logs/' . $filename;
        if (@file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) === false) {
            error_log('[COURTSIDE] ' . trim($entry));
        }
    }
}
