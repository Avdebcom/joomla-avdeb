<?php

use Avdeb\Library\Settings;
use Avdeb\Library\Shortcodes;

function render_tags(string $text): string
{
    return Shortcodes::replace($text, fn (string $tag, array $attrs) => '[' . $tag . ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ']');
}

test('Shortcodes::parseAttributes — named, quoted, bare and flags', function () {
    same(['layout' => 'carousel', 'limit' => '8', 'link' => 'https://avdeb.com/creators/cleo/'], Shortcodes::parseAttributes(' https://avdeb.com/creators/cleo/ layout="carousel" limit=8'));
    same(['layout' => 'list', 'link' => 'cat stickers'], Shortcodes::parseAttributes(' cat stickers layout=list'));
    same(['hide-price' => 'true', 'link' => 'cat stickers'], Shortcodes::parseAttributes(' "cat stickers" hide-price'));
    same(['heading' => "Editor's pick", 'badge' => 'A "quote"'], Shortcodes::parseAttributes(" heading=\"Editor's pick\" badge='A \"quote\"'"));
    same(['link' => 'cats'], Shortcodes::parseAttributes(' cats'));
});

test('Shortcodes::parseAttributes — survives what editors do to quotes', function () {
    same(['link' => 'x', 'layout' => 'grid'], Shortcodes::parseAttributes(' link=&quot;x&quot; layout=&quot;grid&quot;'));
    same(['heading' => 'Shop', 'limit' => '4'], Shortcodes::parseAttributes(" heading=\u{201C}Shop\u{201D}&nbsp;limit=\u{201C}4\u{201D}"));
    same(['heading' => 'A & B'], Shortcodes::parseAttributes(' heading="A &amp; B"'));
});

test('Shortcodes::replace — tags, paragraphs and escapes', function () {
    same('<h1>Hi</h1>[products {"link":"cats"}]<p>after</p>', render_tags('<h1>Hi</h1><p>{avdeb-products cats}</p><p>after</p>'));
    same('<p>Buy [button {"href":"/x/"}] now</p>', render_tags('<p>Buy {avdeb-button href="/x/"} now</p>'));
    same('[product {"link":"https://avdeb.com/store/mug/"}]', render_tags('<p>&nbsp;{avdeb-product https://avdeb.com/store/mug/}&nbsp;</p>'));
    same('Use {avdeb-products cats} to embed', render_tags('Use {{avdeb-products cats}} to embed'));
    same('{avdeb-other x} {notavdeb}', render_tags('{avdeb-other x} {notavdeb}'));
    same('[products []]', render_tags('{AVDEB-Products}'));
});

test('Shortcodes::strip', function () {
    same('<h1>T</h1> text', Shortcodes::strip('<h1>T</h1><p>{avdeb-products cats}</p> text{avdeb-button href="/"}'));
});

test('Settings::fromParams', function () {
    $s = Settings::fromParams(['api_key' => ' ' . SECRET_KEY, 'ref_code' => 'A B', 'cache_minutes' => '1', 'show_disclosure' => '0', 'layout' => 'list']);
    same(SECRET_KEY, $s['apiKey']);
    same('AB', $s['refCode']);
    same(5, $s['cacheMinutes']);
    same(null, Settings::disclosureFor($s, 'AB'));
    same('list', $s['display']['layout']);
    $pk = Settings::fromParams(['api_key' => 'avdeb_pk_123']);
    same(['', 'publishable'], [$pk['apiKey'], $pk['keyProblem']]);
    same(Settings::DEFAULT_DISCLOSURE, Settings::disclosureFor(Settings::fromParams([]), 'X'));
    same(null, Settings::disclosureFor(Settings::fromParams([]), ''));
    same('avdeb_sk_…c3D4', Settings::maskKey(SECRET_KEY));
});
