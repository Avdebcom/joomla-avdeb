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
 * Products + options → ready-to-print card view models. Port of view.ts. Pure.
 */
final class View
{
    private const RATIO_SIZE = ['square' => [600, 600], 'portrait' => [600, 750], 'landscape' => [600, 450]];
    public const RATIO_CSS   = ['square' => '1 / 1', 'portrait' => '4 / 5', 'landscape' => '4 / 3'];
    private const NEW_DAYS   = 14;
    private const SYMBOLS    = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$', 'JPY' => '¥'];

    /** "en-GB" (Joomla language tag) → "$1.90" / "1,90 €" via intl when available. */
    public static function formatPrice(?float $amount, string $currency, string $locale = 'en-US'): ?string
    {
        if ($amount === null) {
            return null;
        }

        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter(str_replace('-', '_', $locale), \NumberFormatter::CURRENCY);
            $out = $fmt->formatCurrency($amount, $currency);

            if (\is_string($out) && $out !== '') {
                return $out;
            }
        }

        $digits = $currency === 'JPY' ? 0 : 2;

        return isset(self::SYMBOLS[$currency])
            ? self::SYMBOLS[$currency] . number_format($amount, $digits)
            : number_format($amount, $digits) . ' ' . $currency;
    }

    /** Whole-number discount (e.g. 25 for 25 % off), or null when there's no real markdown. */
    public static function discountPercent(?float $price, ?float $compareAt): ?int
    {
        if ($price === null || $compareAt === null || $compareAt <= $price || $compareAt <= 0) {
            return null;
        }

        $pct = (int) round((1 - $price / $compareAt) * 100);

        return $pct >= 1 && $pct < 100 ? $pct : null;
    }

    public static function isNew(?string $createdAt, int $now, int $days = self::NEW_DAYS): bool
    {
        $t = $createdAt ? strtotime($createdAt) : false;

        return $t !== false && $t <= $now && $now - $t < $days * 86400;
    }

    /** Referral links are paid links: `sponsored` (Google) — plus `noopener` for new tabs. */
    public static function relFor(string $url): string
    {
        return Catalog::hasRef($url) ? 'sponsored noopener' : 'noopener';
    }

    /** @return array<int, array<string, mixed>> */
    public static function buildCards(array $products, array $o, string $locale = 'en-US', ?int $now = null): array
    {
        $now             = $now ?? time();
        [$width, $height] = self::RATIO_SIZE[$o['imageRatio']] ?? self::RATIO_SIZE['square'];
        $cards           = [];

        foreach ($products as $p) {
            if (!Catalog::isAvdebUrl($p['url'] ?? null)) {
                continue;
            }

            $hasSale = $o['showPrice'] && $p['compareAtPrice'] !== null && $p['price'] !== null && $p['compareAtPrice'] > $p['price'];
            $cards[] = [
                'key'       => $p['handle'],
                'href'      => $p['url'],
                'rel'       => self::relFor($p['url']),
                'target'    => $o['newTab'] ? '_blank' : null,
                'title'     => $p['title'],
                'image'     => $p['image'],
                'width'     => $width,
                'height'    => $height,
                'price'     => $o['showPrice'] ? self::formatPrice($p['price'], $p['currency'], $locale) : null,
                'compareAt' => $hasSale ? self::formatPrice($p['compareAtPrice'], $p['currency'], $locale) : null,
                'discount'  => $o['showBadges'] && $o['showPrice'] ? self::discountPercent($p['price'], $p['compareAtPrice']) : null,
                'isNew'     => $o['showBadges'] && self::isNew($p['createdAt'], $now),
                'creator'   => $o['showCreator'] && $p['creator'] ? $p['creator']['name'] : null,
            ];
        }

        return $cards;
    }

    /** "View all" target for a creator or category embed (with the referral code), else null. */
    public static function viewAllHref(array $query, array $products, string $ref): ?string
    {
        if (!empty($query['handle']) || !empty($query['q']) || !empty($query['type'])) {
            return null;
        }

        if (!empty($query['creator']) && empty($query['tag'])) {
            foreach ($products as $p) {
                if (($p['creator']['slug'] ?? null) === $query['creator'] && !empty($p['creator']['url'])) {
                    return $p['creator']['url'];
                }
            }

            return Catalog::avdebLink('/creators/' . $query['creator'] . '/', $ref) ?: null;
        }

        if (!empty($query['tag']) && empty($query['creator'])) {
            return Catalog::avdebLink('/category/' . $query['tag'] . '/', $ref) ?: null;
        }

        return null;
    }

    /** Inline CSS custom properties for an embed root. Values are validated numbers/hex only. */
    public static function rootStyle(array $o, int $columns): string
    {
        $parts = [
            '--avdeb-cols:' . max(1, min(6, $columns)),
            '--avdeb-radius:' . (int) $o['radius'] . 'px',
            '--avdeb-ratio:' . (self::RATIO_CSS[$o['imageRatio']] ?? '1 / 1'),
        ];

        if ($o['accentColor'] !== '') {
            $parts[] = '--avdeb-accent:' . $o['accentColor'];
        }

        return implode(';', $parts);
    }

    /** Columns actually used: never more than there are products (a lone card shouldn't be 1/3 wide). */
    public static function effectiveColumns(array $o, int $count): int
    {
        if ($o['layout'] === 'list') {
            return 1;
        }

        return max(1, min($o['columns'], $count ?: 1));
    }
}
