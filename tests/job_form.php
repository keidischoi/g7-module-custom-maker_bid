<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\JobPresenter;
use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\PrivacyRules;
use Modules\Custom\MakerBids\Support\TypeCatalog;
use Modules\Custom\MakerBids\Support\TypeRules;
use Modules\Custom\MakerBids\Support\UploadRules;
use Modules\Custom\MakerBids\Models\MakerJobFile;

require __DIR__.'/bootstrap.php';

$defaults = TypeCatalog::defaults();
expect('seed type count is 6', count($defaults), 6);
expect('first seed is 3D 모델링', $defaults[0]['name'], '3D 모델링');
expect('print_3d slug present', $defaults[1]['slug'], 'print_3d');
expect('design_mockup is design-only', $defaults[4]['is_design_only'], true);
expect('modeling_3d does not require address', TypeCatalog::requiresAddress($defaults[0]), false);
expectTrue('modeling_3d includes modeling', $defaults[0]['includes_modeling']);
expectFalse('print_3d does not include modeling', TypeCatalog::includesModeling($defaults[1]));
expectTrue('full_package includes modeling', TypeCatalog::includesModeling($defaults[2]));
expectTrue('character_figure includes modeling', TypeCatalog::includesModeling($defaults[3]));
expectFalse('design_mockup does not include modeling', TypeCatalog::includesModeling($defaults[4]));
expectTrue('working_prototype includes modeling', TypeCatalog::includesModeling($defaults[5]));
expectFalse('print_3d slug heuristic', TypeCatalog::includesModeling(null, 'print_3d'));
expectTrue('name heuristic 모델링', TypeCatalog::includesModeling(['name' => '커스텀 모델링']));
expectTrue('name heuristic 커미션', TypeCatalog::includesModeling(['name' => '피규어 커미션']));
expectTrue('name heuristic 워킹 프로토타입', TypeCatalog::includesModeling(['name' => '워킹 프로토타입 제작']));
expectFalse('name heuristic 출력 대행', TypeCatalog::includesModeling(['name' => '3D 출력 대행']));
expectFalse('db flag false wins over 모델링 name', TypeCatalog::includesModeling(['includes_modeling' => false, 'name' => '커스텀 모델링']));
expectTrue('db flag true wins over 출력 name', TypeCatalog::includesModeling(['includes_modeling' => true, 'name' => '3D 출력 대행']));
expect('print_3d requires address', TypeCatalog::requiresAddress($defaults[1]), true);
expect('legacy design maps to design_mockup', TypeCatalog::normalizeSlug('design'), 'design_mockup');
expect('legacy manufacture maps to full_package', TypeCatalog::normalizeSlug('manufacture'), 'full_package');
expectTrue('print_3d known', TypeCatalog::isKnownSlug('print_3d'));
expectTrue('design legacy known', TypeCatalog::isKnownSlug('design'));
expectFalse('empty slug invalid format', TypeCatalog::isValidSlugFormat(''));
expectFalse('uppercase slug invalid', TypeCatalog::isValidSlugFormat('NOPE'));
expectTrue('custom slug format ok', TypeCatalog::isValidSlugFormat('laser_cut'));

