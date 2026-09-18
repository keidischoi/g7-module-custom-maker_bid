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
expectTrue('public job list remains guest-accessible', str_contains($api, "Route::get('jobs', [JobController::class, 'index'])"));
expectTrue('public job list uses optional.sanctum', str_contains($api, "Route::get('jobs', [JobController::class, 'index'])") && preg_match("/Route::get\\('jobs'.*?optional\\.sanctum/s", $api) === 1);
expectTrue('public job show uses optional.sanctum', str_contains($api, "Route::get('jobs/{id}', [JobController::class, 'show'])") && preg_match("/Route::get\\('jobs\\/\\{id\\}'.*?optional\\.sanctum/s", $api) === 1);
expectTrue('mutating user APIs use auth:sanctum', str_contains($api, "middleware(['auth:sanctum', 'throttle:60,1'])"));
expectTrue('bid create route exists', str_contains($api, "Route::post('jobs/{id}/bids', [BidController::class, 'store'])"));
expectTrue('own-bid update route exists', str_contains($api, "Route::patch('jobs/{id}/bids/{bidId}', [BidController::class, 'update'])"));
expectTrue('owner award route exists', str_contains($api, "Route::post('jobs/{id}/award', [JobController::class, 'award'])"));
expectTrue('company apply route exists', str_contains($api, "Route::post('companies', [CompanyController::class, 'store'])"));
expectTrue('company form-defaults route exists', str_contains($api, "Route::get('companies/form-defaults'"));
expectTrue('public companies list route exists', str_contains($api, "Route::get('companies', [CompanyController::class, 'index'])"));
expectTrue('admin company patch route', str_contains($api, "Route::patch('companies/{id}'"));
expectTrue('admin company hold route', str_contains($api, "Route::post('companies/{id}/hold'"));
expectTrue('admin group uses auth:sanctum', str_contains($api, "->middleware(['auth:sanctum', 'throttle:600,1'])"));
expectTrue('admin jobs read permission', str_contains($api, 'permission:admin,custom-maker_bids.jobs.read'));
expectTrue('admin jobs update permission', str_contains($api, 'permission:admin,custom-maker_bids.jobs.update'));
expectTrue('admin jobs delete permission', str_contains($api, 'permission:admin,custom-maker_bids.jobs.delete'));
expectTrue('admin bids read permission', str_contains($api, 'permission:admin,custom-maker_bids.bids.read'));
expectTrue('admin bids update permission', str_contains($api, 'permission:admin,custom-maker_bids.bids.update'));
expectTrue('admin bids delete permission', str_contains($api, 'permission:admin,custom-maker_bids.bids.delete'));
expectTrue('admin companies read permission', str_contains($api, 'permission:admin,custom-maker_bids.companies.read'));
expectTrue('admin companies create permission', str_contains($api, 'permission:admin,custom-maker_bids.companies.create'));
expectTrue('admin companies update permission', str_contains($api, 'permission:admin,custom-maker_bids.companies.update'));
expectTrue('admin companies delete permission', str_contains($api, 'permission:admin,custom-maker_bids.companies.delete'));
expectTrue('admin company reject route', str_contains($api, "Route::post('companies/{id}/reject'"));
expectTrue('admin company approve route', str_contains($api, "Route::post('companies/{id}/approve'"));
expectTrue('admin company delete route', str_contains($api, "Route::delete('companies/{id}'"));
expectTrue('public settings route', str_contains($api, "Route::get('settings', [SettingsController::class, 'show'])"));
expectTrue('admin settings read permission', str_contains($api, 'permission:admin,custom-maker_bids.settings.read'));
expectTrue('admin settings update permission', str_contains($api, 'permission:admin,custom-maker_bids.settings.update'));
expectTrue('admin settings put route', str_contains($api, "Route::put('settings'"));
expectTrue('admin bid patch route', str_contains($api, "Route::patch('bids/{id}', [BidAdminController::class, 'update'])"));
expectTrue('admin job patch route', str_contains($api, "Route::patch('jobs/{id}', [JobAdminController::class, 'update'])"));
$moduleMeta = json_decode($moduleJson, true);
expectTrue('module.json parses', is_array($moduleMeta));
expectTrue('jobs edit owner route', str_contains($api, "jobs/{id}/edit") || str_contains($api, 'jobs.edit'));
expectTrue('module version is 0.10.35', ($moduleMeta['version'] ?? null) === '0.10.35');
expectTrue('module identifier is exactly custom-maker_bids', ($moduleMeta['identifier'] ?? null) === 'custom-maker_bids');
expectTrue('module identifier is not custom-maker_bid', ($moduleMeta['identifier'] ?? null) !== 'custom-maker_bid');
expectTrue(
    'github_url is g7-module-custom-maker_bids',
    ($moduleMeta['github_url'] ?? null) === 'https://github.com/keidischoi/g7-module-custom-maker_bids',
);
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
expectTrue('composer name is custom/maker-bids', ($composer['name'] ?? null) === 'custom/maker-bids');
expectTrue('composer version matches module', ($composer['version'] ?? null) === '0.10.35');
expectTrue(
    'psr-4 is Modules\\Custom\\MakerBids\\ not MakerBid',
    isset($composer['autoload']['psr-4']['Modules\\Custom\\MakerBids\\'])
    && ! isset($composer['autoload']['psr-4']['Modules\\Custom\\MakerBid\\']),
);
expectTrue('module.php namespace is MakerBids', str_contains($modulePhp, "namespace Modules\\Custom\\MakerBids;"));
expectTrue('module.php has no MakerBid namespace', ! preg_match('/namespace Modules\\\\Custom\\\\MakerBid;/', $modulePhp));
$g7Ns = static function (string $identifier): string {
    $parts = explode('-', $identifier, 2);
    $vendor = ucfirst($parts[0] ?? 'custom');
    $name = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $parts[1] ?? $parts[0])));

    return 'Modules\\'.$vendor.'\\'.$name.'\\';
};
expectTrue('G7 maps custom-maker_bids to MakerBids', $g7Ns('custom-maker_bids') === 'Modules\\Custom\\MakerBids\\');
expectTrue('G7 maps custom-maker_bid to MakerBid', $g7Ns('custom-maker_bid') === 'Modules\\Custom\\MakerBid\\');
expectTrue('live identifier maps to existing MakerBids namespace', $g7Ns((string) $moduleMeta['identifier']) === 'Modules\\Custom\\MakerBids\\');
expectTrue('job types public route', str_contains($api, "Route::get('job-types', [JobTypeController::class, 'index'])"));
expectTrue('job form-defaults route', str_contains($api, "Route::get('jobs/form-defaults'"));
expectTrue('owner job update route', str_contains($api, "Route::patch('jobs/{id}', [JobController::class, 'update'])"));
expectTrue('upload staging route', str_contains($api, "Route::post('uploads'"));
expectTrue('admin job-types route', str_contains($api, "Route::get('job-types', [JobTypeAdminController::class, 'index'])"));
expectTrue('admin job-types move route', str_contains($api, "Route::post('job-types/{id}/move'"));
expectTrue('job viewer route exists', str_contains($api, "Route::get('jobs/{id}/viewer', [JobController::class, 'viewer'])"));
expectTrue('job viewer uses optional.sanctum', preg_match("/Route::get\('jobs\/\{id\}\/viewer'.*?optional\.sanctum/s", $api) === 1);
expectTrue('job store envelopes id for layout navigate', str_contains($jobController, 'JobPresenter::envelope($job)'));
$jobService = (string) file_get_contents($root.'/src/Services/JobService.php');
expectTrue('findPublic maps missing id to domain 404', str_contains($jobService, 'function denyPublic') && str_contains($jobService, '->find($id)'));
expectTrue('findPublic keeps same 404 copy for hidden jobs', str_contains($jobService, '의뢰를 찾을 수 없습니다.'));
expectTrue('listPublic includes owner jobs via orWhere user_id', str_contains($jobService, "orWhere('user_id', \$uid)"));
expectTrue('listPublic uses listStatusFilter', str_contains($jobService, 'listStatusFilter'));
expectTrue('listPublic keeps optional owner visibility 0.6.1', str_contains($jobService, "orWhere('user_id', \$uid)") && str_contains((string) file_get_contents($root.'/src/routes/api.php'), 'optional.sanctum'));
expectTrue('listPublic exposes self-only draft/dispute chips', str_contains($jobService, 'viewerSelfStatusChips') && str_contains((string) file_get_contents($root.'/src/Http/Controllers/JobController.php'), 'viewer_status_chips'));
$jobCtrlSrc = (string) file_get_contents($root.'/src/Http/Controllers/JobController.php');
expectTrue('jobs index meta includes viewer is_admin', str_contains($jobCtrlSrc, "'is_admin'") && str_contains($jobCtrlSrc, "['meta']['viewer']"));
expectTrue('actorFromRequest resolves sanctum bearer token', str_contains($jobService, 'PersonalAccessToken') && str_contains($jobService, 'bearerToken'));
expectTrue('listPublic resolves web session user', str_contains($jobService, "Auth::guard") && str_contains($jobService, 'actorFromRequest') && str_contains($jobService, "'web'"));
expectTrue('listPublic self chips include hold', str_contains($jobService, "'hold' => \$isAdmin || \$uid > 0"));
expectTrue('isAdminActor checks is_super hasRole isAdmin', str_contains($jobService, 'function isAdminActor') && str_contains($jobService, 'is_super') && str_contains($jobService, 'hasRole') && str_contains($jobService, 'isSuperAdmin'));
expectTrue('actorFromRequest prefers admin actor', str_contains($jobService, 'actorCandidates') && str_contains($jobService, "'admin'") && str_contains($jobService, 'isAdminActor($user)'));
expectTrue('admin update uses normalizeStatus', str_contains($jobService, 'normalizeStatus($payload[\'status\'])'));
expectTrue('listPublic includes disputed awarded bidder', str_contains($jobService, 'awarded_bid_id') && str_contains($jobService, 'DisputeRules::STATUS'));
expectTrue('viewerContext uses bidAllowMode', str_contains($jobService, 'bidAllowMode') && str_contains($jobService, 'isDesignated'));
$bidService = (string) file_get_contents($root.'/src/Services/BidService.php');
expectTrue('bid create/update uses denyMessage', str_contains($bidService, 'BidRules::denyMessage') && str_contains($bidService, 'assertEligible'));
expectTrue('user bid update re-checks eligibility', substr_count($bidService, 'assertEligible') >= 2);
expectTrue('bids mine route exists', str_contains($api, "Route::get('bids/mine', [BidController::class, 'mine'])"));
expectTrue('admin job cancel route exists', str_contains($api, "Route::post('jobs/{id}/cancel', [JobAdminController::class, 'cancel'])"));
expectTrue('admin menus include 의뢰 목록', str_contains($modulePhp, '의뢰 목록'));
expectTrue('admin menus include 입찰 관리', str_contains($modulePhp, '입찰 관리'));
expectTrue('admin menus include 회사 목록', str_contains($modulePhp, '회사 목록'));
expectTrue('admin menus include 회원 활동', str_contains($modulePhp, '회원 활동'));
expectTrue('admin menus include 설정', str_contains($modulePhp, '설정'));
expectTrue('admin menus include 분쟁조정', str_contains($modulePhp, '분쟁조정'));
expectTrue('admin menus include 결제', str_contains($modulePhp, "'결제'"));
expectTrue('jobs permissions declared', str_contains($modulePhp, "permissionCategory('jobs'"));
expectTrue('bids permissions declared', str_contains($modulePhp, "permissionCategory('bids'"));
expectTrue('companies permissions declared', str_contains($modulePhp, "permissionCategory('companies'"));
expectTrue('settings permissions declared', str_contains($modulePhp, "permissionCategory('settings'"));
expectTrue('dynamic tables registered', str_contains($modulePhp, "'maker_jobs'") && str_contains($modulePhp, "'maker_bids'") && str_contains($modulePhp, "'maker_companies'") && str_contains($modulePhp, "'maker_job_types'") && str_contains($modulePhp, "'maker_job_files'") && str_contains($modulePhp, "'maker_module_settings'"));
expectTrue('admin menus include 유형 관리', str_contains($modulePhp, '유형 관리'));
expectTrue('additive unique bid constraint', str_contains($migration, 'maker_bids_job_user_unique'));
expectTrue('additive company_id on bids', str_contains($migration, "'company_id'"));
expectTrue('additive company user unique', str_contains($migration, 'maker_companies_user_id_unique') || str_contains($migration, "unique('user_id')"));

