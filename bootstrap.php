<?php

declare(strict_types=1);

use App\Support\Config;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$configData = require __DIR__ . '/config.php';

Config::load($configData);
date_default_timezone_set($configData['app']['timezone'] ?? 'UTC');
