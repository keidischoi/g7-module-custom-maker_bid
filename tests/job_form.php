<?php

declare(strict_types=1);

use Modules\Custom\MakerBid\Support\JobRules;
use Modules\Custom\MakerBid\Support\PrivacyRules;
use Modules\Custom\MakerBid\Support\TypeCatalog;
use Modules\Custom\MakerBid\Support\TypeRules;
use Modules\Custom\MakerBid\Support\UploadRules;

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

expectTrue('personal keys include manager fields', in_array('manager_name', PrivacyRules::personalKeys(), true) && in_array('manager_phone', PrivacyRules::personalKeys(), true) && in_array('manager_email', PrivacyRules::personalKeys(), true));
expectTrue('owner sees personal', PrivacyRules::canViewPersonal(7, 7, 'quote_request', null, false));
expectTrue('admin sees personal', PrivacyRules::canViewPersonal(1, 9, 'quote_request', null, true));
expectFalse('stranger masked before award', PrivacyRules::canViewPersonal(3, 9, 'quote_request', null, false));
expectFalse('bidder masked before award', PrivacyRules::canViewPersonal(4, 9, 'quote_request', 4, false));
expectTrue('awarded bidder sees personal', PrivacyRules::canViewPersonal(4, 9, 'awarded', 4, false));
expectFalse('other bidder still masked after award', PrivacyRules::canViewPersonal(5, 9, 'awarded', 4, false));
expectTrue('archives follow award', PrivacyRules::canViewArchives(4, 9, 'awarded', 4, false));
expectFalse('archives hidden before award', PrivacyRules::canViewArchives(4, 9, 'quote_request', 4, false));

expectTrue('zip allowed archive', UploadRules::isAllowedExtension('archives', 'refs.zip'));
expectTrue('tar.gz allowed', UploadRules::isAllowedExtension('archives', 'model.tar.gz'));
expectFalse('stl not archive', UploadRules::isAllowedExtension('archives', 'a.stl'));
expectTrue('png image', UploadRules::isAllowedExtension('images', 'a.png'));
expectFalse('zip not image', UploadRules::isAllowedExtension('images', 'a.zip'));
expectTrue('STL provided ext', UploadRules::isAllowedProvidedExtension('stl'));

$store = TypeRules::storeRules();
expectTrue('type slug required', in_array('required', $store['slug'], true));
expectTrue('type name required', in_array('required', $store['name'], true));
expectTrue('type includes_modeling optional boolean', isset($store['includes_modeling']) && in_array('boolean', $store['includes_modeling'], true));
expectTrue('provided_extensions not required', in_array('nullable', $create['provided_extensions'], true) && ! in_array('required', $create['provided_extensions'], true));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
