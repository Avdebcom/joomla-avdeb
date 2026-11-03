<?php

use Avdeb\Library\Catalog;
use Avdeb\Library\Options;
use Avdeb\Library\Renderer;
use Avdeb\Library\View;

const NOW = 1791158400; // 2026-10-05T00:00:00Z

function p(array $o = []): array
{
    return Catalog::normalizeProduct(product($o));
}

function data(array $products, array $extra = []): array
{
    return array_merge(['products' => $products, 'ref' => 'ABC12345', 'query' => ['creator' => 'cleo', 'limit' => 6], 'disclosure' => 'Paid links'], $extra);
}

test('View::buildCards — prices, sale, New, sponsored rel', function () {
    $c = View::buildCards([p(['price' => 3, 'compare_at_price' => 4])], Options::parse([]), 'en-US', NOW)[0];
    same('$3.00', $c['price']);
    same('$4.00', $c['compareAt']);
    same(25, $c['discount']);
    same(true, $c['isNew']);
    same('Cleo', $c['creator']);
    same('sponsored noopener', $c['rel']);
    same(null, $c['target']);
});

test('View::buildCards — display toggles', function () {
    $o = Options::parse(['show-price' => 'false', 'show-creator' => '0', 'show-badges' => 'no', 'new-tab' => 'yes', 'image-ratio' => 'portrait']);
    $c = View::buildCards([p(['price' => 3, 'compare_at_price' => 4])], $o, 'en-US', NOW)[0];
    same([null, null, null, false, null, '_blank', 750], [$c['price'], $c['compareAt'], $c['discount'], $c['isNew'], $c['creator'], $c['target'], $c['height']]);
});

test('View helpers', function () {
    same(null, View::discountPercent(4, 3));
    same(50, View::discountPercent(1, 2));
    ok(!View::isNew('2026-09-01T00:00:00Z', NOW));
    same('noopener', View::relFor('https://avdeb.com/store/x/'));
    same('https://avdeb.com/creators/cleo/?ref=ABC12345', View::viewAllHref(['creator' => 'cleo'], [p()], 'X'));
    same('https://avdeb.com/category/stickers/?ref=X', View::viewAllHref(['tag' => 'stickers'], [], 'X'));
    same(null, View::viewAllHref(['q' => 'cat'], [], 'X'));
    same('--avdeb-cols:6;--avdeb-radius:8px;--avdeb-ratio:1 / 1;--avdeb-accent:#123456', View::rootStyle(Options::parse(['accent' => '#123456', 'radius' => 8]), 9));
    same(2, View::effectiveColumns(Options::parse(['columns' => 4]), 2));
    same(1, View::effectiveColumns(Options::parse(['layout' => 'list']), 5));
    ok(str_contains((string) View::formatPrice(5, 'EUR', 'de-DE'), '€'));
});

test('Renderer::embed — grid with header, View all and disclosure', function () {
    $html = Renderer::embed(data([p(), p(['handle' => 'dog', 'title' => 'Dog'])]), Options::parse(['heading' => 'Shop']), 'en-US', 2, NOW);
    ok(str_starts_with($html, '<section class="avdeb avdeb--grid avdeb--elevated not-prose" data-theme="site"'), $html);
    same(2, substr_count($html, 'class="avdeb-card__link"'));
    contains('<h2 class="avdeb__heading">Shop</h2>', $html);
    contains('<h3 class="avdeb-card__title">', $html);
    contains('href="https://avdeb.com/creators/cleo/?ref=ABC12345" rel="sponsored noopener"', $html);
    contains('avdeb__disclosure', $html);
    contains('--avdeb-cols:2', $html);
});

test('Renderer::embed — escapes product and author text', function () {
    $html = Renderer::embed(data([p(['title' => '<img src=x onerror=alert(1)>'])]), Options::parse(['heading' => '"><script>', 'cta_label' => '<b>Buy</b>']), 'en-US', 2, NOW);
    notContains('<script>', $html);
    notContains('<img src=x', $html);
    contains('&lt;img src=x onerror=alert(1)&gt;', $html);
    contains('&lt;b&gt;Buy&lt;/b&gt;', $html);
});

test('Renderer::embed — carousel, hero, empty', function () {
    $carousel = Renderer::embed(data([p()]), Options::parse(['layout' => 'carousel']), 'en-US', 2, NOW);
    contains('data-avdeb-carousel', $carousel);
    contains('data-avdeb-track tabindex="0"', $carousel);
    $hero = Renderer::embed(data([p()], ['query' => ['handle' => 'cat-sticker', 'limit' => 1]]), Options::parse(['mode' => 'product', 'variant' => 'hero', 'badge' => 'Pick', 'note' => 'Love it']), 'en-US', 3, NOW);
    contains('avdeb--single avdeb--hero', $hero);
    contains('avdeb-card avdeb-card--featured avdeb-card--hero', $hero);
    contains('avdeb-badge--pick">Pick<', $hero);
    contains('<h4 class="avdeb-card__title">', $hero);
    notContains('avdeb__all', $hero);
    ok(str_starts_with(Renderer::embed(data([]), Options::parse([])), '<!--'));
});

test('Renderer::button — ref, rel and off-site guard', function () {
    $html = Renderer::button(Options::button(['href' => '/creators/cleo/', 'label' => 'Visit <shop>', 'variant' => 'outline', 'align' => 'center']), 'ME', Options::display([]));
    contains('class="avdeb-cta avdeb-cta--center"', $html);
    contains('href="https://avdeb.com/creators/cleo/?ref=ME" rel="sponsored noopener"', $html);
    contains('Visit &lt;shop&gt;', $html);
    ok(str_starts_with(Renderer::button(Options::button(['href' => 'https://evil.com/']), 'ME', Options::display([])), '<!--'));
});
