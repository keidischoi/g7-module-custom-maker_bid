<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);
$jobController = (string) file_get_contents($root.'/src/Http/Controllers/JobController.php');
$api = (string) file_get_contents($root.'/src/routes/api.php');
$moduleJson = (string) file_get_contents($root.'/module.json');
$modulePhp = (string) file_get_contents($root.'/module.php');
$migration = (string) file_get_contents($root.'/database/migrations/2026_09_16_000004_phase1_maker_bid_constraints.php');

expectFalse(
    'job index no longer auto-seeds demo rows',
    str_contains($jobController, 'MakerJob::query()->insert') || str_contains($jobController, '피규어 3D 출력 의뢰'),
);
expectTrue('public job list remains unauthenticated', str_contains($api, "Route::get('jobs', [JobController::class, 'index'])"));
expectTrue('mutating user APIs use auth:sanctum', str_contains($api, "middleware(['auth:sanctum', 'throttle:60,1'])"));
expectTrue('bid create route exists', str_contains($api, "Route::post('jobs/{id}/bids', [BidController::class, 'store'])"));
expectTrue('own-bid update route exists', str_contains($api, "Route::patch('jobs/{id}/bids/{bidId}', [BidController::class, 'update'])"));
expectTrue('owner award route exists', str_contains($api, "Route::post('jobs/{id}/award', [JobController::class, 'award'])"));
expectTrue('company apply route exists', str_contains($api, "Route::post('companies', [CompanyController::class, 'store'])"));
expectTrue('admin group uses auth:sanctum', str_contains($api, "->middleware(['auth:sanctum', 'throttle:600,1'])"));
expectTrue('admin jobs read permission', str_contains($api, 'permission:admin,custom-maker_bid.jobs.read'));
expectTrue('admin jobs update permission', str_contains($api, 'permission:admin,custom-maker_bid.jobs.update'));
expectTrue('admin jobs delete permission', str_contains($api, 'permission:admin,custom-maker_bid.jobs.delete'));
expectTrue('admin bids read permission', str_contains($api, 'permission:admin,custom-maker_bid.bids.read'));
expectTrue('admin bids update permission', str_contains($api, 'permission:admin,custom-maker_bid.bids.update'));
expectTrue('admin bids delete permission', str_contains($api, 'permission:admin,custom-maker_bid.bids.delete'));
expectTrue('admin companies read permission', str_contains($api, 'permission:admin,custom-maker_bid.companies.read'));
expectTrue('admin companies create permission', str_contains($api, 'permission:admin,custom-maker_bid.companies.create'));
expectTrue('admin companies update permission', str_contains($api, 'permission:admin,custom-maker_bid.companies.update'));
expectTrue('admin companies delete permission', str_contains($api, 'permission:admin,custom-maker_bid.companies.delete'));
expectTrue('admin company reject route', str_contains($api, "Route::post('companies/{id}/reject'"));
expectTrue('admin company approve route', str_contains($api, "Route::post('companies/{id}/approve'"));
expectTrue('admin company delete route', str_contains($api, "Route::delete('companies/{id}'"));
expectTrue('admin bids index route', str_contains($api, "Route::get('bids', [BidAdminController::class, 'index'])"));
expectTrue('module version is 0.5.0', str_contains($moduleJson, '"version": "0.5.0"'));
expectTrue('job types public route', str_contains($api, "Route::get('job-types', [JobTypeController::class, 'index'])"));
expectTrue('job form-defaults route', str_contains($api, "Route::get('jobs/form-defaults'"));
expectTrue('owner job update route', str_contains($api, "Route::patch('jobs/{id}', [JobController::class, 'update'])"));
expectTrue('upload staging route', str_contains($api, "Route::post('uploads'"));
expectTrue('admin job-types route', str_contains($api, "Route::get('job-types', [JobTypeAdminController::class, 'index'])"));
expectTrue('admin job-types move route', str_contains($api, "Route::post('job-types/{id}/move'"));
expectTrue('job viewer route exists', str_contains($api, "Route::get('jobs/{id}/viewer', [JobController::class, 'viewer'])"));
expectTrue('bids mine route exists', str_contains($api, "Route::get('bids/mine', [BidController::class, 'mine'])"));
expectTrue('admin job cancel route exists', str_contains($api, "Route::post('jobs/{id}/cancel', [JobAdminController::class, 'cancel'])"));
expectTrue('admin menus include 의뢰 목록', str_contains($modulePhp, '의뢰 목록'));
expectTrue('admin menus include 입찰 관리', str_contains($modulePhp, '입찰 관리'));
expectTrue('admin menus include 회사 목록', str_contains($modulePhp, '회사 목록'));
expectTrue('admin menus include 회원 활동', str_contains($modulePhp, '회원 활동'));
expectTrue('jobs permissions declared', str_contains($modulePhp, "permissionCategory('jobs'"));
expectTrue('bids permissions declared', str_contains($modulePhp, "permissionCategory('bids'"));
expectTrue('companies permissions declared', str_contains($modulePhp, "permissionCategory('companies'"));
expectTrue('dynamic tables registered', str_contains($modulePhp, "'maker_jobs'") && str_contains($modulePhp, "'maker_bids'") && str_contains($modulePhp, "'maker_companies'") && str_contains($modulePhp, "'maker_job_types'") && str_contains($modulePhp, "'maker_job_files'"));
expectTrue('admin menus include 유형 관리', str_contains($modulePhp, '유형 관리'));
expectTrue('additive unique bid constraint', str_contains($migration, 'maker_bids_job_user_unique'));
expectTrue('additive company_id on bids', str_contains($migration, "'company_id'"));
expectTrue('additive company user unique', str_contains($migration, 'maker_companies_user_id_unique') || str_contains($migration, "unique('user_id')"));

$mutators = [
    "Route::get('jobs/mine'",
    "Route::get('jobs/{id}/viewer'",
    "Route::get('bids/mine'",
    "Route::get('jobs/form-defaults'",
    "Route::patch('jobs/{id}'",
    "Route::post('uploads'",
    "Route::post('jobs'",
    "Route::post('jobs/{id}/bids'",
    "Route::patch('jobs/{id}/bids/{bidId}'",
    "Route::post('jobs/{id}/award'",
    "Route::post('companies'",
];
$authBlockStart = strpos($api, "middleware(['auth:sanctum', 'throttle:60,1'])");
$authBlockEnd = strpos($api, "Route::prefix('admin')");
expectTrue('user auth block found', $authBlockStart !== false && $authBlockEnd !== false && $authBlockStart < $authBlockEnd);
if ($authBlockStart !== false && $authBlockEnd !== false) {
    $block = substr($api, $authBlockStart, $authBlockEnd - $authBlockStart);
    foreach ($mutators as $route) {
        expectTrue("auth block contains {$route}", str_contains($block, $route));
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