expectTrue('quote_request is biddable', JobRules::isBiddableStatus('quote_request'));
expectTrue('request is biddable', JobRules::isBiddableStatus('request'));
expectTrue('legacy open is biddable', JobRules::isBiddableStatus('open'));
expectFalse('hold is not biddable', JobRules::isBiddableStatus('hold'));
expectTrue('hold hidden from public', JobRules::isHiddenFromPublic('hold'));
expectFalse('quote_request not hidden', JobRules::isHiddenFromPublic('quote_request'));
expectTrue('quote_request job with no close is open', JobRules::isOpen('quote_request', null));
expectTrue('request job is open', JobRules::isOpen('request', null));
expectFalse('hold is not open', JobRules::isOpen('hold', null));
expect('hold label', JobRules::statusLabel('hold'), '보류');
expect('request label', JobRules::statusLabel('request'), '의뢰');
expect('quote_request label', JobRules::statusLabel('quote_request'), '견적요청');
expect('open alias label', JobRules::statusLabel('open'), '견적요청');
expect('size label', JobRules::sizeLabel(300, 200, 50), '300 x 200 x 50 mm');
expect('named sizes label', JobRules::sizesLabel([
    ['name' => '본체', 'w' => 300, 'd' => 200, 'h' => 50],
    ['name' => '뚜껑', 'w' => 120, 'd' => 80, 'h' => 20],
]), '본체 300 x 200 x 50 mm · 뚜껑 120 x 80 x 20 mm');
expectTrue('sizes max is 20', JobRules::SIZES_MAX === 20);
$normalized = JobRules::normalizeSizes([
    'sizes' => json_encode([
        ['name' => '본체', 'w' => 300, 'd' => 200, 'h' => 50],
        ['name' => '', 'w' => '', 'd' => '', 'h' => ''],
        ['name' => '뚜껑', 'w' => 80, 'd' => 40, 'h' => 10],
    ], JSON_UNESCAPED_UNICODE),
]);
expect('normalize drops empty size rows', count($normalized), 2);
expect('normalize keeps first name', $normalized[0]['name'], '본체');
$fromLegacy = JobRules::normalizeSizes(['size_w' => 10, 'size_d' => 20, 'size_h' => 30]);
expect('legacy size_w/d/h becomes one row', $fromLegacy[0]['w'] ?? null, 10);
$capped = JobRules::normalizeSizes([
    'sizes' => array_fill(0, 25, ['name' => 'x', 'w' => 1, 'd' => 1, 'h' => 1]),
]);
expect('normalize caps at 20', count($capped), 20);
expect('budget range label', JobRules::budgetLabel(10000, 100000, null), '10,000~100,000원');
expect('contact hours combine', JobRules::contactHours('00:00', '05:00', null), '00:00 ~ 05:00');
expect('datetime-local from date', JobRules::datetimeLocal('2026-09-20'), '2026-09-20T00:00');
expect('datetime-local label', JobRules::datetimeLabel('2026-09-20T18:30'), '2026-09-20 18:30');
expectTrue('rush_deadline required_if when enabled', in_array('required_if:rush_fee_enabled,true', JobRules::createRules()['rush_deadline'], true));

$create = JobRules::createRules();
expectTrue('create requires type', in_array('required', $create['type'], true));
expectTrue('create requires title', in_array('required', $create['title'], true));
expectTrue('create requires contact_name', in_array('required', $create['contact_name'], true));
expectTrue('create requires contact_phone', in_array('required', $create['contact_phone'], true));
expectTrue('create requires contact_email', in_array('required', $create['contact_email'], true));
expectTrue('create budget_min nullable', in_array('nullable', $create['budget_min'], true));
expectTrue('create sizes optional', in_array('nullable', $create['sizes'], true));
expectTrue('create manager_name optional', in_array('nullable', $create['manager_name'], true));
expectTrue('create manager_phone optional', in_array('nullable', $create['manager_phone'], true));
expectTrue('create manager_email optional', in_array('nullable', $create['manager_email'], true));
expectTrue('listing status includes hold', in_array('hold', JobRules::LISTING_STATUSES, true));

