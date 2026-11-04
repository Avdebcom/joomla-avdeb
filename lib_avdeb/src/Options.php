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
 * Embed options: what one shortcode / module shows and how. Site-wide display defaults come from the
 * content plugin's settings; every embed can override them. Port of config.ts (Wix/Squarespace) and
 * the EmDash block fields. Pure.
 *
 * Attribute names are forgiving: `card-style`, `card_style` and `cardStyle` all mean cardStyle.
 */
final class Options
{
    public const MODES            = ['collection', 'product'];
    public const SOURCES          = ['auto', 'creator', 'tag', 'type', 'search', 'newest'];
    public const LAYOUTS          = ['grid', 'carousel', 'list', 'spotlight'];
    public const CARD_STYLES      = ['elevated', 'outline', 'minimal'];
    public const THEMES           = ['site', 'light', 'dark', 'auto'];
    public const IMAGE_RATIOS     = ['square', 'portrait', 'landscape'];
    public const PRODUCT_VARIANTS = ['card', 'horizontal', 'hero'];
    public const BUTTON_VARIANTS  = ['solid', 'outline', 'link'];
    public const ALIGNMENTS       = ['start', 'center', 'end'];

    /** Site-wide display defaults (plugin settings); each embed may override them. */
    public const DISPLAY_DEFAULTS = [
        'layout'      => 'grid',
        'columns'     => 3,
        'cardStyle'   => 'elevated',
        'theme'       => 'site',
        'accentColor' => '',
        'radius'      => 16,
        'imageRatio'  => 'square',
        'ctaLabel'    => 'View product',
        'showPrice'   => true,
        'showCreator' => true,
        'showBadges'  => true,
        'newTab'      => false,
    ];

    public const EMBED_DEFAULTS = [
        'mode'    => 'collection',
        'source'  => 'auto',
        'value'   => '',
        'limit'   => Catalog::DEFAULT_LIMIT,
        'variant' => 'horizontal',
        'badge'   => '',
        'note'    => '',
        'heading' => '',
        'intro'   => '',
        'viewAll' => true,
    ];

    /** Accepted attribute spellings → canonical key (lower-cased, without "-" and "_"). */
    private const ALIASES = [
        'link'        => 'value',
        'url'         => 'value',
        'search'      => 'value',
        'id'          => 'value',
        'show'        => 'mode',
        'buttontext'  => 'ctaLabel',
        'accent'      => 'accentColor',
        'ratio'       => 'imageRatio',
        'cols'        => 'columns',
        'count'       => 'limit',
        // mod_avdeb_products: Joomla reserves the module param `layout` for the template override file.
        'embedlayout' => 'layout',
    ];

