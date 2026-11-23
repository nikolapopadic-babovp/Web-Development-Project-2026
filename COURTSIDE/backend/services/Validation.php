<?php
/** Reusable input validation. No SQL or HTTP framework dependencies here. */
final class Validation
{
    public static function fields(array $data, array $allowed, array $required = []): void
    {
        if ($data === []) {
            throw new ServiceException('Request body must contain at least one field.');
        }
        foreach (array_keys($data) as $name) {
            if (!is_string($name) || !in_array($name, $allowed, true)) {
                throw new ServiceException('Unknown or read-only field: ' . (string) $name);
            }
        }
        foreach ($required as $name) {
            if (!array_key_exists($name, $data) || $data[$name] === null ||
                (is_string($data[$name]) && trim($data[$name]) === '')) {
                throw new ServiceException("Missing required field: $name");
            }
        }
    }

    public static function text(mixed $value, string $name, int $max = 255, bool $nullable = false): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (!is_string($value)) {
            throw new ServiceException("$name must be a string.");
        }
        $value = trim($value);
        if ($value === '' && !$nullable) {
            throw new ServiceException("$name cannot be empty.");
        }
        if ($nullable && $value === '') {
            return null;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $max) {
            throw new ServiceException("$name must contain at most $max characters.");
        }
        return $value;
    }

    public static function email(mixed $value): string
    {
        $email = self::text($value, 'email', 150);
        $email = strtolower($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ServiceException('Email address is invalid.');
        }
        return $email;
    }

    public static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 8 || strlen($value) > 72) {
            throw new ServiceException('Password must contain 8 to 72 bytes.');
        }
        return $value;
    }

    public static function integer(mixed $value, string $name, int $min = 1, int $max = PHP_INT_MAX): int
    {
        if (is_bool($value) || (!is_int($value) && !is_string($value)) ||
            filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ServiceException("$name must be a whole number.");
        }
        $number = (int) $value;
        if ($number < $min || $number > $max) {
            throw new ServiceException("$name must be between $min and $max.");
        }
        return $number;
    }

    public static function id(mixed $value, string $name = 'id'): int
    {
        return self::integer($value, $name, 1);
    }

    public static function boolean(mixed $value, string $name): int
    {
        if ($value === true || $value === 1 || $value === '1') return 1;
        if ($value === false || $value === 0 || $value === '0') return 0;
        throw new ServiceException("$name must be a boolean (true/false or 1/0).");
    }

    public static function number(mixed $value, string $name, float $min, float $max): float
    {
        if (is_bool($value) || (!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
            throw new ServiceException("$name must be a number.");
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < $min || $number > $max) {
            throw new ServiceException("$name must be between $min and $max.");
        }
        return $number;
    }

    public static function oneOf(mixed $value, string $name, array $options): string
    {
        if (!is_string($value) || !in_array($value, $options, true)) {
            throw new ServiceException("$name must be one of: " . implode(', ', $options) . '.');
        }
        return $value;
    }

    /** Store MySQL DATETIME in UTC. Plain SQL datetime input is interpreted as UTC. */
    public static function utcDate(mixed $value, string $name = 'starts_at'): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2}|T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))$/', $value)) {
            throw new ServiceException("$name must be a UTC datetime (YYYY-MM-DD HH:MM:SS) or ISO 8601 with timezone.");
        }
        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new ServiceException("$name is not a valid datetime.");
        }
        // DateTime normalizes invalid calendar dates silently; reject normalization.
        if (str_contains($value, ' ') && $date->format('Y-m-d H:i:s') !== $value) {
            throw new ServiceException("$name is not a valid datetime.");
        }
        $parseErrors = DateTimeImmutable::getLastErrors();
        if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
            throw new ServiceException("$name is not a valid datetime.");
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function pagination(array $query): array
    {
        return [
            self::integer($query['limit'] ?? 50, 'limit', 1, 100),
            self::integer($query['offset'] ?? 0, 'offset', 0, 1000000),
        ];
    }
}
