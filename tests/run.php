#!/usr/bin/env php
<?php

declare(strict_types=1);

$files = [
    __DIR__.'/domain_rules.php',
    __DIR__.'/job_form.php',
    __DIR__.'/company_form.php',
    __DIR__.'/partial_update.php',
    __DIR__.'/api_contracts.php',
    __DIR__.'/layouts.php',
    __DIR__.'/settings.php',
    __DIR__.'/type_ext_coerce.php',
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
