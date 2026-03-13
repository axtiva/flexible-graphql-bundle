<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = str_replace('\\', '/', substr($class, strlen($prefix)));
    $projectDir = __DIR__;
    $candidates = [
        $projectDir . '/src/' . $relativeClass . '.php',
        $projectDir . '/var/generated/' . $relativeClass . '.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            require_once $candidate;

            return;
        }
    }
});
