<?php

use Avdeb\Library\Catalog;

test('cleanRef / cleanSlug / cleanType / cleanSearch', function () {
    same('ABc12_-', Catalog::cleanRef('AB<c>12_- '));
    same('my-shop', Catalog::cleanSlug('  My Shop! '));
    same('caf-stickers', Catalog::cleanSlug('Café Stickers'), 'same as the TypeScript cleanSlug');
    same('Phone Case', Catalog::cleanType('Phone   Case!!'));
    same('cat stickers', Catalog::cleanSearch("cat\x00 stickers  "));
    same(100, strlen(Catalog::cleanSlug(str_repeat('a', 300))));
});

test('keyProblem / usableKey', function () {
    same(null, Catalog::keyProblem(''));
    same(null, Catalog::keyProblem(SECRET_KEY));
    same('publishable', Catalog::keyProblem('avdeb_pk_abc'));
    same('malformed', Catalog::keyProblem('hello'));
    same(SECRET_KEY, Catalog::usableKey(' ' . SECRET_KEY . ' '));
    same('', Catalog::usableKey('avdeb_pk_x'));
});

test('isAvdebUrl rejects look-alikes', function () {
    ok(Catalog::isAvdebUrl('https://avdeb.com/store/x/'));
    ok(Catalog::isAvdebUrl('https://www.avdeb.com/'));
    ok(!Catalog::isAvdebUrl('http://avdeb.com/'));
    ok(!Catalog::isAvdebUrl('https://avdeb.com.evil.com/'));
    ok(!Catalog::isAvdebUrl('https://avdeb.com@evil.com/'));
    ok(!Catalog::isAvdebUrl('https://evil.com/?u=https://avdeb.com/'));
    ok(!Catalog::isAvdebUrl('https://avdeb.com/"onmouseover=x'));
    ok(!Catalog::isAvdebUrl('javascript:alert(1)'));
});

test('avdebLink adds the ref and never links off-site', function () {
    same('https://avdeb.com/creators/cleo/?ref=ME', Catalog::avdebLink('/creators/cleo/', 'ME'));
    same('https://avdeb.com/store/x/?a=1&ref=ME', Catalog::avdebLink('avdeb.com/store/x/?a=1&ref=OLD', 'ME'));
    same('https://avdeb.com/store/x/', Catalog::avdebLink('https://avdeb.com/store/x/'));
    same('', Catalog::avdebLink('https://evil.com/', 'ME'));
    same('', Catalog::avdebLink('//evil.com/x'));
    ok(Catalog::hasRef('https://avdeb.com/?ref=a'));
    ok(!Catalog::hasRef('https://avdeb.com/'));
});

test('parseAvdebLink', function () {
    same(['kind' => 'creator', 'value' => 'cleo'], Catalog::parseAvdebLink('https://avdeb.com/creators/Cleo/'));
    same(['kind' => 'tag', 'value' => 'stickers'], Catalog::parseAvdebLink('avdeb.com/category/stickers/?ref=x'));
    same(['kind' => 'handle', 'value' => 'cat-sticker'], Catalog::parseAvdebLink('/store/cat-sticker/'));
    same(null, Catalog::parseAvdebLink('https://example.com/creators/x/'));
    same(null, Catalog::parseAvdebLink('cat stickers'));
    same(null, Catalog::parseAvdebLink('https://avdeb.com/about/'));
});

test('sanitizeQuery clamps and drops junk', function () {
    same(['creator' => 'cleo', 'limit' => 12], Catalog::sanitizeQuery(['creator' => 'Cleo', 'limit' => 99, 'evil' => 1], 12));
    same(['handle' => 'x', 'limit' => 1], Catalog::sanitizeQuery(['handle' => 'x', 'limit' => 20], 48));
    same(['limit' => 6], Catalog::sanitizeQuery('nope', 48));
    same(['q' => 'cats', 'limit' => 6], Catalog::sanitizeQuery(['q' => 'cats', 'limit' => '-3'], 48));
});

test('productsUrl', function () {
    same('https://api.avdeb.com/v1/catalog/products?tag=stickers&q=cat%20stickers&limit=6&ref=ME', Catalog::productsUrl(['tag' => 'stickers', 'q' => 'cat stickers', 'limit' => 6], 'ME'));
});

test('normalizeProduct validates every field', function () {
    $p = Catalog::normalizeProduct(product(['image' => 'http://insecure/x.png', 'currency' => 'eur', 'price' => -1, 'compare_at_price' => 9]));
    same('cat-sticker', $p['handle']);
    same(null, $p['image']);
    same('EUR', $p['currency']);
    same(null, $p['price']);
    same(9.0, $p['compareAtPrice']);
    same('Cleo', $p['creator']['name']);
    same(null, Catalog::normalizeProduct(product(['url' => 'https://evil.com/'])));
    same(null, Catalog::normalizeProduct(product(['title' => '  '])));
    same(null, Catalog::normalizeProduct('x'));
});

test('parseProductsResponse de-duplicates and reads the tier', function () {
    $r = Catalog::parseProductsResponse(productsBody([product(), product(), product(['handle' => 'dog', 'title' => 'Dog'])], ['tier' => 'weird', 'ref' => 'AB C']));
    same(2, count($r['products']));
    same('anonymous', $r['tier']);
    same('ABC', $r['ref']);
    same(null, Catalog::parseProductsResponse(['products' => 'x']));
});

test('parseKeyResponse / apiErrorMessage', function () {
    same(['type' => 'creator', 'name' => 'Cleo', 'ref' => 'CLEO1', 'creatorSlug' => 'cleo'], Catalog::parseKeyResponse(['owner' => ['type' => 'Creator', 'name' => 'Cleo', 'ref' => 'CLEO1', 'creator_slug' => 'Cleo']]));
    same('bad key', Catalog::apiErrorMessage(401, ['error' => 'bad key']));
    same('HTTP 502', Catalog::apiErrorMessage(502, null));
});
