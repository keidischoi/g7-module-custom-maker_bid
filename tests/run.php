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
    __DIR__.'/admin_actor.php',
    __DIR__.'/job_lifecycle.php',
    __DIR__.'/dispute_rules.php',
    __DIR__.'/payment_rules.php',
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

echo "== ext_normalize_js.mjs ==\n";
passthru('node '.escapeshellarg(__DIR__.'/ext_normalize_js.mjs'), $jsCode);
echo "\n";
if ($jsCode !== 0) {
    $failed++;
}

echo "== search_fix_admin.mjs ==\n";
passthru('node '.escapeshellarg(__DIR__.'/search_fix_admin.mjs'), $searchJsCode);
echo "\n";
if ($searchJsCode !== 0) {
    $failed++;
}

exit($failed === 0 ? 0 : 1);
