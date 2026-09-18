<?php

declare(strict_types=1);

// Isolated process: no Composer autoloader/global metadata is modified in PHPUnit.
$mode = $argv[1];
if ($mode !== 'no-composer') {
    require dirname(__DIR__).'/vendor/composer/InstalledVersions.php';
    $versions = $mode === 'missing-package' ? [] : [
        'devneok/neokpay-php' => [
            'pretty_version' => $mode === 'null-version' ? null : $argv[2],
            'dev_requirement' => false,
        ],
    ];
    Composer\InstalledVersions::reload(['root' => [], 'versions' => $versions]);
}
require dirname(__DIR__).'/src/ClientConfig.php';
echo (new Neok\Pay\ClientConfig('fake-api-key', userAgentSuffix: $argv[3] ?? null))->userAgent();