$mutators = [
    "Route::get('jobs/mine'",
    "Route::get('bids/mine'",
    "Route::get('jobs/form-defaults'",
    "Route::patch('jobs/{id}'",
    "Route::post('uploads'",
    "Route::post('jobs'",
    "Route::post('jobs/{id}/bids'",
    "Route::patch('jobs/{id}/bids/{bidId}'",
    "Route::post('jobs/{id}/award'",
    "Route::get('companies/form-defaults'",
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
    expectTrue('auth block excludes jobs viewer', ! str_contains($block, "Route::get('jobs/{id}/viewer'"));
}

$paginator = (string) file_get_contents($root.'/src/Support/ArrayPaginator.php');
expectTrue('ArrayPaginator helper exists', str_contains($paginator, 'last_page') && str_contains($paginator, 'per_page'));
$jobCtrl = (string) file_get_contents($root.'/src/Http/Controllers/JobController.php');
expectTrue('jobs index/mine paginated', str_contains($jobCtrl, 'ArrayPaginator::paginate') && substr_count($jobCtrl, 'ArrayPaginator::paginate') >= 2);
$bidCtrl = (string) file_get_contents($root.'/src/Http/Controllers/BidController.php');
expectTrue('bids mine paginated', str_contains($bidCtrl, 'ArrayPaginator::paginate'));
$coCtrl = (string) file_get_contents($root.'/src/Http/Controllers/CompanyController.php');
expectTrue('companies index paginated', str_contains($coCtrl, 'ArrayPaginator::paginate'));
$mktCtrl = (string) file_get_contents($root.'/src/Http/Controllers/MarketplaceController.php');
expectTrue('notices paginated', str_contains($mktCtrl, 'ArrayPaginator::paginate'));


echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