    /** Normalise attribute names: "card-style" / "card_style" / "CardStyle" → "cardStyle". */
    public static function normalizeKeys(array $attrs): array
    {
        static $canonical = null;

        if ($canonical === null) {
            $canonical = [];

            foreach (array_merge(array_keys(self::DISPLAY_DEFAULTS), array_keys(self::EMBED_DEFAULTS), ['hidePrice', 'hideCreator', 'handle', 'creator', 'tag', 'type', 'q', 'product']) as $key) {
                $canonical[strtolower($key)] = $key;
            }
        }

        $out = [];

        foreach ($attrs as $name => $value) {
            $flat = strtolower(str_replace(['-', '_'], '', (string) $name));
            $key  = self::ALIASES[$flat] ?? $canonical[$flat] ?? null;

            if ($key !== null && !\array_key_exists($key, $out)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function oneOf(mixed $value, array $allowed): ?string
    {
        $v = \is_string($value) ? strtolower(trim($value)) : '';

        return \in_array($v, $allowed, true) ? $v : null;
    }

    public static function int(mixed $value, int $min, int $max, int $fallback): int
    {
        if (\is_bool($value) || !is_numeric(\is_string($value) ? trim($value) : $value)) {
            return $fallback;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    public static function bool(mixed $value, bool $fallback): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        $v = strtolower(trim(Catalog::str($value)));

        return match ($v) {
            '1', 'true', 'yes', 'on'  => true,
            '0', 'false', 'no', 'off' => false,
            default                   => $fallback,
        };
    }

    public static function text(mixed $value, int $max, string $fallback = ''): string
    {
        $v = mb_substr(trim((string) preg_replace('/\s+/u', ' ', Catalog::str($value))), 0, $max);

        return $v !== '' ? $v : $fallback;
    }

    /** A hex colour or "" — the value is placed in a style attribute, so nothing else is accepted. */
    public static function safeColor(mixed $value): string
    {
        $v = trim(Catalog::str($value));

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $v) ? strtolower($v) : '';
    }

    /**
     * Validate display settings. Invalid or missing values fall back to $base (the built-in defaults
     * for the plugin settings, the site settings for one embed's overrides).
     */
    public static function display(array $params, array $base = self::DISPLAY_DEFAULTS): array
    {
        $d = $base + self::DISPLAY_DEFAULTS;
        $v = self::normalizeKeys($params);
        $theme = ($v['theme'] ?? '') === 'inherit' ? 'site' : ($v['theme'] ?? null);

        return [
            'layout'      => self::oneOf($v['layout'] ?? null, self::LAYOUTS) ?? $d['layout'],
            'columns'     => self::int($v['columns'] ?? null, 1, 6, $d['columns']),
            'cardStyle'   => self::oneOf($v['cardStyle'] ?? null, self::CARD_STYLES) ?? $d['cardStyle'],
            'theme'       => self::oneOf($theme, self::THEMES) ?? $d['theme'],
            'accentColor' => self::safeColor($v['accentColor'] ?? '') ?: self::safeColor($d['accentColor']),
            'radius'      => self::int($v['radius'] ?? null, 0, 32, $d['radius']),
            'imageRatio'  => self::oneOf($v['imageRatio'] ?? null, self::IMAGE_RATIOS) ?? $d['imageRatio'],
            'ctaLabel'    => self::text($v['ctaLabel'] ?? '', 40, $d['ctaLabel']),
            'showPrice'   => self::bool($v['showPrice'] ?? null, $d['showPrice']),
            'showCreator' => self::bool($v['showCreator'] ?? null, $d['showCreator']),
            'showBadges'  => self::bool($v['showBadges'] ?? null, $d['showBadges']),
            'newTab'      => self::bool($v['newTab'] ?? null, $d['newTab']),
        ];
    }

    /**
     * One embed: its own attributes over the site display defaults. Empty values and "default" fall
     * back to the site setting (module params use "" for "use plugin default").
     */
    public static function parse(array $attrs, array $siteDisplay = self::DISPLAY_DEFAULTS): array
    {
        $v = array_filter(
            self::normalizeKeys($attrs),
            static fn ($x) => !(\is_string($x) && (trim($x) === '' || strtolower(trim($x)) === 'default')) && $x !== null
        );

        // Explicit filters (creator="…", tag="…", handle="…", q="…") are shorthands for source + value.
        foreach (['creator' => 'creator', 'tag' => 'tag', 'type' => 'type', 'q' => 'search', 'handle' => null, 'product' => null] as $key => $source) {
            if (!isset($v[$key])) {
                continue;
            }

            if ($source === null) {
                $v['mode']  = 'product';
                $v['value'] = $v[$key];
            } elseif (!isset($v['value'])) {
                $v['source'] = $source;
                $v['value']  = $v[$key];
            }
        }

        $display = self::display(array_intersect_key($v, self::DISPLAY_DEFAULTS), $siteDisplay);

        if (self::bool($v['hidePrice'] ?? null, false)) {
            $display['showPrice'] = false;
        }

        if (self::bool($v['hideCreator'] ?? null, false)) {
            $display['showCreator'] = false;
        }

        $e = self::EMBED_DEFAULTS;

        return $display + [
            'mode'    => self::oneOf($v['mode'] ?? null, self::MODES) ?? $e['mode'],
            'source'  => self::oneOf($v['source'] ?? null, self::SOURCES) ?? $e['source'],
            'value'   => self::text($v['value'] ?? '', 300),
            'limit'   => self::int($v['limit'] ?? null, 1, Catalog::MAX_LIMIT_SECRET, $e['limit']),
            'variant' => self::oneOf($v['variant'] ?? null, self::PRODUCT_VARIANTS) ?? $e['variant'],
            'badge'   => self::text($v['badge'] ?? '', 40),
            'note'    => mb_substr(trim(Catalog::str($v['note'] ?? '')), 0, 600),
            'heading' => self::text($v['heading'] ?? '', 120),
            'intro'   => self::text($v['intro'] ?? '', 400),
            'viewAll' => self::bool($v['viewAll'] ?? null, $e['viewAll']),
        ];
    }

    /** Product handle from a pasted product link or a bare handle ("" for anything else). */
    public static function productHandle(string $value): string
    {
        $link = Catalog::parseAvdebLink($value);

        if ($link) {
            return $link['kind'] === 'handle' ? $link['value'] : '';
        }

        return preg_match('#[/.:]#', $value) ? '' : Catalog::cleanSlug($value);
    }

    /** Unsanitised catalog query (the client re-validates and clamps the limit to the tier). */
    public static function queryFor(array $o): array
    {
        if ($o['mode'] === 'product') {
            $handle = self::productHandle($o['value']);

            return $handle !== '' ? ['handle' => $handle, 'limit' => 1] : ['limit' => 1];
        }

        $raw   = $o['value'];
        $link  = Catalog::parseAvdebLink($raw);
        $query = ['limit' => $o['limit']];
        $slug  = static fn (string $kind) => $link && $link['kind'] === $kind ? $link['value'] : Catalog::cleanSlug($raw);
        $set   = static function (string $key, string $value) use (&$query): void {
            if ($value !== '') {
                $query[$key] = $value;
            }
        };

        switch ($o['source']) {
            case 'creator':
                $set('creator', $slug('creator'));
                break;
            case 'tag':
                $set('tag', $slug('tag'));
                break;
            case 'type':
                $set('type', Catalog::cleanType($raw));
                break;
            case 'search':
                $set('q', Catalog::cleanSearch($raw));
                break;
            case 'newest':
                break;
            default:
                $link ? $set($link['kind'], $link['value']) : $set('q', Catalog::cleanSearch($raw));
        }

        return $query;
    }

    /** Button: { href, label, variant, align }. */
    public static function button(array $attrs): array
    {
        $v = array_change_key_case($attrs, CASE_LOWER);

        return [
            'href'    => trim(Catalog::str($v['href'] ?? $v['link'] ?? $v['url'] ?? '')) ?: '/',
            'label'   => self::text($v['label'] ?? $v['text'] ?? '', 80, 'Shop on AVDEB'),
            'variant' => self::oneOf($v['variant'] ?? $v['style'] ?? null, self::BUTTON_VARIANTS) ?? 'solid',
            'align'   => self::oneOf($v['align'] ?? null, self::ALIGNMENTS) ?? 'start',
        ];
    }
}
