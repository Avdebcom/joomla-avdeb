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
 * HTML for every layout — grid, carousel, list, spotlight — the single-product variants (card /
 * horizontal / hero) and the button. Same markup and BEM classes as emdash-plugin-avdeb's Embed.astro,
 * so media/css/avdeb.css is shared. Pure: every value is escaped, URLs are validated by View/Catalog,
 * style values are numbers/hex only. The only script (media/js/avdeb.js) adds carousel arrows.
 */
final class Renderer
{
    private const ARROW       = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3 8h9.5M8.5 4 12.5 8l-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    private const PLACEHOLDER = '<svg class="avdeb-card__placeholder" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z M4 7.5l8 4.5 8-4.5 M12 12v9" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>';
    private const INFO        = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><circle cx="8" cy="8" r="6.25" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M8 7.2v4M8 4.8v.1" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

    public static function e(mixed $value): string
    {
        return htmlspecialchars(Catalog::str($value), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function attr(string $name, mixed $value): string
    {
        return $value === null ? '' : ' ' . $name . '="' . self::e($value) . '"';
    }

    private static function chevron(string $dir): string
    {
        $d = $dir === 'right' ? 'M6 3.5 10.5 8 6 12.5' : 'M10 3.5 5.5 8 10 12.5';

        return '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /**
     * @param array{products: array[], ref: string, query: array, disclosure: ?string} $data
     * @param array $o  Options::parse() result
     * @param int   $headingLevel  Level of the section heading (card titles use the next level)
     */
    public static function embed(array $data, array $o, string $locale = 'en-US', int $headingLevel = 2, ?int $now = null): string
    {
        $single = $o['mode'] === 'product';
        $cards  = View::buildCards($single ? \array_slice($data['products'], 0, 1) : $data['products'], $o, $locale, $now);

        if (!$cards) {
            return '<!-- AVDEB Products: nothing to show for this embed -->';
        }

        $layout        = $single ? 'single' : $o['layout'];
        $isCarousel    = $layout === 'carousel';
        $columns       = $single ? 1 : View::effectiveColumns($o, \count($cards));
        $allHref       = !$single && $o['viewAll'] ? View::viewAllHref($data['query'], $data['products'], $data['ref']) : null;
        $featuredFirst = $single ? $o['variant'] !== 'card' : $layout === 'spotlight';
        $heading       = $single ? '' : $o['heading'];
        $intro         = $single ? '' : $o['intro'];
        $label         = $heading ?: ($single ? $cards[0]['title'] : '') ?: 'AVDEB products';
        $newTab        = $o['newTab'] ? '_blank' : null;
        $h             = max(2, min(5, $headingLevel));
        $hCard         = min(6, $h + 1);

        $classes = implode(' ', array_filter(['avdeb', "avdeb--{$layout}", $single ? "avdeb--{$o['variant']}" : '', "avdeb--{$o['cardStyle']}"]));

        $header = '';

        if ($heading !== '' || $intro !== '' || $allHref || $isCarousel) {
            $titles = $heading !== '' || $intro !== ''
                ? '<div class="avdeb__titles">'
                    . ($heading !== '' ? "<h{$h} class=\"avdeb__heading\">" . self::e($heading) . "</h{$h}>" : '')
                    . ($intro !== '' ? '<p class="avdeb__intro">' . self::e($intro) . '</p>' : '')
                    . '</div>'
                : '';
            $all = $allHref
                ? '<a class="avdeb__all"' . self::attr('href', $allHref) . self::attr('rel', View::relFor($allHref)) . self::attr('target', $newTab) . '>View all' . self::chevron('right') . '</a>'
                : '';
            $nav = $isCarousel
                ? '<div class="avdeb__nav"><button type="button" class="avdeb__navbtn" data-avdeb-prev aria-label="Previous products" hidden>' . self::chevron('left')
                    . '</button><button type="button" class="avdeb__navbtn" data-avdeb-next aria-label="Next products" hidden>' . self::chevron('right') . '</button></div>'
                : '';
            $header = '<header class="avdeb__header">' . $titles . ($all . $nav !== '' ? '<div class="avdeb__tools">' . $all . $nav . '</div>' : '') . '</header>';
        }

        $items = '';

        foreach ($cards as $i => $c) {
            $cardClasses = implode(' ', array_filter([
                'avdeb-card',
                $featuredFirst && $i === 0 ? 'avdeb-card--featured' : '',
                $single && $o['variant'] === 'hero' ? 'avdeb-card--hero' : '',
            ]));
            $badge  = $single ? $o['badge'] : '';
            $badges = $badge !== '' || $c['discount'] || $c['isNew']
                ? '<div class="avdeb-card__badges" aria-hidden="true">'
                    . ($badge !== '' ? '<span class="avdeb-badge avdeb-badge--pick">' . self::e($badge) . '</span>' : '')
                    . ($c['discount'] ? '<span class="avdeb-badge avdeb-badge--sale">−' . (int) $c['discount'] . '%</span>' : '')
                    . ($c['isNew'] ? '<span class="avdeb-badge">New</span>' : '')
                    . '</div>'
                : '';
            $media = $c['image']
                ? '<img' . self::attr('src', $c['image']) . self::attr('alt', $c['title']) . self::attr('width', $c['width']) . self::attr('height', $c['height'])
                    . ' loading="' . ($single ? 'eager' : 'lazy') . '" decoding="async">'
                : self::PLACEHOLDER;
            $price = $c['price'] !== null
                ? '<p class="avdeb-card__price">'
                    . ($c['compareAt'] !== null ? '<del><span class="avdeb-sr">Was </span>' . self::e($c['compareAt']) . '</del>' : '')
                    . '<span>' . ($c['compareAt'] !== null ? '<span class="avdeb-sr">now </span>' : '') . self::e($c['price']) . '</span></p>'
                : '';

            $items .= '<li class="avdeb__item"><article class="' . $cardClasses . '">'
                . '<div class="avdeb-card__media">' . $media . $badges . '</div>'
                . '<div class="avdeb-card__body"><div class="avdeb-card__main">'
                . ($c['creator'] !== null ? '<p class="avdeb-card__by">by ' . self::e($c['creator']) . '</p>' : '')
                . "<h{$hCard} class=\"avdeb-card__title\"><a class=\"avdeb-card__link\"" . self::attr('href', $c['href']) . self::attr('rel', $c['rel']) . self::attr('target', $c['target']) . '>'
                . self::e($c['title']) . "</a></h{$hCard}>"
                . ($single && $o['note'] !== '' ? '<p class="avdeb-card__note">' . self::e($o['note']) . '</p>' : '')
                . '</div><div class="avdeb-card__footer">' . $price
                . '<span class="avdeb-card__cta" aria-hidden="true"><span class="avdeb-card__cta-label">' . self::e($o['ctaLabel']) . '</span>' . self::ARROW . '</span>'
                . '</div></div></article></li>';
        }

        $track      = $isCarousel ? ' data-avdeb-track tabindex="0"' . self::attr('aria-label', $label . ' — scroll for more') : '';
        $disclosure = !empty($data['disclosure']) ? '<p class="avdeb__disclosure">' . self::INFO . self::e($data['disclosure']) . '</p>' : '';

        return '<section class="' . $classes . ' not-prose" data-theme="' . self::e($o['theme']) . '"' . ($isCarousel ? ' data-avdeb-carousel' : '')
            . self::attr('style', View::rootStyle($o, $columns)) . self::attr('aria-label', $label) . '>'
            . $header . '<ul class="avdeb__items" role="list"' . $track . '>' . $items . '</ul>' . $disclosure . '</section>';
    }

    /** A call-to-action link to an AVDEB page, carrying the site's referral code. */
    public static function button(array $b, string $ref, array $display): string
    {
        $url = Catalog::avdebLink($b['href'], $ref);

        if ($url === '') {
            return '<!-- AVDEB Button: not an avdeb.com link -->';
        }

        $style = $display['accentColor'] !== '' ? self::attr('style', '--avdeb-accent:' . $display['accentColor']) : '';

        return '<p class="avdeb-cta avdeb-cta--' . self::e($b['align']) . '"' . $style . '>'
            . '<a class="avdeb-btn avdeb-btn--' . self::e($b['variant']) . '"' . self::attr('href', $url) . self::attr('rel', View::relFor($url))
            . self::attr('target', $display['newTab'] ? '_blank' : null) . '>' . self::e($b['label']) . self::ARROW . '</a></p>';
    }
}