$exts = JobRules::collectProvidedExtensions(['ext_stl' => true, 'ext_obj' => 1, 'provided_extensions' => ['FBX']]);
expectTrue('stl collected', in_array('STL', $exts, true));
expectTrue('obj collected', in_array('OBJ', $exts, true));
expectTrue('fbx collected', in_array('FBX', $exts, true));
$dwg = JobRules::collectProvidedExtensions(['ext_dwg' => true]);
expectTrue('dwg collected', in_array('DWG', $dwg, true));
expectTrue('dwg provided ext allowed', UploadRules::isAllowedProvidedExtension('dwg'));
expectTrue('create has audience rule', isset($create['audience']));
expectTrue('create has ownership_requested', isset($create['ownership_requested']));
expectFalse('create has no has_copyright field', isset($create['has_copyright']));
expect('audience 업체만', JobRules::normalizeAudience('업체만'), 'company');
expect('audience 개인만', JobRules::normalizeAudience('개인만'), 'individual');
expect('audience default all', JobRules::normalizeAudience(''), 'all');
expect('audience 관리자', JobRules::normalizeAudience('관리자'), 'admin');
expect('audienceLabel 관리자', JobRules::audienceLabel('admin'), '관리자');
expectFalse('member cannot see admin-only', JobRules::canViewAudience('admin', false, false, true, false, null));
expectTrue('admin sees admin-only', JobRules::canViewAudience('admin', false, true, true, false, null));
expectTrue('owner sees admin-only', JobRules::canViewAudience('admin', true, false, true, false, null));
expectTrue('admin can bid admin-only', JobRules::canBidAudience('admin', true, false, null, BidRules::ALLOW_ALL, true, false));
expectFalse('member cannot bid admin-only', JobRules::canBidAudience('admin', true, false, null, BidRules::ALLOW_ALL, false, false));

expect('list sort 최신순', JobRules::normalizeListSort('최신순'), 'latest');
expect('list sort 등록순', JobRules::normalizeListSort('등록순'), 'created');
expect('list sort 조회순', JobRules::normalizeListSort('조회순'), 'views');
expect('audience label company', JobRules::audienceLabel('company'), '업체만');
expectTrue('company viewer sees company jobs', JobRules::canViewAudience('company', false, false, true, true, 'company'));
expectFalse('individual cannot see company-only', JobRules::canViewAudience('company', false, false, true, false, null));
expectTrue('owner sees company-only', JobRules::canViewAudience('company', true, false, true, false, null));
expectTrue('owner sees individual-only', JobRules::canViewAudience('individual', true, false, false, false, null));
expectTrue('guest sees all', JobRules::canViewAudience('all', false, false, false, false, null));
expectFalse('guest cannot see company-only', JobRules::canViewAudience('company', false, false, false, false, null));
expectFalse('guest cannot see individual-only', JobRules::canViewAudience('individual', false, false, false, false, null));
expectTrue('company can bid company-only', JobRules::canBidAudience('company', true, true, 'company'));
expectFalse('individual cannot bid company-only', JobRules::canBidAudience('company', true, false, null));
expectTrue('individual can bid individual-only', JobRules::canBidAudience('individual', true, false, null));
expectFalse('company cannot bid individual-only', JobRules::canBidAudience('individual', true, true, 'company'));
expectTrue('member can bid all', JobRules::canBidAudience('all', true, false, null));
expectFalse('guest cannot bid all', JobRules::canBidAudience('all', false, false, null));
expectTrue('admin mode skips job audience', JobRules::canBidAudience('company', true, false, null, BidRules::ALLOW_ADMIN, true, false));
expectFalse('member blocked by admin mode even on all jobs', JobRules::canBidAudience('all', true, false, null, BidRules::ALLOW_ADMIN, false, false));
expectTrue('designated company can bid all', JobRules::canBidAudience('all', true, true, 'company', BidRules::ALLOW_DESIGNATED, false, true));
expectFalse('designated company cannot bid individual-only', JobRules::canBidAudience('individual', true, true, 'company', BidRules::ALLOW_DESIGNATED, false, true));
expectTrue('company mode and company audience compose', JobRules::canBidAudience('company', true, true, 'company', BidRules::ALLOW_APPROVED_COMPANY, false, false));
expectFalse('individual mode cannot bid company-only job', JobRules::canBidAudience('company', true, true, 'individual', BidRules::ALLOW_APPROVED_INDIVIDUAL, false, false));
expectTrue('union mode can bid all jobs if company', JobRules::canBidAudience('all', true, true, 'company', BidRules::ALLOW_APPROVED_BIDDERS, false, false));
expectFalse('일반회원 cannot bid when registered company', JobRules::canBidAudience('all', true, true, 'company', BidRules::ALLOW_MEMBER, false, false));
expectTrue('일반회원 can bid all when not registered', JobRules::canBidAudience('all', true, false, null, BidRules::ALLOW_MEMBER, false, false));
expectTrue('undefined type query ignored', JobRules::listTypeFilter('undefined') === null);
expectTrue('empty type query ignored', JobRules::listTypeFilter('') === null);
expect('print type query kept', JobRules::listTypeFilter('print_3d'), 'print_3d');
expectTrue('open list status means biddable', JobRules::listStatusFilter('open') === JobRules::BIDDABLE_STATUSES);
expectTrue('blank list status is no filter', JobRules::listStatusFilter(null) === null);
expect('hold list status stays hold', JobRules::listStatusFilter('hold'), ['hold']);
expect('draft list status stays draft', JobRules::listStatusFilter('임시저장'), ['draft']);
expect('disputed list status stays disputed', JobRules::listStatusFilter('분쟁조정'), ['disputed']);
expect('status sort alias', JobRules::normalizeListSort('상태순'), JobRules::LIST_SORT_STATUS);
expect('latest sort default', JobRules::normalizeListSort(''), JobRules::LIST_SORT_LATEST);

