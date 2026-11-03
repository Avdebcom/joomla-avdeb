<?php

/**
 * @package     AVDEB Products for Joomla
 * @copyright   (C) 2026 AVDEB <https://avdeb.com>
 * @license     GNU General Public License version 2 or later
 */

namespace Avdeb\Library;

/**
 * Finds `{avdeb-products …}`, `{avdeb-product …}` and `{avdeb-button …}` tags in article text — the
 * Joomla `{loadmodule …}` style — and replaces them. Pure.
 *
 *   {avdeb-products https://avdeb.com/creators/cleo/ layout="carousel" limit="8" heading="From my shop"}
 *   {avdeb-product https://avdeb.com/store/cat-sticker/ variant="hero" badge="Editor's pick"}
 *   {avdeb-button href="/creators/cleo/" label="Visit my shop" variant="outline" align="center"}
 *
 * The first bare value is the link (or search phrase). Editors mangle tags in predictable ways, so:
 * entities (&quot; &amp; &nbsp;) and curly quotes are normalised, and a tag that sits alone in a
 * paragraph replaces the whole `<p>` (block HTML inside a <p> is invalid). Write `{{avdeb-products}}`
 * to show a tag literally.
 */
final class Shortcodes
{
    public const TAGS = ['products', 'product', 'button'];

    private const TAG_RE  = '/(?<!\{)\{avdeb-(products|product|button)(\s[^{}]*)?\}(?!\})/i';
    private const PARA_RE = '#<p(?:\s[^>]*)?>(?:\s|&nbsp;|&\#160;|<br\s*/?>)*(\{avdeb-(?:products|product|button)(?:\s[^{}]*)?\})(?:\s|&nbsp;|&\#160;|<br\s*/?>)*</p>#i';

    public static function contains(string $text): bool
    {
        return stripos($text, '{avdeb-') !== false;
    }

    /**
     * @param callable $render fn(string $tag, array $attrs): string — $tag is products|product|button
     */
    public static function replace(string $text, callable $render): string
    {
        if (!self::contains($text)) {
            return $text;
        }

        // Unwrap tags that are alone in a paragraph, then render every tag.
        $text = (string) preg_replace(self::PARA_RE, '$1', $text);
        $text = (string) preg_replace_callback(
            self::TAG_RE,
            static fn (array $m) => $render(strtolower($m[1]), self::parseAttributes($m[2] ?? '')),
            $text
        );

        // {{avdeb-…}} → literal {avdeb-…}
        return (string) preg_replace('/\{(\{avdeb-[^{}]*\})\}/i', '$1', $text);
    }

    /** Remove the tags (search indexing, RSS, plain-text contexts). */
    public static function strip(string $text): string
    {
        if (!self::contains($text)) {
            return $text;
        }

        $text = (string) preg_replace(self::PARA_RE, '', $text);

        return (string) preg_replace(self::TAG_RE, '', $text);
    }

    /** Bare words that are boolean switches rather than part of a search phrase. */
    private const FLAGS = ['hideprice', 'hidecreator', 'newtab', 'viewall', 'showprice', 'showcreator', 'showbadges'];

    /**
     * `https://avdeb.com/x/ layout="grid" limit=6 hide-price` → [link => …, layout => grid, limit => 6, hide-price => true]
     * `cat stickers layout=list` → [link => "cat stickers", layout => list]
     *
     * @return array<string, string>
     */
    public static function parseAttributes(string $raw): array
    {
        $s = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{2033}", "\u{2018}", "\u{2019}", "\u{00A0}"], ['"', '"', '"', '"', "'", "'", ' '], $s);

        $attrs = [];
        $re    = '/([A-Za-z][\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))|"([^"]*)"|\'([^\']*)\'|(\S+)/u';

        if (!preg_match_all($re, $s, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL)) {
            return $attrs;
        }

        $words = [];

        foreach ($matches as $m) {
            if ($m[1] !== null) {
                $attrs[strtolower($m[1])] ??= trim($m[2] ?? $m[3] ?? $m[4] ?? '');
                continue;
            }

            $quoted = $m[5] ?? $m[6];
            $bare   = trim($quoted ?? $m[7] ?? '');

            if ($bare === '') {
                continue;
            }

            if ($quoted === null && \in_array(strtolower(str_replace(['-', '_'], '', $bare)), self::FLAGS, true)) {
                $attrs[strtolower($bare)] ??= 'true';
            } else {
                $words[] = $bare;
            }
        }

        // Bare words form the link / search phrase unless link= was given explicitly.
        if ($words && !isset($attrs['link'])) {
            $attrs['link'] = implode(' ', $words);
        }

        return $attrs;
    }
}
