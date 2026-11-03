<?php

use Avdeb\Library\Options;

test('Options::parse — attribute spellings and defaults', function () {
    $o = Options::parse(['link' => 'avdeb.com/creators/cleo/', 'card-style' => 'outline', 'Show_Price' => '0', 'columns' => '9']);
    same('avdeb.com/creators/cleo/', $o['value']);
    same('outline', $o['cardStyle']);
    same(false, $o['showPrice']);
    same(6, $o['columns']);
    same('grid', $o['layout']);
    same('site', $o['theme']);
});

test('Options::parse — empty / "default" fall back to the site settings', function () {
    $site = Options::display(['layout' => 'carousel', 'card_style' => 'minimal', 'accent_color' => '#ABCDEF', 'show_price' => '0']);
    $o    = Options::parse(['embed_layout' => '', 'card_style' => 'default', 'show_price' => ''], $site);
    same('carousel', $o['layout']);
    same('minimal', $o['cardStyle']);
    same('#abcdef', $o['accentColor']);
    same(false, $o['showPrice']);
    same('list', Options::parse(['embed_layout' => 'list'], $site)['layout']);
});

test('Options::parse — rejects unsafe or unknown values', function () {
    $o = Options::parse(['layout' => 'masonry', 'theme' => 'neon', 'accent' => 'red;background:url(x)', 'radius' => '-4', 'limit' => '500']);
    same('grid', $o['layout']);
    same('site', $o['theme']);
    same('', $o['accentColor']);
    same(0, $o['radius']);
    same(48, $o['limit']);
    same('site', Options::parse(['theme' => 'inherit'])['theme']);
});

test('Options::parse — EmDash hidePrice/hideCreator and filter shorthands', function () {
    $o = Options::parse(['hide-price' => 'true', 'hide_creator' => '1', 'creator' => 'Cleo']);
    same(false, $o['showPrice']);
    same(false, $o['showCreator']);
    same(['limit' => 6, 'creator' => 'cleo'], Options::queryFor($o));
    same(['handle' => 'mug', 'limit' => 1], Options::queryFor(Options::parse(['handle' => 'mug'])));
});

test('Options::queryFor — auto-detects pasted links', function () {
    $q = fn (array $a) => Options::queryFor(Options::parse($a));
    same(['limit' => 6, 'creator' => 'cleo'], $q(['link' => 'https://avdeb.com/creators/Cleo/']));
    same(['limit' => 4, 'tag' => 'stickers'], $q(['link' => 'avdeb.com/category/stickers/', 'limit' => 4]));
    same(['limit' => 6, 'handle' => 'cat-sticker'], $q(['link' => '/store/cat-sticker/']));
    same(['limit' => 6, 'q' => 'cat stickers'], $q(['link' => 'cat stickers']));
    same(['limit' => 6], $q([]));
    same(['limit' => 6, 'type' => 'Phone Case'], $q(['source' => 'type', 'link' => 'Phone Case!']));
    same(['limit' => 6, 'tag' => 'laptop-stickers'], $q(['source' => 'tag', 'link' => 'Laptop Stickers']));
    same(['limit' => 6], $q(['source' => 'newest', 'link' => 'ignored']));
});

test('Options::queryFor — product mode', function () {
    same(['handle' => 'cat-sticker', 'limit' => 1], Options::queryFor(Options::parse(['mode' => 'product', 'link' => 'https://avdeb.com/store/cat-sticker/?ref=x'])));
    same(['limit' => 1], Options::queryFor(Options::parse(['mode' => 'product', 'link' => 'https://avdeb.com/creators/cleo/'])));
    same('mug', Options::productHandle('Mug'));
    same('', Options::productHandle('https://example.com/a'));
});

test('Options::button', function () {
    same(['href' => '/creators/cleo/', 'label' => 'Visit', 'variant' => 'outline', 'align' => 'center'], Options::button(['href' => '/creators/cleo/', 'label' => 'Visit', 'variant' => 'outline', 'align' => 'center']));
    same(['href' => '/', 'label' => 'Shop on AVDEB', 'variant' => 'solid', 'align' => 'start'], Options::button(['variant' => 'huge']));
});
