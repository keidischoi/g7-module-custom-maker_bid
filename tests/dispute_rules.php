<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\DisputeRules;
use Modules\Custom\MakerBids\Support\JobRules;

require __DIR__.'/bootstrap.php';

expectTrue('disputed status label', DisputeRules::LABEL === '분쟁조정');
expectTrue('can open from awarded', DisputeRules::canOpen('awarded'));
expectTrue('can open from done', DisputeRules::canOpen('done'));
expectTrue('can open while already disputed', DisputeRules::canOpen('disputed'));
expectFalse('cannot open from quote_request', DisputeRules::canOpen('quote_request'));
expectTrue('resolve to cancelled', DisputeRules::canResolveTo('cancelled'));
expect('normalize resolve 취소', DisputeRules::normalizeResolveTo('취소'), 'cancelled');
expectTrue('nine dispute factors', count(DisputeRules::FACTORS) === 9);
expect('사기 maps to fraud', DisputeRules::normalizeFactor('사기'), 'fraud');
expect('품질 maps to quality', DisputeRules::normalizeFactor('제품 품질 문제'), 'quality');
expect('배송 maps to shipping', DisputeRules::normalizeFactor('배송문제'), 'shipping');
expect('지연 maps to delay', DisputeRules::normalizeFactor('납기 지연'), 'delay');
expect('factor label quality', DisputeRules::factorLabel('quality'), '제품 품질 문제');
expect('compose reason prefixes factor', DisputeRules::composeReason('shipping', '박스가 찢어짐'), '배송 문제 · 박스가 찢어짐');
expect('kind claim label', DisputeRules::kindLabel('claim'), '분쟁');
expect('kind report label', DisputeRules::kindLabel('report'), '신고');
expect('open status label', DisputeRules::statusLabel('open'), '진행중');
expect('closed status label', DisputeRules::statusLabel('closed'), '종결');
expectTrue('options include fraud', in_array('fraud', array_column(DisputeRules::factorOptions(), 'value'), true));
expectTrue('cancelled is public after dispute resolve', in_array('cancelled', JobRules::PUBLIC_STATUSES, true));

$root = dirname(__DIR__);
$ws = (string) file_get_contents($root.'/resources/layouts/user/jobs_workspace.json');
expectTrue('workspace has factor select', str_contains($ws, '"id": "claim_factor"') && str_contains($ws, '사기·허위'));
expectTrue('workspace posts factor in claim body', str_contains($ws, '{{_local.claim}}') && str_contains($ws, '/claim'));
expectTrue('workspace report has factor', str_contains($ws, '"id": "report_factor"'));
$nav = (string) file_get_contents($root.'/resources/assets/nav.js');
expectTrue('nav has 분쟁 tab', str_contains($nav, "/maker-bids/disputes") && str_contains($nav, "label: '분쟁'"));
$userRoutes = json_decode((string) file_get_contents($root.'/resources/routes/user.json'), true);
expectTrue('user disputes route', in_array('*/maker-bids/disputes', array_column($userRoutes['routes'], 'path'), true));
$adminRoutes = json_decode((string) file_get_contents($root.'/resources/routes/admin.json'), true);
expectTrue('admin disputes route', in_array('*/admin/maker-bids/disputes', array_column($adminRoutes['routes'], 'path'), true));
$mod = (string) file_get_contents($root.'/module.php');
expectTrue('admin menu 분쟁조정', str_contains($mod, '분쟁조정') && str_contains($mod, '/admin/maker-bids/disputes'));
$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('detail 분쟁 접수 link', str_contains($show, '분쟁 접수') && str_contains($show, 'can_dispute'));
$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('disputes mine API', str_contains($api, "Route::get('disputes'"));
$mig = (string) file_get_contents($root.'/database/migrations/2026_09_18_000020_ensure_claim_report_factor.php');
expectTrue('factor migration claims+reports', str_contains($mig, 'maker_claims') && str_contains($mig, "'factor'"));
$adminDisp = (string) file_get_contents($root.'/resources/layouts/admin/disputes_index.json');
expectTrue('admin disputes resolve awarded/cancel/done', str_contains($adminDisp, '낙찰 유지') && str_contains($adminDisp, '"job_status": "cancelled"') && str_contains($adminDisp, '"job_status": "done"'));
$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list subnav 분쟁', str_contains($list, '/maker-bids/disputes'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
