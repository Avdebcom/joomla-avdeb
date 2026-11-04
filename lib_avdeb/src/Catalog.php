<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Library;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * AVDEB catalog API contract: query sanitising, response validation, link helpers.
 * PHP port of emdash-plugin-avdeb/src/catalog.ts. Pure: no Joomla calls, safe to unit-test.
 *
 * A product is an array{handle: string, title: string, url: string, image: ?string, price: ?float,
 * compareAtPrice: ?float, currency: string, type: ?string, tags: string[],
 * creator: ?array{slug: string, name: string, url: ?string}, createdAt: ?string}.
 */
final class Catalog
{
    public const API_HOST    = 'api.avdeb.com';
    public const API_BASE    = 'https://api.avdeb.com/v1/catalog';
    public const SITE_ORIGIN = 'https://avdeb.com';

    /** Secret keys are issued as `avdeb_sk_` + 32 letters/digits (partner dashboard → Embeds & API). */
    public const SECRET_KEY_RE          = '/^avdeb_sk_[A-Za-z0-9]{32}$/';
    public const PUBLISHABLE_KEY_PREFIX = 'avdeb_pk_';

    public const MAX_LIMIT_ANONYMOUS = 12;
    public const MAX_LIMIT_SECRET    = 48;
    public const DEFAULT_LIMIT       = 6;

    private const CONTROL_RE  = '/[[:cntrl:]]+/';
    private const CURRENCY_RE = '/^[A-Z]{3}$/';

    public static function str(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return \is_int($value) || \is_float($value) ? (string) $value : '';
    }

