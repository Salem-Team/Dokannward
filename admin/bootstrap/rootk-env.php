<?php

/**
 * Load ROOTK tenant env before Laravel boots.
 * Checks admin/.rootk then repo-root/.rootk (hybrid Next.js + Laravel layout).
 */
$candidates = [
    dirname(__DIR__).'/.rootk/tenant.env',
    dirname(__DIR__, 2).'/.rootk/tenant.env',
];

$tenantEnvPath = null;
foreach ($candidates as $path) {
    if (is_readable($path)) {
        $tenantEnvPath = $path;
        break;
    }
}

if ($tenantEnvPath === null) {
    return;
}

$lines = file($tenantEnvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    return;
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    if ($key === '') {
        continue;
    }

    $value = trim($value);
    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"'))
        || (str_starts_with($value, "'") && str_ends_with($value, "'"))
    ) {
        $value = substr($value, 1, -1);
    }

    if (getenv($key) !== false) {
        continue;
    }

    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