expectTrue('personal keys include manager fields', in_array('manager_name', PrivacyRules::personalKeys(), true) && in_array('manager_phone', PrivacyRules::personalKeys(), true) && in_array('manager_email', PrivacyRules::personalKeys(), true));
expectTrue('owner sees personal', PrivacyRules::canViewPersonal(7, 7, 'quote_request', null, false));
expectTrue('admin sees personal', PrivacyRules::canViewPersonal(1, 9, 'quote_request', null, true));
expectFalse('stranger masked before award', PrivacyRules::canViewPersonal(3, 9, 'quote_request', null, false));
expectFalse('bidder masked before award', PrivacyRules::canViewPersonal(4, 9, 'quote_request', 4, false));
expectTrue('awarded bidder sees personal when done', PrivacyRules::canViewPersonal(4, 9, 'done', 4, false));
expectTrue('awarded bidder sees personal after award', PrivacyRules::canViewPersonal(4, 9, 'awarded', 4, false));
expectFalse('other bidder still masked after award', PrivacyRules::canViewPersonal(5, 9, 'awarded', 4, false));
expectTrue('awarded maker can view archives/delivery', PrivacyRules::canViewArchives(4, 9, 'awarded', 4, false));
expectTrue('done maker can view archives', PrivacyRules::canViewArchives(4, 9, 'done', 4, false));
expectFalse('other bidder still no archives', PrivacyRules::canViewArchives(5, 9, 'awarded', 4, false));
expectFalse('archives hidden before award', PrivacyRules::canViewArchives(4, 9, 'quote_request', 4, false));

expectTrue('zip allowed archive', UploadRules::isAllowedExtension('archives', 'refs.zip'));
expectTrue('tar.gz allowed', UploadRules::isAllowedExtension('archives', 'model.tar.gz'));
expectFalse('stl not archive', UploadRules::isAllowedExtension('archives', 'a.stl'));
expectTrue('stl delivery', UploadRules::isAllowedExtension('delivery', 'a.stl'));
expectTrue('pdf delivery', UploadRules::isAllowedExtension('delivery', 'a.pdf'));
expectTrue('delivery ttl days', UploadRules::DELIVERY_TTL_DAYS === 30);
expectTrue('png image', UploadRules::isAllowedExtension('images', 'a.png'));
expectTrue('zip archive', UploadRules::isAllowedExtension('archives', 'a.zip'));
expectTrue('png logo collection', UploadRules::isAllowedExtension('logos', 'keidis.png'));
expectFalse('zip not logo', UploadRules::isAllowedExtension('logos', 'a.zip'));
expectFalse('zip not image', UploadRules::isAllowedExtension('images', 'a.zip'));
expectTrue('STL provided ext', UploadRules::isAllowedProvidedExtension('stl'));

