<?php

declare(strict_types=1);

/**
 * Builds bin/build/ai-router.phar containing the app and production-only dependencies.
 * Run with: php -d phar.readonly=0 build/build-phar.php
 */

$root = dirname(__DIR__);
$output = $root . '/bin/build/ai-router.phar';
$staging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-router-phar-' . bin2hex(random_bytes(4));

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
};

$copy = static function (string $from, string $to) use (&$copy): void {
    if (!is_dir($to)) {
        mkdir($to, 0o777, true);
    }
    foreach (new DirectoryIterator($from) as $item) {
        if ($item->isDot()) {
            continue;
        }
        $target = $to . DIRECTORY_SEPARATOR . $item->getFilename();
        $item->isDir() ? $copy($item->getPathname(), $target) : copy($item->getPathname(), $target);
    }
};

if (ini_get('phar.readonly')) {
    fwrite(STDERR, "phar.readonly is enabled. Run: php -d phar.readonly=0 build/build-phar.php\n");
    exit(1);
}

try {
    mkdir($staging, 0o777, true);
    $copy($root . '/src', $staging . '/src');
    mkdir($staging . '/bin');
    copy($root . '/bin/ai-router', $staging . '/bin/ai-router');
    foreach (['composer.json', 'composer.lock'] as $file) {
        if (is_file($root . '/' . $file)) {
            copy($root . '/' . $file, $staging . '/' . $file);
        }
    }

    passthru('composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --working-dir=' . escapeshellarg($staging), $status);
    if ($status !== 0) {
        throw new RuntimeException('composer install --no-dev failed.');
    }

    if (!is_dir(dirname($output))) {
        mkdir(dirname($output), 0o777, true);
    }
    @unlink($output);

    $phar = new Phar($output);
    $phar->startBuffering();
    $phar->buildFromIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS)),
        $staging,
    );
    $phar->setStub(Phar::createDefaultStub('bin/ai-router'));
    $phar->stopBuffering();

    fwrite(STDERR, "Built {$output}\n");
} finally {
    $remove($staging);
}
