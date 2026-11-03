<?php
declare(strict_types=1);

namespace gCore\Contracts\Events;

/** Identifiers: random, URL- and key-safe, never derived from personal data. */
final class Ids
{
    public static function new(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    public static function valid(string $id): bool
    {
        return (bool)preg_match('/\A[a-z][a-z0-9]*_[a-f0-9]{16}\z/', $id) || (bool)preg_match('/\A[a-z0-9][a-z0-9_.-]{0,63}\z/', $id);
    }
}
