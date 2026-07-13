<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Support;

/**
 * Base-URL guard for connector endpoints. Only http/https with a host are
 * allowed — file://, ftp://, javascript: and schemeless input are rejected. We
 * deliberately do NOT block private/loopback hosts: Jellyfin and Audiobookshelf
 * commonly run on the LAN or localhost. This is not a general SSRF sink — the
 * connectors only ever call fixed, harmless diagnostic paths on this base.
 */
final class BaseUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($scheme)
            && is_string($host)
            && $host !== ''
            && in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    /** Trim and drop a trailing slash so paths can be concatenated safely. */
    public static function normalize(string $value): string
    {
        return rtrim(trim($value), '/');
    }
}
