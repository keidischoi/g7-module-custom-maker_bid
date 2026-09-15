#!/usr/bin/env php
<?php

declare(strict_types=1);

$files = [
    __DIR__.'/domain_rules.php',
    __DIR__.'/api_contracts.php',
];

$failed = 0;
foreach ($files as $file) {
    echo '== '.basename($file)." ==\n";
    passthru('php '.escapeshellarg($file), $code);
    echo "\n";
    if ($code !== 0) {
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