    /** Referral codes are letters, digits, "-" and "_" (as issued in the AVDEB affiliate dashboard). */
    public static function cleanRef(mixed $code): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', self::str($code)), 0, 40);
    }

    /** Lower-case URL slug (creator, category, product handle). */
    public static function cleanSlug(mixed $value): string
    {
        $slug = (string) preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim(self::str($value))));

        return substr(trim($slug, '-'), 0, 100);
    }

    /** Product type such as "Sticker" or "Phone Case". */
    public static function cleanType(mixed $value): string
    {
        $type = (string) preg_replace("/[^\\p{L}\\p{N} &'-]+/u", '', self::str($value));

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $type)), 0, 40);
    }

    /** Free-text search phrase. */
    public static function cleanSearch(mixed $value): string
    {
        $q = (string) preg_replace(self::CONTROL_RE, ' ', self::str($value));

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $q)), 0, 100);
    }

    /** null for a usable secret key (or empty input), otherwise "publishable" or "malformed". */
    public static function keyProblem(mixed $key): ?string
    {
        $k = trim(self::str($key));

        if ($k === '' || preg_match(self::SECRET_KEY_RE, $k)) {
            return null;
        }

        return str_starts_with($k, self::PUBLISHABLE_KEY_PREFIX) ? 'publishable' : 'malformed';
    }

    /** The key when it is a well-formed secret key, otherwise "". */
    public static function usableKey(mixed $key): string
    {
        $k = trim(self::str($key));

        return preg_match(self::SECRET_KEY_RE, $k) ? $k : '';
    }

    public static function maxLimit(bool $hasKey): int
    {
        return $hasKey ? self::MAX_LIMIT_SECRET : self::MAX_LIMIT_ANONYMOUS;
    }

    public static function isAvdebUrl(mixed $url): bool
    {
        if (!\is_string($url) || $url === '' || preg_match('/[\s[:cntrl:]"\'<>\\\\]/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        if (!\is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        return $host === 'avdeb.com' || $host === 'www.avdeb.com';
    }

    /**
     * Absolute avdeb.com URL for an avdeb.com URL or a site path ("/store/cat/"), with `ref` set when
     * given. Anything pointing elsewhere returns "" so callers never link off-site by accident.
     */
    public static function avdebLink(mixed $input, string $ref = ''): string
    {
        $raw = trim(self::str($input));

        if ($raw === '') {
            return '';
        }

        if (str_starts_with($raw, '/') && !str_starts_with($raw, '//')) {
            $raw = self::SITE_ORIGIN . $raw;
        } elseif (preg_match('#^(www\.)?avdeb\.com(/|$)#i', $raw)) {
            $raw = 'https://' . $raw;
        }

        if (!self::isAvdebUrl($raw)) {
            return '';
        }

        $code = self::cleanRef($ref);

        return $code === '' ? $raw : self::withQueryParam($raw, 'ref', $code);
    }

    /** Set (or replace) one query parameter, keeping path, other parameters and the fragment. */
    public static function withQueryParam(string $url, string $name, string $value): string
    {
        $parts = parse_url($url);
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query[$name] = $value;

        return ($parts['scheme'] ?? 'https') . '://' . strtolower($parts['host'] ?? '')
            . ($parts['path'] ?? '/')
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    public static function hasRef(string $url): bool
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return \array_key_exists('ref', $query);
    }

    /**
     * Recognise an AVDEB page a user pasted: /creators/{slug}/, /category/{slug}/, /store/{handle}/.
     * Accepts full URLs, scheme-less "avdeb.com/…" and bare paths.
     *
     * @return array{kind: string, value: string}|null  kind is creator, tag or handle
     */
    public static function parseAvdebLink(mixed $input): ?array
    {
        $raw = trim(self::str($input));

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '/') && !str_starts_with($raw, '//')) {
            $path = $raw;
        } else {
            $withScheme = preg_match('#^https?://#i', $raw) ? $raw : 'https://' . $raw;
            $host       = strtolower((string) parse_url($withScheme, PHP_URL_HOST));

            if ($host !== 'avdeb.com' && $host !== 'www.avdeb.com') {
                return null;
            }

            $path = (string) parse_url($withScheme, PHP_URL_PATH);
        }

        $path     = preg_split('/[?#]/', $path)[0] ?? '';
        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
        $section  = strtolower($segments[0] ?? '');
        $value    = self::cleanSlug($segments[1] ?? '');
        $kind     = ['creators' => 'creator', 'category' => 'tag', 'store' => 'handle'][$section] ?? null;

        return $kind && $value !== '' ? ['kind' => $kind, 'value' => $value] : null;
    }

    /**
     * Validate an untrusted query. Unknown keys are ignored; an empty filter set means "newest
     * products". `limit` is clamped to the tier max; a handle always means one product.
     *
     * @return array{creator?: string, tag?: string, type?: string, handle?: string, q?: string, limit: int}
     */
    public static function sanitizeQuery(mixed $input, int $max): array
    {
        $src   = \is_array($input) ? $input : [];
        $query = [];

        foreach (['creator' => 'cleanSlug', 'tag' => 'cleanSlug', 'type' => 'cleanType', 'handle' => 'cleanSlug', 'q' => 'cleanSearch'] as $key => $fn) {
            $value = self::$fn($src[$key] ?? '');

            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        $limit          = is_numeric($src['limit'] ?? null) ? (int) $src['limit'] : 0;
        $query['limit'] = isset($query['handle'])
            ? 1
            : ($limit > 0 ? min($max, $limit) : min($max, self::DEFAULT_LIMIT));

        return $query;
    }

    /** Stable string for a query (cache key, request de-duplication). */
    public static function queryKey(array $q): string
    {
        return implode('|', [$q['creator'] ?? '', $q['tag'] ?? '', $q['type'] ?? '', $q['handle'] ?? '', $q['q'] ?? '', $q['limit'] ?? '']);
    }

    /** GET /v1/catalog/products URL. `ref` is only sent without a key (key holders get their own code). */
    public static function productsUrl(array $q, string $ref = ''): string
    {
        $params = [];

        foreach (['creator', 'tag', 'type', 'handle', 'q'] as $k) {
            if (!empty($q[$k])) {
                $params[$k] = $q[$k];
            }
        }

        $params['limit'] = (string) ($q['limit'] ?? self::DEFAULT_LIMIT);
        $code            = self::cleanRef($ref);

        if ($code !== '') {
            $params['ref'] = $code;
        }

        return self::API_BASE . '/products?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private static function money(mixed $value): ?float
    {
        return (\is_int($value) || \is_float($value)) && is_finite((float) $value) && $value >= 0 ? (float) $value : null;
    }

    /** Keep only the fields the extensions render, validated. null for anything unusable. */
    public static function normalizeProduct(mixed $raw): ?array
    {
        if (!\is_array($raw)) {
            return null;
        }

        $handle = self::cleanSlug($raw['handle'] ?? '');
        $title  = mb_substr(trim((string) preg_replace(self::CONTROL_RE, ' ', self::str($raw['title'] ?? ''))), 0, 200);
        $url    = self::str($raw['url'] ?? '');

        if ($handle === '' || $title === '' || !self::isAvdebUrl($url)) {
            return null;
        }

        $creator = null;

        if (\is_array($raw['creator'] ?? null)) {
            $slug = self::cleanSlug($raw['creator']['slug'] ?? '');

            if ($slug !== '') {
                $creatorUrl = self::str($raw['creator']['url'] ?? '');
                $creator    = [
                    'slug' => $slug,
                    'name' => mb_substr(trim(self::str($raw['creator']['name'] ?? '')), 0, 120) ?: $slug,
                    'url'  => self::isAvdebUrl($creatorUrl) ? $creatorUrl : null,
                ];
            }
        }

        $image     = self::str($raw['image'] ?? '');
        $currency  = strtoupper(self::str($raw['currency'] ?? ''));
        $createdAt = self::str($raw['created_at'] ?? '');
        $tags      = \is_array($raw['tags'] ?? null) ? array_slice(array_values(array_filter(array_map([self::class, 'cleanSlug'], $raw['tags']))), 0, 20) : [];

        return [
            'handle'         => $handle,
            'title'          => $title,
            'url'            => $url,
            'image'          => str_starts_with($image, 'https://') && !preg_match('/[\s"\'<>]/', $image) ? $image : null,
            'price'          => self::money($raw['price'] ?? null),
            'compareAtPrice' => self::money($raw['compare_at_price'] ?? null),
            'currency'       => preg_match(self::CURRENCY_RE, $currency) ? $currency : 'USD',
            'type'           => self::cleanType($raw['type'] ?? '') ?: null,
            'tags'           => $tags,
            'creator'        => $creator,
            'createdAt'      => $createdAt !== '' && strtotime($createdAt) !== false ? $createdAt : null,
        ];
    }

    /** @return array{products: array[], ref: string, tier: string}|null */
    public static function parseProductsResponse(mixed $json): ?array
    {
        if (!\is_array($json) || !\is_array($json['products'] ?? null) || !array_is_list($json['products'])) {
            return null;
        }

        $products = [];
        $seen     = [];

        foreach ($json['products'] as $raw) {
            $p = self::normalizeProduct($raw);

            if ($p && !isset($seen[$p['handle']])) {
                $seen[$p['handle']] = true;
                $products[]         = $p;
            }
        }

        $tier = \in_array($json['tier'] ?? null, ['secret', 'publishable'], true) ? $json['tier'] : 'anonymous';

        return ['products' => $products, 'ref' => self::cleanRef($json['ref'] ?? ''), 'tier' => $tier];
    }

    /** @return array{type: string, name: string, ref: string, creatorSlug: string}|null */
    public static function parseKeyResponse(mixed $json): ?array
    {
        if (!\is_array($json) || !\is_array($json['owner'] ?? null)) {
            return null;
        }

        $o = $json['owner'];

        return [
            'type'        => substr(strtolower((string) preg_replace('/[^a-z_-]/i', '', self::str($o['type'] ?? ''))), 0, 20),
            'name'        => mb_substr(trim(self::str($o['name'] ?? '')), 0, 120),
            'ref'         => self::cleanRef($o['ref'] ?? ''),
            'creatorSlug' => self::cleanSlug($o['creator_slug'] ?? ''),
        ];
    }

    /** Error message from an API error body (`{ "error": "…" }`) or the HTTP status. */
    public static function apiErrorMessage(int $status, mixed $json): string
    {
        $msg = \is_array($json) ? mb_substr(trim(self::str($json['error'] ?? '')), 0, 200) : '';

        return $msg !== '' ? $msg : 'HTTP ' . $status;
    }
}
