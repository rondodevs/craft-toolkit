<?php

namespace rondodevs\toolkit\support;

/**
 * Settings arrive as form strings, JSON numbers or DB values: one way to read them.
 */
final class Normalize
{
    public static function string(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    public static function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }

    public static function positiveInt(mixed $value): ?int
    {
        $value = self::int($value);

        return $value !== null && $value > 0 ? $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return $value === null || $value === '' ? null : (bool)$value;
    }

    public static function path(mixed $value): ?string
    {
        $value = self::string($value);

        return $value !== null ? '/' . ltrim($value, '/') : null;
    }

    /**
     * An absolute URL without the trailing slash, or null; anything else is refused.
     */
    public static function url(mixed $value, string $label): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException("$label must be a valid absolute URL.");
        }

        return rtrim($value, '/');
    }
}
