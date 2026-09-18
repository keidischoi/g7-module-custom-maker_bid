<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\AwardRules;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\UploadRules;
use Modules\Custom\MakerBids\Support\JobRules;

require __DIR__.'/bootstrap.php';

$now = new DateTimeImmutable('2026-09-15 12:00:00');

expectTrue('open job with no close is open', JobRules::isOpen('open', null, $now));
expectFalse('awarded job is not open', JobRules::isOpen('awarded', null, $now));
expectFalse('hold job is not open', JobRules::isOpen('hold', null, $now));
expectFalse('closed when closes_at is past', JobRules::isOpen('open', '2026-09-01 00:00:00', $now));
expectTrue('open when closes_at is future', JobRules::isOpen('open', '2026-09-16 00:00:00', $now));
expectFalse('open exactly at close is closed', JobRules::isOpen('open', '2026-09-15 12:00:00', $now));
expectTrue('print_3d type allowed', JobRules::isAllowedType('print_3d'));
expectTrue('design_mockup type allowed', JobRules::isAllowedType('design_mockup'));
expectTrue('full_package type allowed', JobRules::isAllowedType('full_package'));
expectFalse('uppercase type rejected', JobRules::isAllowedType('NOPE'));
expectTrue('title max is 200', JobRules::TITLE_MAX === 200);

$create = JobRules::createRules();
expectTrue('create requires type', in_array('required', $create['type'], true));
expectTrue('create requires title', in_array('required', $create['title'], true));
expectTrue('create budget nullable', in_array('nullable', $create['budget'], true));

expectTrue('member can bid', BidRules::canBid(true, false));
expectTrue('approved company can bid', BidRules::canBid(false, true));
expectTrue('member and company can bid', BidRules::canBid(true, true));
expectFalse('guest cannot bid', BidRules::canBid(false, false));
expectTrue('seven bid-allow modes', count(BidRules::ALLOW_MODES) === 7);
expectTrue('seven bid-allow labels', BidRules::ALLOW_LABELS === [
    'all' => '모두',
    'admin' => '관리자',
    'designated' => '지정업체',
    'approved_company' => '모든 등록된 업체',
    'approved_individual' => '등록된 개인회원',
    'approved_bidders' => '모든 등록된 업체 & 등록된 개인회원',
    'member' => '일반회원',
]);
expectTrue('all mode is 모두', BidRules::allowLabel('all') === '모두');
expectTrue('members slug maps to all', BidRules::normalizeAllow('members') === BidRules::ALLOW_ALL);
expectTrue('admin mode Korean', BidRules::normalizeAllow('관리자') === BidRules::ALLOW_ADMIN);
expectTrue('designated Korean', BidRules::normalizeAllow('지정업체') === BidRules::ALLOW_DESIGNATED);
expectTrue('approved company Korean', BidRules::normalizeAllow('모든 등록된 업체') === BidRules::ALLOW_APPROVED_COMPANY);
expectTrue('approved individual Korean', BidRules::normalizeAllow('등록된 개인회원') === BidRules::ALLOW_APPROVED_INDIVIDUAL);
expectTrue('approved bidders union Korean', BidRules::normalizeAllow('모든 등록된 업체 & 등록된 개인회원') === BidRules::ALLOW_APPROVED_BIDDERS);
expectTrue('regular member Korean', BidRules::normalizeAllow('일반회원') === BidRules::ALLOW_MEMBER);
expectTrue('legacy company slug maps', BidRules::normalizeAllow('company') === BidRules::ALLOW_APPROVED_COMPANY);
expectTrue('legacy individual slug maps', BidRules::normalizeAllow('individual') === BidRules::ALLOW_APPROVED_INDIVIDUAL);
expectFalse('guest cannot bid admin mode', BidRules::canBid(false, false, BidRules::ALLOW_ADMIN, true));
expectTrue('admin can bid admin mode', BidRules::canBid(true, false, BidRules::ALLOW_ADMIN, true));
expectFalse('member cannot bid admin mode', BidRules::canBid(true, false, BidRules::ALLOW_ADMIN, false));
expectTrue('designated approved company can bid', BidRules::canBid(true, true, BidRules::ALLOW_DESIGNATED, false, 'company', true));
expectFalse('approved company without flag cannot bid designated', BidRules::canBid(true, true, BidRules::ALLOW_DESIGNATED, false, 'company', false));
expectFalse('member cannot bid designated', BidRules::canBid(true, false, BidRules::ALLOW_DESIGNATED, false, null, false));
expectTrue('company-kind can bid approved_company', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_COMPANY, false, 'company'));
expectFalse('individual cannot bid approved_company', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_COMPANY, false, 'individual'));
expectTrue('individual-kind can bid approved_individual', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_INDIVIDUAL, false, 'individual'));
expectFalse('plain member cannot bid approved_individual', BidRules::canBid(true, false, BidRules::ALLOW_APPROVED_INDIVIDUAL, false, null));
expectFalse('company-kind cannot bid approved_individual', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_INDIVIDUAL, false, 'company'));
expectTrue('company in union mode', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_BIDDERS, false, 'company'));
expectTrue('individual in union mode', BidRules::canBid(true, true, BidRules::ALLOW_APPROVED_BIDDERS, false, 'individual'));
expectFalse('plain member not in union mode', BidRules::canBid(true, false, BidRules::ALLOW_APPROVED_BIDDERS, false, null));
expectTrue('plain member can bid 일반회원', BidRules::canBid(true, false, BidRules::ALLOW_MEMBER, false, null));
expectFalse('approved company cannot bid 일반회원', BidRules::canBid(true, true, BidRules::ALLOW_MEMBER, false, 'company'));
expectFalse('approved individual cannot bid 일반회원', BidRules::canBid(true, true, BidRules::ALLOW_MEMBER, false, 'individual'));
expectTrue('logged-in can bid 모두', BidRules::canBid(true, false, BidRules::ALLOW_ALL));
expectFalse('guest cannot bid 모두', BidRules::canBid(false, false, BidRules::ALLOW_ALL));
expectTrue('designated deny is Korean', BidRules::denyMessage('designated') === '지정업체로 지정·승인된 업체만 입찰할 수 있습니다.');
expectTrue('union deny is Korean', BidRules::denyMessage('approved_bidders') === '등록된 업체 또는 등록된 개인회원만 입찰할 수 있습니다.');
expectTrue('own job detected', BidRules::isOwnJob(7, 7));
expectFalse('other job not own', BidRules::isOwnJob(7, 8));
expectFalse('null owner is not own job', BidRules::isOwnJob(7, null));
expectTrue('create allowed when no existing bid', BidRules::canCreateOrUpdateOwn(3, null, true));
expectTrue('update own pending bid while open', BidRules::canUpdateOwn(3, 3, true, 'pending'));
expectFalse('cannot update someone else bid', BidRules::canUpdateOwn(3, 9, true, 'pending'));
expectFalse('cannot update when job closed', BidRules::canUpdateOwn(3, 3, false, 'pending'));
expectFalse('cannot update accepted bid', BidRules::canUpdateOwn(3, 3, true, 'accepted'));
expectFalse('cannot update rejected bid', BidRules::canUpdateOwn(3, 3, true, 'rejected'));