$store = TypeRules::storeRules();
expectTrue('type slug required', in_array('required', $store['slug'], true));
expectTrue('type name required', in_array('required', $store['name'], true));
expectTrue('type includes_modeling optional boolean', isset($store['includes_modeling']) && in_array('boolean', $store['includes_modeling'], true));
expectTrue('provided_extensions not required', in_array('nullable', $create['provided_extensions'], true) && ! in_array('required', $create['provided_extensions'], true));

$row = new MakerJobFile();
$row->id = 7;
$row->job_id = 1;
$row->hash = 'pnghash01234567890123456789012';
$row->original_filename = 'shot.png';
$row->mime_type = 'image/png';
$row->size = 2048;
$row->collection = 'images';
$row->sort_order = 0;
$att = $row->toAttachmentArray();
expectTrue('png attachment is_image', $att['is_image'] === true);
expectTrue('png attachment has hash', $att['hash'] === 'pnghash01234567890123456789012');
expectTrue('png attachment has download_url', str_contains((string) $att['download_url'], $att['hash']));
$payload = $row->toUploaderPayload();
expectTrue('uploader payload success wrap', $payload['success'] === true && is_array($payload['data']) && $payload['data']['hash'] === $att['hash']);
expectTrue('uploader payload also top-level hash', $payload['hash'] === $att['hash']);

$env = JobPresenter::envelope(['id' => 42, 'title' => 'test-job']);
expectTrue('job envelope has top-level id', ($env['id'] ?? null) === 42);
expectTrue('job envelope nested data id', is_array($env['data'] ?? null) && ($env['data']['id'] ?? null) === 42);
expectTrue('job envelope success flag', ($env['success'] ?? null) === true);

$zip = new MakerJobFile();
$zip->id = 8;
$zip->job_id = 1;
$zip->hash = 'ziphash01234567890123456789012';
$zip->original_filename = 'refs.zip';
$zip->mime_type = 'application/zip';
$zip->size = 4096;
$zip->collection = 'archives';
$zip->sort_order = 0;
$zipAtt = $zip->toAttachmentArray();
expectFalse('zip attachment is not image', $zipAtt['is_image']);
$zipPayload = $zip->toUploaderPayload();
expectTrue('zip uploader payload wrapped', $zipPayload['success'] === true && $zipPayload['data']['hash'] === $zipAtt['hash']);

expectTrue('png attachment has file_name alias', ($att['file_name'] ?? null) === 'shot.png' && ($att['name'] ?? null) === 'shot.png');
expectTrue('png attachment has url alias', ($att['url'] ?? '') === ($att['download_url'] ?? 'x'));
$logo = \Modules\Custom\MakerBids\Support\CompanyPresenter::logoFiles('logohash01234567890123456789012');
expectTrue('logo_files attachment shape', is_array($logo) && ($logo[0]['is_image'] ?? false) === true && ($logo[0]['file_name'] ?? '') === 'logo' && str_contains((string) ($logo[0]['download_url'] ?? ''), 'logohash'));
expectTrue('toUploaderFile helper', method_exists(\Modules\Custom\MakerBids\Support\UploadRules::class, 'toUploaderFile'));


echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

expect('korean 견적요청 -> quote_request', JobRules::normalizeListingStatus('견적요청'), 'quote_request');
expect('korean 의뢰 -> request', JobRules::normalizeListingStatus('의뢰'), 'request');
expect('korean 보류 -> hold', JobRules::normalizeListingStatus('보류'), 'hold');
expect('open alias -> quote_request', JobRules::normalizeListingStatus('open'), 'quote_request');
expect('invalid korean status falls back', JobRules::normalizeListingStatus('알수없음'), 'quote_request');
expectTrue('PDF in provided defaults', in_array('PDF', UploadRules::PROVIDED_EXTENSIONS, true));

