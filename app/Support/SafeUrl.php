<?php

namespace App\Support;

use GuzzleHttp\Psr7\UriNormalizer;
use GuzzleHttp\Psr7\Utils;
use Throwable;

class SafeUrl
{
    /**
     * Schemes we are willing to fetch over. Anything else (file://, gopher://,
     * ftp://, …) is a classic SSRF vector and is rejected outright.
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Canonicalize a URL so equivalent variants collapse to one key — lowercased
     * scheme/host, default ports and dot-segments removed, fragment dropped, and a
     * trailing slash stripped from non-root paths. Keeps crawls from ingesting
     * `/x` and `/x/` (or `example.com` and `example.com/`) as separate documents.
     */
    public static function normalize(string $url): string
    {
        try {
            $uri = UriNormalizer::normalize(
                Utils::uriFor($url),
                UriNormalizer::PRESERVING_NORMALIZATIONS,
            )->withFragment('');
        } catch (Throwable) {
            return $url;
        }

        $path = $uri->getPath();

        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            $uri = $uri->withPath(rtrim($path, '/'));
        }

        return (string) $uri;
    }

    /**
     * Full guard for user-supplied URLs at ingestion time: the URL must be a valid
     * http(s) URL whose host resolves *only* to public IP addresses. Performs DNS
     * resolution, so a hostname that points at a private/loopback/link-local address
     * is rejected too.
     */
    public static function isPublic(string $url): bool
    {
        $host = self::hostFor($url);

        if ($host === null) {
            return false;
        }

        $ips = self::resolve($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lightweight, DNS-free guard used inside queue jobs before each individual fetch:
     * it rejects non-http(s) schemes and private/reserved IP-literal hosts without a
     * network round-trip. Hostname → private-IP targets are already blocked at ingestion
     * time by isPublic(), so this is defense-in-depth for URLs discovered mid-crawl.
     */
    public static function hasSafeTarget(string $url): bool
    {
        $host = self::hostFor($url);

        if ($host === null) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        return true;
    }

    /**
     * Parse and validate the scheme, returning the (bracket-stripped) host or null when
     * the URL is malformed or uses a disallowed scheme.
     */
    private static function hostFor(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        return trim($parts['host'], '[]');
    }

    /**
     * Resolve a host to its IP addresses (both A and AAAA records). An IP literal is
     * returned as-is.
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);

        if (is_array($v4)) {
            $ips = array_merge($ips, $v4);
        }

        $v6 = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * A public IP is one that falls outside the private and reserved ranges, which
     * covers 10/8, 172.16/12, 192.168/16, 127/8 (loopback), 169.254/16 (link-local),
     * and the IPv6 equivalents.
     */
    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