$write = BidRules::writeRules();
expectTrue('bid pending label', BidRules::statusLabel('pending') === '검토중');
expectTrue('bid accepted label', BidRules::statusLabel('accepted') === '낙찰');
expectTrue('bid amount min 1', in_array('min:1', $write['amount'], true));

expectTrue('owner can award', AwardRules::canAward(11, 11));
expectFalse('non-owner cannot award', AwardRules::canAward(11, 12));
expectFalse('null owner cannot award', AwardRules::canAward(11, null));

$awarded = AwardRules::apply(2, [
    ['id' => 1, 'status' => 'pending'],
    ['id' => 2, 'status' => 'pending'],
    ['id' => 3, 'status' => 'pending'],
]);
expect('accepted bid stays accepted', $awarded[1]['status'], 'accepted');
expect('other bids rejected #1', $awarded[0]['status'], 'rejected');
expect('other bids rejected #3', $awarded[2]['status'], 'rejected');

expectTrue('no company can apply', CompanyRules::canApply(null));
expectTrue('rejected company can reapply', CompanyRules::canApply('rejected'));
expectFalse('pending cannot apply again', CompanyRules::canApply('pending'));
expectFalse('approved cannot apply again', CompanyRules::canApply('approved'));
expectTrue('approved company flag', CompanyRules::isApproved('approved'));
expectFalse('pending is not approved', CompanyRules::isApproved('pending'));
expectTrue('can reject pending', CompanyRules::canReject('pending'));
expectFalse('cannot reject already rejected', CompanyRules::canReject('rejected'));
expectTrue('can approve pending', CompanyRules::canApprove('pending'));
expectFalse('cannot approve already approved', CompanyRules::canApprove('approved'));

// --- 0.10.1 marketplace must-haves ---
$marketSrc = (string) file_get_contents($root.'/src/Services/MarketplaceService.php');
expectTrue('closeExpired notifies owner', str_contains($marketSrc, 'deadline_closed') && str_contains($marketSrc, 'function closeExpired'));
expectTrue('notifyDeadlineSoon exists', str_contains($marketSrc, 'function notifyDeadlineSoon'));
expectTrue('runSchedule closes and notices', str_contains($marketSrc, 'function runSchedule') && str_contains($marketSrc, 'notifyDeadlineSoon'));
expectTrue('notify tries email memo and g7 system', str_contains($marketSrc, 'trySendEmail') && str_contains($marketSrc, 'trySendMemo') && str_contains($marketSrc, 'trySendSystemNotification'));
expectTrue('work statuses include printing', str_contains($marketSrc, "'printing'") && str_contains($marketSrc, "'shipping'"));
expectTrue('shipping requires tracking', str_contains($marketSrc, '발송 상태에는 송장번호가 필요합니다'));
expectTrue('reviewsForJob API helper', str_contains($marketSrc, 'function reviewsForJob'));
expectTrue('resolveReport helper', str_contains($marketSrc, 'function resolveReport'));

