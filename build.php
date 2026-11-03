<?php

/**
 * Builds dist/pkg_avdeb-<version>.zip — one installable Joomla package containing the library,
 * the content plugin, the editor button and the module (each as its own zip, as Joomla expects).
 *
 *   php build.php
 */

declare(strict_types=1);

$root    = __DIR__;
$version = (string) simplexml_load_file($root . '/pkg_avdeb.xml')->version;
$dist    = $root . '/dist';
$staging = $dist . '/staging';

$children = ['lib_avdeb', 'plg_content_avdeb', 'plg_editors-xtd_avdeb', 'mod_avdeb_products'];

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

function zipDir(string $src, string $zipFile, array $extra = []): void
{
    if (!class_exists(ZipArchive::class)) {
        // No ext-zip: use the zip CLI (extra files are copied in first, then removed).
        foreach ($extra as $name => $path) {
            copy($path, "$src/$name");
        }
        exec('cd ' . escapeshellarg($src) . ' && zip -qrX ' . escapeshellarg($zipFile) . ' .', $o, $code);
        foreach ($extra as $name => $path) {
            unlink("$src/$name");
        }
        if ($code !== 0) {
            throw new RuntimeException("zip failed for $src");
        }

        return;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create $zipFile");
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    $list  = [];
    foreach ($files as $file) {
        $list[] = $file->getPathname();
    }
    sort($list);
    foreach ($list as $path) {
        $zip->addFile($path, str_replace('\\', '/', substr($path, strlen($src) + 1)));
    }
    foreach ($extra as $name => $path) {
        $zip->addFile($path, $name);
    }
    $zip->close();
}

// Every manifest must carry the package version.
foreach (array_merge(['pkg_avdeb.xml'], array_map(fn ($c) => "$c/" . match ($c) {
    'lib_avdeb' => 'lib_avdeb.xml',
    'mod_avdeb_products' => 'mod_avdeb_products.xml',
    default => 'avdeb.xml',
}, $children)) as $manifest) {
    $v = (string) simplexml_load_file("$root/$manifest")->version;
    if ($v !== $version) {
        fwrite(STDERR, "Version mismatch: $manifest has $v, package is $version\n");
        exit(1);
    }
}

rrmdir($dist);
mkdir($staging . '/packages', 0777, true);

foreach ($children as $child) {
    zipDir("$root/$child", "$staging/packages/$child.zip", ['LICENSE.txt' => "$root/LICENSE.txt"]);
}

copy("$root/pkg_avdeb.xml", "$staging/pkg_avdeb.xml");
copy("$root/script.php", "$staging/script.php");
copy("$root/LICENSE.txt", "$staging/LICENSE.txt");
mkdir("$staging/language/en-GB", 0777, true);
copy("$root/language/en-GB/pkg_avdeb.sys.ini", "$staging/language/en-GB/pkg_avdeb.sys.ini");

$out = "$dist/pkg_avdeb-$version.zip";
zipDir($staging, $out);
rrmdir($staging);

echo "Built $out (" . number_format(filesize($out) / 1024, 1) . " KB)\n";
