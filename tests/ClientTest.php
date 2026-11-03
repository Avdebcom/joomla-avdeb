<?php

use Avdeb\Library\Client;
use Avdeb\Library\Store\ArrayStore;

/** HTTP double: answers from a queue and records every call. */
function scripted(array $replies): array
{
    $calls = new ArrayObject();
    $http  = function (string $url, array $headers, int $timeout) use (&$replies, $calls): array {
        $calls[] = ['url' => $url, 'headers' => $headers];
        $reply   = array_shift($replies);

        if ($reply === null) {
            throw new RuntimeException("unexpected request $url");
        }

        if ($reply instanceof Throwable) {
            throw $reply;
        }

        return ['status' => $reply['status'] ?? 200, 'body' => json_encode($reply['body'])];
    };

    return [$http, $calls];
}

function client(array $replies, string $key = '', string $ref = '', int $budget = 30): array
{
    [$http, $calls] = scripted($replies);
    $clock          = new ArrayObject(['t' => 1790000000]);
    $client         = new Client(new ArrayStore(), $http, $key, $ref, 60, $budget, fn () => $clock['t']);

    return [$client, $calls, $clock];
}

test('Client::hash matches the TypeScript FNV-1a', function () {
    same('811c9dc5', Client::hash(''));
    same('e40c292c', Client::hash('a'));
});

test('Client::products — network, then cache, then refetch after expiry', function () {
    [$c, $calls, $clock] = client([['body' => productsBody([product()])], ['body' => productsBody([product()])]], '', 'ME');
    same('network', $c->products(['creator' => 'cleo', 'limit' => 6])['source']);
    same('cache', $c->products(['creator' => 'cleo', 'limit' => 6])['source']);
    same(1, count($calls));
    contains('ref=ME', $calls[0]['url']);
    ok(!isset($calls[0]['headers']['Authorization']));
    same('joomla-avdeb/' . Client::VERSION, $calls[0]['headers']['User-Agent']);
    $clock['t'] += 61 * 60;
    same('network', $c->products(['creator' => 'cleo', 'limit' => 6])['source']);
});

test('Client::products — secret key goes in the header, never the URL', function () {
    [$c, $calls] = client([['body' => productsBody([product()], ['ref' => 'OWNER'])]], SECRET_KEY, 'IGNORED');
    $out         = $c->products(['limit' => 6]);
    same('OWNER', $out['ref']);
    same('Bearer ' . SECRET_KEY, $calls[0]['headers']['Authorization']);
    notContains('ref=', $calls[0]['url']);
    notContains(SECRET_KEY, $calls[0]['url']);
});

test('Client::products — serves the last good copy when AVDEB is down, with back-off', function () {
    [$c, $calls, $clock] = client([['body' => productsBody([product()])], new RuntimeException('timeout')]);
    $c->products(['limit' => 6]);
    $clock['t'] += 2 * 3600;
    $out = $c->products(['limit' => 6]);
    same(['stale', 'timeout', 1], [$out['source'], $out['error'], count($out['products'])]);
    same('cache', $c->products(['limit' => 6])['source'], 'back-off: no retry for 15 minutes');
    same(2, count($calls));
});

test('Client::products — empty result is remembered briefly on failure', function () {
    [$c, $calls] = client([['status' => 500, 'body' => ['error' => 'boom']]]);
    $out         = $c->products(['limit' => 6]);
    same(['empty', 'boom'], [$out['source'], $out['error']]);
    same('cache', $c->products(['limit' => 6])['source']);
    same(1, count($calls));
});

test('Client::products — request budget per minute', function () {
    [$c, $calls] = client([['body' => productsBody([])], ['body' => productsBody([])]], '', '', 2);
    $c->products(['q' => 'a', 'limit' => 6]);
    $c->products(['q' => 'b', 'limit' => 6]);
    $out = $c->products(['q' => 'c', 'limit' => 6]);
    same(['empty', 'rate limited'], [$out['source'], $out['error']]);
    same(2, count($calls));
});

test('Client::keyInfo / ref / flush / stats', function () {
    [$c, $calls] = client([
        ['body' => ['owner' => ['type' => 'creator', 'name' => 'Cleo', 'ref' => 'CLEO1', 'creator_slug' => 'cleo']]],
        ['body' => productsBody([product()])],
        ['body' => productsBody([product()])],
    ], SECRET_KEY);
    same('CLEO1', $c->ref());
    same('CLEO1', $c->ref(), 'key check cached');
    same(1, count($calls));
    contains('/v1/catalog/key', $calls[0]['url']);
    $c->products(['limit' => 6]);
    $c->flush();
    same('network', $c->products(['limit' => 6])['source']);
    same(3, $c->stats()['requests'], 'stats survive a flush');
    same(null, client([], '')[0]->keyInfo());
});