$bidSrc = (string) file_get_contents($root.'/src/Services/BidService.php');
expectTrue('new bid notifies owner', str_contains($bidSrc, 'new_bid') && str_contains($bidSrc, 'MarketplaceService'));

$adminJob = (string) file_get_contents($root.'/src/Http/Controllers/Admin/JobAdminController.php');
expectTrue('approve notifies', str_contains($adminJob, "'approved'"));
expectTrue('hold notifies', str_contains($adminJob, "'hold'"));

$mod = (string) file_get_contents($root.'/module.php');
expectTrue('getSchedules registers maker-bids:run-schedule', str_contains($mod, 'function getSchedules') && str_contains($mod, 'maker-bids:run-schedule'));
expectTrue('module declares g7 notification definition', str_contains($mod, 'function getNotificationDefinitions') && str_contains($mod, 'maker_bids.notice'));
expectTrue('schedule command file exists', is_file($root.'/src/Console/Commands/RunMarketplaceScheduleCommand.php'));
expectTrue('service provider registers command', is_file($root.'/src/Providers/MakerBidsServiceProvider.php'));
expectTrue('seed-dummy-bids command file exists', is_file($root.'/src/Console/Commands/SeedDummyBidsCommand.php'));
$providerSrc = (string) file_get_contents($root.'/src/Providers/MakerBidsServiceProvider.php');
expectTrue('provider registers seed-dummy-bids', str_contains($providerSrc, 'SeedDummyBidsCommand'));


$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('reviews route', str_contains($api, "jobs/{id}/reviews"));
expectTrue('company reviews route', str_contains($api, "companies/{id}/reviews"));
expectTrue('run-schedule route', str_contains($api, 'jobs/run-schedule'));
expectTrue('admin resolve report route', str_contains($api, "reports/{id}"));

expectTrue('draft is listing status', in_array('draft', JobRules::LISTING_STATUSES, true));
expectTrue('draft hidden from public', in_array('draft', JobRules::HIDDEN_PUBLIC_STATUSES, true));
expectTrue('disputed is status and hidden from public', in_array('disputed', JobRules::STATUSES, true) && in_array('disputed', JobRules::HIDDEN_PUBLIC_STATUSES, true));
expectTrue('disputed is not a member listing status', ! in_array('disputed', JobRules::LISTING_STATUSES, true));
expectTrue('draft status label is 임시저장', JobRules::statusLabel('draft') === '임시저장');
expectTrue('disputed status label is 분쟁조정', JobRules::statusLabel('disputed') === '분쟁조정');
expectTrue('normalizeStatus maps 분쟁상태', JobRules::normalizeStatus('분쟁상태') === 'disputed');
expectTrue('terms_agreed in create rules', array_key_exists('terms_agreed', JobRules::createRules()));

expectTrue('business_no normalize helper', method_exists(CompanyRules::class, 'normalizeBusinessNo'));
expectTrue('business_no rejects short', CompanyRules::isValidBusinessNo('123') === false);
expectTrue('business_no normalize strips dashes', CompanyRules::normalizeBusinessNo('123-45-67890') === '1234567890');
expectTrue('empty business_no allowed', CompanyRules::isValidBusinessNo(null) && CompanyRules::isValidBusinessNo(''));


// --- 0.10.1 follow-ups ---
expectTrue('export builds html forms', str_contains($marketSrc, 'buildRequestFormHtml') && str_contains($marketSrc, 'buildQuoteFormHtml'));
expectTrue('exportHtml helper', str_contains($marketSrc, 'function exportHtml'));
expectTrue('messages enrich author_label', str_contains($marketSrc, 'author_label') && str_contains($marketSrc, 'is_mine'));
expectTrue('runSchedule purges files', str_contains($marketSrc, 'files_purged') && str_contains($marketSrc, 'purgeExpired'));
$filesSrc = (string) file_get_contents($root.'/src/Services/JobFileService.php');
expectTrue('job files purgeExpired', str_contains($filesSrc, 'function purgeExpired'));
expectTrue('delivery collection supported', str_contains($filesSrc, 'COLLECTION_DELIVERY') || str_contains((string) file_get_contents($root.'/src/Support/UploadRules.php'), "COLLECTION_DELIVERY"));
expectTrue('delivery ttl constant', UploadRules::DELIVERY_TTL_DAYS >= 1);
$privacy = (string) file_get_contents($root.'/src/Support/PrivacyRules.php');
expectTrue('archives allow awarded maker', str_contains($privacy, "'awarded', 'done'"));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
