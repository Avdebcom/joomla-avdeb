<?php

/**
 * Minimal test runner (no Composer, no PHPUnit): `php tests/run.php`.
 * Loads the pure library classes and every tests/*Test.php; each test file registers closures
 * with test('name', fn () => …). Assertion helpers throw on failure.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Avdeb\\Library\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../lib_avdeb/src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

/** @var array<string, callable> $GLOBALS['avdeb_tests'] */
$GLOBALS['avdeb_tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['avdeb_tests'][$name] = $fn;
}

final class AssertionFailed extends RuntimeException
{
}

function fail(string $message): never
{
    throw new AssertionFailed($message);
}

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fail(($message ? "$message\n" : '') . 'expected ' . var_export($expected, true) . "\n     got " . var_export($actual, true));
    }
}

function ok(mixed $value, string $message = 'expected truthy'): void
{
    if (!$value) {
        fail($message);
    }
}

function contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        fail(($message ? "$message\n" : '') . "missing: $needle\n     in: " . mb_substr($haystack, 0, 400));
    }
}

function notContains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        fail(($message ? "$message\n" : '') . "unexpected: $needle");
    }
}

/** API product fixture (same as the TypeScript tests' helpers.ts). */
function product(array $overrides = []): array
{
    return array_merge([
        'id'               => 'p1',
        'handle'           => 'cat-sticker',
        'title'            => 'Cat Sticker',
        'url'              => 'https://avdeb.com/store/cat-sticker/?ref=ABC12345',
        'image'            => 'https://cdn.avdeb.com/cat.webp',
        'price'            => 4.9,
        'compare_at_price' => null,
        'currency'         => 'USD',
        'type'             => 'Sticker',
        'tags'             => ['stickers', 'cats'],
        'creator'          => ['slug' => 'cleo', 'name' => 'Cleo', 'url' => 'https://avdeb.com/creators/cleo/?ref=ABC12345'],
        'created_at'       => '2026-09-30T10:00:00Z',
    ], $overrides);
}

function productsBody(array $products, array $extra = []): array
{
    return array_merge(['products' => $products, 'next_cursor' => null, 'ref' => 'ABC12345', 'tier' => 'secret'], $extra);
}

const SECRET_KEY = 'avdeb_sk_a1B2c3D4a1B2c3D4a1B2c3D4a1B2c3D4';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;

foreach ($GLOBALS['avdeb_tests'] as $name => $fn) {
    try {
        $fn();
        echo "  ✔ $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✖ $name\n    " . str_replace("\n", "\n    ", $e->getMessage()) . "\n";

        if (!$e instanceof AssertionFailed) {
            echo '    at ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }
}

$total = \count($GLOBALS['avdeb_tests']);
echo "\n" . ($total - $failed) . "/$total passed\n";
exit($failed ? 1 : 0);
