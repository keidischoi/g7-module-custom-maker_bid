<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);

$jsonFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.json')) {
        $jsonFiles[] = $file->getPathname();
    }
}
sort($jsonFiles);

expectTrue('found layout/route json files', $jsonFiles !== []);

foreach ($jsonFiles as $path) {
    $raw = (string) file_get_contents($path);
    $decoded = json_decode($raw, true);
    $rel = substr($path, strlen($root) + 1);
    expectTrue("valid json {$rel}", is_array($decoded) && json_last_error() === JSON_ERROR_NONE);
}

$userRoutes = json_decode((string) file_get_contents($root.'/resources/routes/user.json'), true);
$adminRoutes = json_decode((string) file_get_contents($root.'/resources/routes/admin.json'), true);
$userPaths = array_column($userRoutes['routes'], 'path');
$adminPaths = array_column($adminRoutes['routes'], 'path');

expectTrue('user list route', in_array('*/maker-bids', $userPaths, true));
expectTrue('user create route', in_array('*/maker-bids/new', $userPaths, true));
expectTrue('user bids route', in_array('*/maker-bids/bids', $userPaths, true));
expectTrue('user history route', in_array('*/maker-bids/history', $userPaths, true));
expectTrue('user company route', in_array('*/maker-bids/company', $userPaths, true));
expectTrue('user edit route', in_array('*/maker-bids/:id/edit', $userPaths, true));
expectTrue('user notices route', in_array('*/maker-bids/notices', $userPaths, true));
expectTrue('user disputes route', in_array('*/maker-bids/disputes', $userPaths, true));
expectTrue('user payments route', in_array('*/maker-bids/payments', $userPaths, true));
$layoutsByPath = [];
foreach ($userRoutes['routes'] as $r) {
    $layoutsByPath[$r['path']] = $r['layout'] ?? null;
}
expectTrue('create+edit share jobs_form layout', ($layoutsByPath['*/maker-bids/new'] ?? null) === 'jobs_form' && ($layoutsByPath['*/maker-bids/:id/edit'] ?? null) === 'jobs_form');
expectTrue('notices uses jobs_notices layout', ($layoutsByPath['*/maker-bids/notices'] ?? null) === 'jobs_notices');
expectTrue('disputes uses jobs_disputes layout', ($layoutsByPath['*/maker-bids/disputes'] ?? null) === 'jobs_disputes');
expectTrue('payments uses jobs_payments layout', ($layoutsByPath['*/maker-bids/payments'] ?? null) === 'jobs_payments');
expectTrue('user detail route', in_array('*/maker-bids/:id', $userPaths, true));
expectTrue('admin types route', in_array('*/admin/maker-bids/types', $adminPaths, true));
expectTrue('create requires auth', $userRoutes['routes'][1]['auth_required'] === true);
expectTrue('company requires auth', in_array(true, array_map(
    static fn (array $r): bool => $r['path'] === '*/maker-bids/company' && ($r['auth_required'] ?? false) === true,
    $userRoutes['routes'],
), true));

expectTrue('admin jobs route', in_array('*/admin/maker-bids', $adminPaths, true));
expectTrue('admin job detail route', in_array('*/admin/maker-bids/jobs/:id', $adminPaths, true));
expectTrue('admin bids route', in_array('*/admin/maker-bids/bids', $adminPaths, true));
expectTrue('admin companies route', in_array('*/admin/maker-bids/companies', $adminPaths, true));
expectTrue('admin activity route', in_array('*/admin/maker-bids/activity', $adminPaths, true));
expectTrue('admin settings route', in_array('*/admin/maker-bids/settings', $adminPaths, true));
expectTrue('admin bid detail route', in_array('*/admin/maker-bids/bids/:id', $adminPaths, true));

$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
$pageJs = (string) file_get_contents($root.'/resources/assets/page.js');
$adminJobsShowSpec = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('detail has award action', str_contains($show, '/jobs/{{route.id}}/award'));
expectTrue('detail has bid create', str_contains($pageJs, "/jobs/' + encodeURIComponent(jobId) + '/bids") || str_contains($show, '/jobs/{{route.id}}/bids'));
expectTrue('detail has bid patch', str_contains($pageJs, 'updateDetailBid') || str_contains($show, '/bids/{{viewer.data.my_bid.id}}'));
expectTrue('detail mutate uses auth_mode optional (no auth_required gate)', str_contains($show, '"auth_mode": "optional"') && !str_contains($show, '"auth_required": true'));
expectTrue('detail job fetch uses optional auth_mode', str_contains($show, '"auth_mode": "optional"') && preg_match('/"id": "job"[\s\S]*?"auth_mode": "optional"[\s\S]*?"id": "viewer"/', $show) === 1);
expectTrue('detail toasts errors', str_contains($show, '"handler": "toast"') && str_contains($show, '{{error.message}}'));
expectTrue('detail empty bids copy', str_contains($show, '아직 들어온 견적이 없습니다.'));
expectTrue('detail edit gated by can_edit', str_contains($show, 'can_edit'));
expectTrue('detail has spec_card', str_contains($show, 'spec_card') && str_contains($show, '의뢰 명세') && str_contains($show, 'data-cmb-job-spec'));
expectTrue('detail description exists only in full spec', ! str_contains($show, '"id": "desc_card"') && str_contains($pageJs, "{ label: '설명'"));
expectTrue('detail spec renders empty fields', str_contains($pageJs, "var NONE = '해당없음'") && str_contains($pageJs, 'CATALOG.forEach'));
expectTrue('detail spec uses job type catalog flags', str_contains($pageJs, 'type_includes_modeling') && str_contains($pageJs, 'type_requires_address'));
expectTrue('admin detail uses same full spec renderer', str_contains($adminJobsShowSpec, 'data-cmb-job-spec') && str_contains($adminJobsShowSpec, '의뢰 명세'));
expectTrue('detail gallery sits beside spec', str_contains($show, 'cmb-job-spec-body') && str_contains($show, 'data-cmb-job-gallery'));
expectTrue('detail gallery filters images and badges extras', str_contains($pageJs, 'function isImageFile') && str_contains($pageJs, "'+' + (images.length - 1)"));
expectTrue('detail gallery opens sliding carousel', str_contains($pageJs, 'function openLightbox') && str_contains($pageJs, 'translate3d'));
expectTrue('detail amounts use thousand separators', str_contains($pageJs, "toLocaleString('ko-KR')") && str_contains($pageJs, 'window.CMB.formatMoney'));
expectTrue('bid amount_label used on detail', str_contains($show, 'amount_label'));
expectTrue('detail privacy blocked copy', str_contains($show, '낙찰') && (str_contains($show, '개인정보') || str_contains($show, '연락처')));
expectTrue('detail rush deadline label', str_contains($pageJs, 'rush_deadline_label'));
expectTrue('detail spec fetches job if state missing', str_contains($pageJs, 'fetchJobIfNeeded') && str_contains($pageJs, 'jobFetchUrl'));
expectTrue('detail spec walks full G7 state', str_contains($pageJs, 'jobFromFullState') && str_contains($pageJs, 'walkStateForJob'));
expectTrue('detail spec polls until ready', str_contains($pageJs, 'ensurePolling') && str_contains($pageJs, 'data-cmb-job-spec-ready'));
expectTrue('detail spec falls back without catalog', str_contains($pageJs, 'renderFallback') && str_contains($pageJs, 'FALLBACK_KEYS'));
expectTrue('detail manager info stays privacy-gated', str_contains($pageJs, "key: 'manager_name', private: true") && str_contains($pageJs, "var PRIVATE = '비공개'"));
expectTrue('detail spec requires route job id', str_contains($pageJs, 'job.id == null || String(job.id) !== id') && str_contains($pageJs, 'normalizeJob'));
expectTrue('detail spec maps audience label', str_contains($pageJs, "label: '입찰 권한'") && str_contains($pageJs, 'function audienceLabel'));
expectTrue('detail spec owner sees privacy', str_contains($pageJs, 'viewer.is_owner') && str_contains($pageJs, 'can_view_privacy'));
expectTrue('detail refund account in spec catalog', str_contains($pageJs, "key: 'refund_account_holder', private: true") && str_contains($pageJs, "key: 'refund_account_no', private: true"));


$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
$form = $create;
expectTrue('create posts jobs', str_contains($create, '"target": "/api/modules/custom-maker_bids/jobs"'));
expectTrue('create navigate uses job id fallback', str_contains($create, '/maker-bids/{{response.data.id || response.id}}'));
expectTrue('create has auth_required', str_contains($create, '"auth_required": true'));
expectTrue('create toasts errors', str_contains($create, 'error.message'));
expectTrue('create is order card', str_contains($create, '제작 주문서'));
expectTrue('create card uses theme surface', str_contains($create, 'cmb-order-card') && str_contains($create, 'dark:bg-gray-800'));
expectTrue('create fields use theme tokens', str_contains($create, 'cmb-order-field') && str_contains($create, 'dark:bg-gray-900'));
expectTrue('create does not use zinc paper card', ! str_contains($create, 'zinc-900') && ! str_contains($create, 'zinc-950'));
expectTrue('create FileUploader themed', str_contains($create, 'cmb-order-uploader'));
expectTrue('create FileUploader images', str_contains($create, 'cmb_images_uploader'));
expectTrue('create FileUploader archives', str_contains($create, 'cmb_archives_uploader'));
expectTrue('create uploadTriggerEvent images', str_contains($create, 'upload:maker_bids_images'));
expectTrue('create uploadTriggerEvent archives', str_contains($create, 'upload:maker_bids_archives'));
expectTrue('create apiEndpoints.upload', str_contains($create, '"upload": "/api/modules/custom-maker_bids/uploads"'));
expectTrue('create does not stash PendingFile.file', ! str_contains($create, 'PendingFile') && ! preg_match('/\$event\.target\.files|PendingFile\.file/', $create));
expectTrue('create archive accept zip', str_contains($create, '.zip,.tar,.gz'));
expectTrue('create daum postcode button', str_contains($create, 'data-cmb-postcode'));
expectTrue('create budget range', str_contains($create, 'budget_min') && str_contains($create, 'budget_max'));
expectTrue('create rush checkbox', str_contains($create, 'rush_fee_enabled'));
expectTrue('create rush calendar under checkbox', str_contains($create, '"type": "datetime-local"') && str_contains($create, '적용 조건 시각') && str_contains($create, 'rush_date_wrap') && str_contains($create, 'cmb-cond-rush'));
expectTrue('create rush calendar nested in rush_row', preg_match('/"id": "rush_row"[\s\S]*"id": "rush_date_wrap"[\s\S]*"id": "premium_row"/', $create) === 1);
expectTrue('create rush calendar not gated only by if', ! str_contains($create, '"id": "rush_date_wrap"') || ! preg_match('/"id": "rush_date_wrap"[\s\S]{0,200}"if": "{{_local.form.rush_fee_enabled}}"/', $create));
expectTrue('create Select is value-controlled', str_contains($create, '"value": "{{_local.form.type}}"') && str_contains($create, '"value": "{{_local.form.status}}"') && str_contains($create, 'form.type') && str_contains($create, 'cmb-order-select'));
expectTrue('create Select options use length fallback', str_contains($create, 'defaults.data?.types?.length') || str_contains($create, 'types.data?.length'));
expectTrue('create extension checkbox labels are explicit text', str_contains($create, '"text": "STL"') && str_contains($create, '"text": "3MF"') && str_contains($create, '"text": "GCODE"') && str_contains($create, '"text": "FBX"') && str_contains($create, '"text": "DWG"'));
expectTrue('create checkbox labels use contrast classes', str_contains($create, 'cmb-order-check-label') && str_contains($create, 'cmb-order-check-text') && str_contains($create, 'dark:text-gray-100'));
expectTrue('create rush/premium/revision checkbox labels', str_contains($create, '"text": "적용 가능"') && str_contains($create, '"text": "적용 유무"'));
expectTrue('create daytime helper 주간만', str_contains($create, '주간만') && str_contains($create, 'data-cmb-daytime'));
expectTrue('create revision min count and cost per revision under checkbox', str_contains($create, '최소 횟수') && str_contains($create, '회당 / 최대 수정비용') && preg_match('/"id": "rev_row"[\s\S]*"id": "rev_fields"[\s\S]*"id": "desc_label"/', $create) === 1);
expectTrue('create revision fields not gated only by if', str_contains($create, 'cmb-cond-rev'));
expectTrue('create profile name helper 회원정보 사용', str_contains($create, '회원정보 사용') && str_contains($create, 'data-cmb-profile-name'));
expectTrue('create extensions hidden unless modeling', str_contains($create, 'cmb-cond-ext') && str_contains($create, 'data-cmb-ext') && str_contains($create, 'includes_modeling'));
expectTrue('create size rows add and delete', str_contains($create, 'data-cmb-size-add') && str_contains($create, '"text": "추가"') && str_contains($create, 'data-cmb-sizes-list') && str_contains($create, 'data-cmb-sizes'));
expectTrue('create manager info card', str_contains($create, '담당자 정보') && str_contains($create, '담당자 명') && str_contains($create, '담당자 연락처') && str_contains($create, '담당자 이메일') && str_contains($create, 'cmb-manager-box') && str_contains($create, 'manager_name'));
expectTrue('create title is value-controlled', str_contains($create, '"value": "{{_local.form.title}}"') && str_contains($create, 'form.title'));
expectTrue('create budget/closes are value-controlled', str_contains($create, '"value": "{{_local.form.budget_min}}"') && str_contains($create, '"value": "{{_local.form.closes_at}}"'));
expectTrue('create contact/manager are value-controlled', str_contains($create, '"value": "{{_local.form.contact_name}}"') && str_contains($create, '"value": "{{_local.form.manager_name}}"'));
expectTrue('create rush/revision checkboxes bind local state', str_contains($create, '"checked": "{{_local.form.rush_fee_enabled}}"') && str_contains($create, '"checked": "{{_local.form.revision_enabled}}"'));
expectTrue('create has no QA fill button', ! str_contains($create, 'data-cmb-qa-fill') && ! str_contains($create, '임의입력') && ! str_contains($create, 'cmb-qa-fill'));
expectTrue('create tab is 입찰자 등록', str_contains($create, '입찰자 등록'));
expectTrue('create 소유권한 요청 near extensions', str_contains($create, '소유권한 요청') && str_contains($create, 'ownership_requested') && ! str_contains($create, '저작권 있음'));
expectTrue('create 공개 설정 전체/업체만/개인만', str_contains($create, '공개 설정') && str_contains($create, '"value": "all"') && str_contains($create, '업체만') && str_contains($create, '개인만'));
expectTrue('create status options 보류', str_contains($create, '보류'));
$qrOpt = strpos($create, '"value": "quote_request"');
$holdOpt = strpos($create, '"value": "hold"');
expectTrue('create status lists 견적요청 before 보류', $qrOpt !== false && $holdOpt !== false && $qrOpt < $holdOpt);
expectTrue('create privacy block', str_contains($create, '주문자명 또는 업체명'));
expectTrue('create refund account optional fields', str_contains($create, 'refund_account_holder') && str_contains($create, 'refund_account_no') && str_contains($create, '환불 계좌'));

$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list empty state', str_contains($list, '등록된 의뢰가 없습니다'));
expectTrue('list keeps 의뢰목록 tab', str_contains($list, '의뢰목록'));
expectTrue('list uses catalog types filter', str_contains($list, 'job-types'));
expectTrue('list notes 보류 hidden', str_contains($list, '보류'));
expectTrue('list jobs fetch uses optional auth_mode', str_contains($list, '"auth_mode": "optional"') && preg_match('/"id": "jobs"[\s\S]*?"auth_mode": "optional"/', $list) === 1);
expectTrue('list notice placeholder', str_contains($list, 'data-cmb-notice') && str_contains($list, '"list"'));
expectTrue('list rows read data.data fallback', str_contains($list, 'jobs?.data?.data ?? jobs?.data'));
expectTrue('list omits empty type query', str_contains($list, 'query.type ||'));
expectTrue('history rows read data.data fallback', str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_history.json'), 'jobs?.data?.data ?? jobs?.data'));

$company = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
$css = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('company apply posts companies', str_contains($company, '"target": "/api/modules/custom-maker_bids/companies"'));
expectTrue('company apply auth_required', str_contains($company, '"auth_required": true'));
expectTrue('company apply title is 입찰자 등록', str_contains($company, '입찰자 등록'));
expectTrue('company apply FileUploader logos', str_contains($company, 'FileUploader') && str_contains($company, '"collection": "logos"'));
expectTrue('company apply profile fill helper', str_contains($company, 'data-cmb-profile-fill') && str_contains($company, '회원정보'));
expectTrue('company apply job types host', str_contains($company, 'data-cmb-job-types'));
expectTrue('company apply daum postcode', str_contains($company, 'data-cmb-postcode'));
expectTrue('company apply has no editable is_designated field', ! str_contains($company, '"name": "is_designated"'));
expectTrue('company apply submit is 등록 with marker', str_contains($company, '"text": "등록"') && str_contains($company, 'data-cmb-company-submit'));
expectTrue('company apply submit near sub-nav', str_contains($company, 'data-cmb-subnav') && str_contains($company, 'data-cmb-company-submit'));
expectTrue('company apply submit is not beside the page title', preg_match('/"id": "title_row"[\s\S]*"id": "submit"[\s\S]*"id": "tabs"/', $company) !== 1);
expectTrue('company apply has no bottom submit duplicate', ! str_contains($company, '"id": "submit_bottom"'));
expectTrue('company apply submit posts companies', str_contains($company, '"target": "/api/modules/custom-maker_bids/companies"') && str_contains($company, '"method": "POST"'));
expectTrue('company apply submit requires auth', preg_match('/"id": "submit"[\s\S]*"auth_required": true/', $company) === 1);
expectTrue('company apply css keeps title/nav band helpers', str_contains($css, '.cmb-company-title-row') && str_contains($css, '.cmb-company-nav-row') && str_contains($css, 'flex-direction: row !important'));
expectTrue('company apply 등록 button is not gated by G7 if', preg_match('/"id": "submit"[\s\S]{0,400}"if":/', $company) !== 1);
expectTrue('company apply form is not gated by pending status if', preg_match('/"id": "card"[\s\S]{0,250}"if":/', $company) !== 1);
expectTrue('company apply form has reveal marker', str_contains($company, 'cmb-company-form') && str_contains($company, 'data-cmb-company-form'));
expectTrue('company apply sub-nav is marked for active sync', str_contains($company, 'data-cmb-subnav'));

$adminJobs = (string) file_get_contents($root.'/resources/layouts/admin/jobs_index.json');
expectTrue('admin jobs hold', str_contains($adminJobs, '/hold'));
expectTrue('admin jobs cancel', str_contains($adminJobs, '/cancel'));
expectTrue('admin jobs delete', str_contains($adminJobs, '"method": "DELETE"'));
expectTrue('admin list shows 입찰 권한', str_contains($adminJobs, '입찰 권한') && str_contains($adminJobs, 'audience_label'));

$adminJobsShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('admin job detail patches job', str_contains($adminJobsShow, '"method": "PATCH"') && str_contains($adminJobsShow, '/admin/jobs/{{route.id}}'));
expectTrue('admin job detail has title field', str_contains($adminJobsShow, '"name": "title"'));
expectTrue('admin job detail has status select', str_contains($adminJobsShow, '"value": "hold"') && str_contains($adminJobsShow, '"value": "awarded"') && str_contains($adminJobsShow, '"value": "done"') && str_contains($adminJobsShow, '"value": "disputed"'));
expectTrue('admin job detail has audience', str_contains($adminJobsShow, '"value": "company"') && str_contains($adminJobsShow, 'ownership_requested'));
expectTrue('admin job detail keeps hold/cancel', str_contains($adminJobsShow, '/hold') && str_contains($adminJobsShow, '/cancel'));
expectTrue('admin job detail has dispute action', str_contains($adminJobsShow, '/dispute') && str_contains($adminJobsShow, '분쟁조정'));

$adminBids = (string) file_get_contents($root.'/resources/layouts/admin/bids_index.json');
expectTrue('admin bids filter job_id', str_contains($adminBids, 'job_id'));
expectTrue('admin bids delete', str_contains($adminBids, '/admin/bids/{{$item.id}}'));
expectTrue('admin bids edit link', str_contains($adminBids, '/admin/maker-bids/bids/{{$item.id}}'));
expectTrue('admin bids index patch', str_contains($adminBids, '"method": "PATCH"'));

$adminBidShow = (string) file_get_contents($root.'/resources/layouts/admin/bids_show.json');
expectTrue('admin bid show patches bid', str_contains($adminBidShow, '/admin/bids/{{route.id}}') && str_contains($adminBidShow, '"method": "PATCH"'));
expectTrue('admin bid show amount days message status', str_contains($adminBidShow, '"name": "amount"') && str_contains($adminBidShow, '"name": "days"') && str_contains($adminBidShow, '"name": "message"') && str_contains($adminBidShow, '"value": "pending"'));

$adminCos = (string) file_get_contents($root.'/resources/layouts/admin/companies_index.json');
expectTrue('admin company approve', str_contains($adminCos, '/approve'));
expectTrue('admin company reject', str_contains($adminCos, '/reject'));
expectTrue('admin company hold', str_contains($adminCos, '/hold'));
expectTrue('admin company patch', str_contains($adminCos, '"method": "PATCH"') || str_contains($adminCos, '"method": "patch"'));
expectTrue('admin company recommended/priority', str_contains($adminCos, 'is_recommended') && str_contains($adminCos, 'priority'));
expectTrue('admin company designated toggle', str_contains($adminCos, 'is_designated') && str_contains($adminCos, '지정업체'));
expectTrue('admin company delete', str_contains($adminCos, '/admin/companies/{{$co.id}}'));

$nav = (string) file_get_contents($root.'/src/Listeners/UserMenuListener.php');
expectTrue('nav cache bust 0.10.38', str_contains($nav, 'nav.js?v=0.10.38'));
expectTrue('form.js cache bust 0.10.38', str_contains($nav, 'form.js?v=0.10.38'));
expectTrue('form.css cache bust 0.10.38', str_contains($nav, 'form.css?v=0.10.38'));
expectTrue('admin.css cache bust 0.10.38', str_contains($nav, 'admin.css?v=0.10.38'));
expectTrue('admin.js cache bust 0.10.38', str_contains($nav, 'admin.js?v=0.10.38'));
expectTrue('cmb_maker_nav cache bust 0.10.38', str_contains((string) file_get_contents($root.'/resources/layouts/user/cmb_maker_nav.json'), 'nav.js?v=0.10.38'));
expectTrue('listener injects admin form.css via _admin_base', str_contains($nav, "=== '_admin_base'"));
expectTrue('listener strips extension nav by settings', str_contains($nav, 'maker_bids_user_nav') && str_contains($nav, 'extension_user_base'));

$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
expectTrue('edit patches job', str_contains($edit, '/jobs/{{route.id}}'));
expectTrue('edit navigate uses job id fallback', str_contains($edit, '/maker-bids/{{response.data.id || response.id || route.id}}'));
expectTrue('edit FileUploader present', str_contains($edit, 'FileUploader'));
expectTrue('edit card uses theme surface', str_contains($edit, 'cmb-order-card') && str_contains($edit, 'dark:bg-gray-800'));
expectTrue('edit does not use zinc paper card', ! str_contains($edit, 'zinc-900') && ! str_contains($edit, 'zinc-950'));
expectTrue('edit extension checkbox labels are explicit text', str_contains($edit, '"text": "STL"') && str_contains($edit, 'cmb-order-check-label'));
expectTrue('edit daytime helper 주간만', str_contains($edit, '주간만') && str_contains($edit, 'data-cmb-daytime'));
expectTrue('edit rush calendar is datetime-local', str_contains($edit, '"type": "datetime-local"') && str_contains($edit, '적용 조건 시각') && str_contains($edit, 'cmb-cond-rush'));
expectTrue('edit revision min count and cost per revision', str_contains($edit, '최소 횟수') && str_contains($edit, '회당 / 최대 수정비용') && str_contains($edit, 'cmb-cond-rev'));
expectTrue('edit Select is value-controlled', str_contains($edit, '"value": "{{_local.form.type}}"') && str_contains($edit, 'cmb-order-select'));
expectTrue('edit profile name helper 회원정보 사용', str_contains($edit, '회원정보 사용') && str_contains($edit, 'data-cmb-profile-name'));
expectTrue('edit extensions hidden unless modeling', str_contains($edit, 'cmb-cond-ext') && str_contains($edit, 'data-cmb-ext'));
expectTrue('edit size rows add and delete', str_contains($edit, 'data-cmb-size-add') && str_contains($edit, '"text": "추가"') && str_contains($edit, 'data-cmb-sizes-list'));
expectTrue('edit manager info card', str_contains($edit, '담당자 정보') && str_contains($edit, 'manager_name') && str_contains($edit, 'cmb-manager-box'));
expectTrue('edit has no QA fill button', ! str_contains($edit, 'data-cmb-qa-fill'));
expectTrue('edit 소유권한 요청 near extensions', str_contains($edit, '소유권한 요청') && str_contains($edit, 'ownership_requested') && ! str_contains($edit, '저작권 있음'));
expectTrue('edit DWG extension', str_contains($edit, '"text": "DWG"') && str_contains($edit, 'ext_dwg'));
expectTrue('edit 공개 설정 전체/업체만/개인만', str_contains($edit, '공개 설정') && str_contains($edit, '"value": "all"') && str_contains($edit, '업체만') && str_contains($edit, '개인만'));
expectTrue('settings removed bid_audience_mode', ! str_contains((string) file_get_contents($root.'/resources/layouts/admin/settings_index.json'), 'bid_audience_mode') && ! str_contains((string) file_get_contents($root.'/resources/layouts/admin/settings_index.json'), '기본 입찰 공개 설정'));
expectTrue('settings has provided_extensions', str_contains((string) file_get_contents($root.'/resources/layouts/admin/settings_index.json'), 'provided_extensions') && str_contains((string) file_get_contents($root.'/resources/layouts/admin/settings_index.json'), '제공 확장자'));
expectTrue('form audience has 관리자', str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_form.json'), '"value": "admin"') && str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_form.json'), '관리자'));
expectTrue('detail has privacy_card', str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_show.json'), 'privacy_card') && str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_show.json'), 'data-cmb-job-privacy'));
expectTrue('admin job has image uploader', str_contains((string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json'), 'cmb_admin_images_uploader'));
expectTrue('admin company has logo uploader', str_contains((string) file_get_contents($root.'/resources/layouts/admin/companies_index.json'), 'cmb_admin_logo_uploader'));
expectTrue('jobs form audience section marker', str_contains($edit, 'data-cmb-audience-section'));
$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list search uses query + search input', str_contains($list, 'data-cmb-search-input') && str_contains($list, 'route.query.q'));
expectTrue('list search-fix fetches jobs api', str_contains((string) file_get_contents($root.'/resources/assets/search-fix.js'), '/api/modules/custom-maker_bids/jobs') && str_contains($list, 'jobs'));
expectTrue('page.js soft search', str_contains($pageJs, 'cmb-list-search') && str_contains($pageJs, 'runSearch'));
expectTrue('list sort UI', str_contains($list, 'sort') && str_contains($list, '최신순') && str_contains($list, '등록순') && str_contains($list, '조회순'));
expectTrue('page.js sort soft', str_contains($pageJs, 'cmb-list-sort') && str_contains($pageJs, 'applySort'));
expectTrue('page.js open bid form', str_contains($pageJs, 'cmb-open-bid-form') && str_contains($pageJs, '/bids'));
$bidsLayout = (string) file_get_contents($root.'/resources/layouts/user/jobs_bids.json');
expectTrue('bids inline 견적 form', str_contains($bidsLayout, 'data-cmb-open-bid-panel') && str_contains($bidsLayout, '견적 제출') && str_contains($bidsLayout, 'data-cmb-bid-amount'));



$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('show 소유권한 요청', str_contains($pageJs, "label: '소유권한'") && str_contains($pageJs, 'ownership_requested') && ! str_contains($show, '저작권 있음'));
expectTrue('show audience label', str_contains($show, 'audience_label'));
expectTrue('list audience label', str_contains($list, 'audience_label'));

expectTrue('show gates closed hint on job.is_open', str_contains($show, 'job.data.is_open') && ! str_contains($show, "status === 'open'") && ! str_contains($show, "status !== 'open'"));
expectTrue('show links workspace after award', str_contains($show, '/maker-bids/{{route.id}}/work') && str_contains($show, 'can_workspace'));
expectTrue('list uses card layout', str_contains($list, 'cmb-job-card') && str_contains($list, 'cmb-badge'));
$companies = (string) file_get_contents($root.'/resources/layouts/user/company_list.json');
expectTrue('companies page uses public directory API', str_contains($companies, '/api/modules/custom-maker_bids/companies') && str_contains($companies, 'companies/me'));
expectTrue('companies page card layout', str_contains($companies, 'cmb-company-card'));


$css = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('form.css themes dark card', str_contains($css, 'color-scheme: dark') && str_contains($css, '--cmb-card'));
expectTrue('form.css styles fields and uploader', str_contains($css, '.cmb-order-field') && str_contains($css, '.cmb-order-uploader'));
expectTrue('form.css forces checkbox label contrast', str_contains($css, '--cmb-check-fg') && str_contains($css, '.cmb-order-check-label') && str_contains($css, '.cmb-order-check-text'));
expectTrue('form.css has no QA fill button', ! str_contains($css, '.cmb-qa-fill') && ! str_contains($css, '임의입력'));
expectTrue('form.css has public card tokens', str_contains($css, '.cmb-job-card') && str_contains($css, '.cmb-badge') && str_contains($css, '.cmb-page'));
expectTrue('form.css styles size add/remove', str_contains($css, '.cmb-size-add') && str_contains($css, '.cmb-size-remove'));
expectTrue('form.css styles manager box', str_contains($css, '.cmb-manager-box'));
expectTrue('form.css conditional rush/rev/ext', str_contains($css, '.cmb-cond-rush') && str_contains($css, '.cmb-cond-rev') && str_contains($css, '.cmb-cond-ext') && str_contains($css, 'pointer-events: auto'));
expectTrue('form.css styles company submit on sub-nav', str_contains($css, '.cmb-company-submit-top') && str_contains($css, 'white-space: nowrap') && str_contains($css, '.cmb-company-nav-row'));
expectTrue('form.css can collapse company form until 등록', str_contains($css, '.cmb-company-form.is-collapsed'));

$formJs = (string) file_get_contents($root.'/resources/assets/form.js');
expectTrue('form.js syncAudienceSection always show', str_contains($formJs, 'syncAudienceSection') && ! str_contains($formJs, 'admin_only') && str_contains($formJs, 'syncProvidedExtOptions'));
expectTrue('form.js injects form.css', str_contains($formJs, 'form.css?v=0.10.38'));

$history = (string) file_get_contents($root.'/resources/layouts/user/jobs_history.json');
expectTrue('jobs_history notices section card', str_contains($history, 'notices_card') && str_contains($history, 'cmb-section-card'));
expectTrue('jobs_history jobs section card', str_contains($history, 'jobs_card') && str_contains($history, 'cmb-section-head'));
expectTrue('jobs_history section link to notices', str_contains($history, '/maker-bids/notices'));

$bidsLayout = (string) file_get_contents($root.'/resources/layouts/user/jobs_bids.json');
expectTrue('jobs_bids mine section card', str_contains($bidsLayout, 'mine_card') && str_contains($bidsLayout, 'cmb-section-card'));
expectTrue('jobs_bids open section card', str_contains($bidsLayout, 'open_card'));

$listLayout = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('jobs_list list section card', str_contains($listLayout, 'list_card') && str_contains($listLayout, 'cmb-section-head'));


$formCss = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('form.css section-head', str_contains($formCss, '.cmb-section-head'));
expectTrue('form.css section-link', str_contains($formCss, '.cmb-section-link'));

expectTrue('public list pagers present', str_contains($listLayout, 'data-cmb-pager') && str_contains($listLayout, 'jobs_pager'));
expectTrue('jobs_list sends page/per_page', str_contains($listLayout, '"page"') && str_contains($listLayout, 'per_page'));
$bidsLayoutPager = (string) file_get_contents($root.'/resources/layouts/user/jobs_bids.json');
expectTrue('jobs_bids dual pagers', str_contains($bidsLayoutPager, 'mine_pager') && str_contains($bidsLayoutPager, 'open_pager') && str_contains($bidsLayoutPager, 'mine_page'));
$historyPager = (string) file_get_contents($root.'/resources/layouts/user/jobs_history.json');
expectTrue('jobs_history dual pagers', str_contains($historyPager, 'hist_jobs_pager') && str_contains($historyPager, 'hist_notices_pager') && str_contains($historyPager, 'notices_page'));
$companiesPager = (string) file_get_contents($root.'/resources/layouts/user/company_list.json');
expectTrue('company_list pager', str_contains($companiesPager, 'companies_pager') && str_contains($companiesPager, 'data-cmb-pager'));
$noticesPager = (string) file_get_contents($root.'/resources/layouts/user/jobs_notices.json');
expectTrue('jobs_notices pager', str_contains($noticesPager, 'notices_pager'));
expectTrue('form.css pager styles', str_contains($formCss, '.cmb-pager') && str_contains($formCss, 'justify-content: flex-end'));
expectTrue('subnav right aligned in css', str_contains($formCss, '.cmb-maker-subnav') && str_contains($formCss, 'justify-content: flex-end !important'));
$pageJs = (string) file_get_contents($root.'/resources/assets/page.js');
expectTrue('page.js renders cmb-pager', str_contains($pageJs, 'data-cmb-pager') && str_contains($pageJs, 'cmb-pager-btn'));
expectTrue('page.js ensures list card classes', str_contains($pageJs, 'cmb-list-item') && str_contains($pageJs, 'ensureFormCss') && str_contains($pageJs, 'form.css?v=0.10.38'));
$navJs = (string) file_get_contents($root.'/resources/assets/nav.js');
expectTrue('nav.js soft in-module navigation', str_contains($navJs, 'function softGo') && str_contains($navJs, 'function bindSoftNav'));
expectTrue('cmb_maker_nav layout async false', str_contains((string) file_get_contents($root.'/resources/layouts/user/cmb_maker_nav.json'), '"async": false'));
expectTrue('nav.js boot CSS id', str_contains($navJs, 'cmb-boot-css') && str_contains($navJs, 'cmb-dark-boot'));
expectTrue('nav.js soft-nav for maker_bids', str_contains($navJs, 'data-cmb-soft-nav') && str_contains($navJs, 'startViewTransition') && str_contains($navJs, "handler: 'navigate'"));
expectTrue('form.css critical dark html/body', str_contains((string) file_get_contents($root.'/resources/assets/form.css'), 'html.cmb-dark-boot') && str_contains((string) file_get_contents($root.'/resources/assets/form.css'), 'color-scheme: dark'));
expectTrue('listener loads nav.js sync for FOUC boot', str_contains($nav, "cmb_maker_nav") && str_contains($nav, "\$id !== 'cmb_maker_nav'") && str_contains($nav, "'async' => \$async"));

expectTrue('form.css dark list cards use rgba panels', str_contains($formCss, 'rgba(255, 255, 255, 0.06)') && str_contains($formCss, 'rgba(255, 255, 255, 0.12)'));
expectTrue('form.css dark nested item cards stronger', str_contains($formCss, 'rgba(255, 255, 255, 0.08)') && str_contains($formCss, '.cmb-list-item'));
expectTrue('jobs_list items carry cmb-list-item', str_contains($listLayout, 'cmb-list-item'));
expectTrue('jobs_list has status chips', str_contains($listLayout, 'data-cmb-status-chips') && str_contains($listLayout, 'quote_request') && str_contains($listLayout, '승인대기'));
expectTrue('jobs_list job thumb', str_contains($listLayout, 'cmb_job_thumb') && str_contains($listLayout, 'cmb-list-thumb') && str_contains($listLayout, 'thumbnail_url'));
$fixListener = (string) file_get_contents($root.'/src/Listeners/LayoutFileFixListener.php');
expectTrue('list listener does not force 10-col grid', ! str_contains($fixListener, 'repeat(10') && str_contains($fixListener, 'cmb-company-gallery'));
expectTrue('list listener company_list only gallery', str_contains($fixListener, "layoutName === 'company_list'"));
$listGrid = (string) file_get_contents($root.'/resources/assets/list-grid.css');
expectTrue('list-grid job cards 3-col at xl', str_contains($listGrid, ':not(.cmb-company-gallery)') && str_contains($listGrid, 'repeat(3, minmax(0, 1fr))'));
expectTrue('list-grid company gallery 5-col at lg', str_contains($listGrid, '.cmb-company-gallery') && str_contains($listGrid, 'repeat(5, minmax(0, 1fr))'));
$searchFix = (string) file_get_contents($root.'/resources/assets/search-fix.js');
expectTrue('search-fix fetches filtered jobs', str_contains($searchFix, '/api/modules/custom-maker_bids/jobs') && str_contains($searchFix, 'per_page') && str_contains($searchFix, 'quote_request'));
expectTrue('search-fix does not clobber focused input', str_contains($searchFix, 'document.activeElement !== free') && str_contains($searchFix, 'data-cmb-free'));
expectTrue('search-fix uses native search bar', str_contains($searchFix, 'data-cmb-native-bar') && str_contains($searchFix, 'stopPropagation'));
expectTrue('search-fix native host sits outside G7 bar', str_contains($searchFix, 'data-cmb-native-host') && str_contains($searchFix, 'neutralizeG7'));
expectTrue('search-fix keeps typed query across remount', str_contains($searchFix, 'typedQ') && str_contains($searchFix, 'rememberTyped'));
expectTrue('search-fix hides G7 search with display none', str_contains($searchFix, "setProperty('display', 'none', 'important')"));
expectTrue('search-fix searches only on Enter key', str_contains($searchFix, "e.key !== 'Enter'") && ! str_contains($searchFix, 'debounce') && ! str_contains($searchFix, "addEventListener('input'") && ! str_contains($searchFix, "addEventListener('keyup'") && ! str_contains($searchFix, "addEventListener('keypress'"));
expectTrue('search-fix self chips use inline display', str_contains($searchFix, 'paintSelfChips') && str_contains($searchFix, "setProperty('display', 'inline-flex', 'important')"));
expectTrue('page.js skips native search wiring', str_contains($pageJs, 'data-cmb-native-host') && str_contains($pageJs, 'data-cmb-native-bar'));
expectTrue('jobs_list has self-only draft and dispute chips', str_contains($listLayout, '임시저장') && str_contains($listLayout, '분쟁조정') && str_contains($listLayout, 'data-cmb-self-status'));
expectTrue('jobs_list has self-only hold draft and dispute chips', str_contains($listLayout, '임시저장') && str_contains($listLayout, '분쟁조정') && str_contains($listLayout, 'data-cmb-self-status": "hold"') && str_contains($listLayout, 'data-cmb-self-status'));
expectTrue('search-fix self chips include hold', str_contains($searchFix, "['hold', '보류']") && str_contains($searchFix, "['draft', '임시저장']") && str_contains($searchFix, "['disputed', '분쟁조정']"));
expectTrue('search-fix sends G7 bearer token', str_contains($searchFix, 'auth_token') && str_contains($searchFix, 'Authorization') && str_contains($searchFix, 'credentials: \'include\''));
expectTrue('search-fix shows hold chip for admin context', str_contains($searchFix, 'viewerLooksAdmin') && str_contains($searchFix, "getAuthType() === 'admin'") && str_contains($searchFix, 'adminChipFlags') && str_contains($searchFix, 'probeAdminAuth'));
expectTrue('search-fix list path allows locale prefix', str_contains($searchFix, 'maker-bids$'));
expectTrue('jobs_list hold chip if uses viewer meta', str_contains($listLayout, 'viewer_status_chips?.hold') && str_contains($listLayout, 'viewer?.is_admin') && ! str_contains($listLayout, '"hidden": true'));
expectTrue('list-grid reveals self chips when not hidden', str_contains($listGrid, 'cmb-chip-self-only:not([hidden])') && str_contains($listGrid, 'inline-flex !important'));
expectTrue('jobs_list stacks 형식 then 의뢰상태 filters', str_contains($listLayout, 'cmb-filter-row-type') && str_contains($listLayout, 'cmb-filter-row-status') && str_contains($listLayout, '"text": "형식"') && str_contains($listLayout, '"text": "의뢰상태"'));
expectTrue('jobs_list type chips have data-cmb-type', str_contains($listLayout, 'data-cmb-type'));
expectTrue('nav skips list filter chips', str_contains((string) file_get_contents($root.'/resources/assets/nav.js'), 'cmb-chip') && str_contains((string) file_get_contents($root.'/resources/assets/nav.js'), 'data-cmb-type'));
expectTrue('listener keeps bids page quote toggle', str_contains($fixListener, "layoutName !== 'jobs_bids'"));
expectTrue('list-grid open-bid cards stay column', str_contains($listGrid, '.cmb-open-bid-card') && str_contains($listGrid, 'flex-direction: column'));
expectTrue('company_list gallery class', str_contains($companiesPager, 'cmb-company-gallery') && str_contains($companiesPager, 'cmb_co_logo'));
expectTrue('company_list me logo', str_contains($companiesPager, 'cmb_me_logo'));
expectTrue('viewer has no debug_session', ! str_contains((string) file_get_contents($root.'/src/Http/Controllers/JobController.php'), 'debug_session'));


expectTrue('form.js company submit hides only when approved', str_contains($formJs, 'function syncCompanySubmit') && str_contains($formJs, "st === 'approved'") && str_contains($formJs, "querySelector('[data-cmb-company-submit]')"));
expectTrue('form.js 등록 reveals company form', str_contains($formJs, 'function revealCompanyForm') && str_contains($formJs, 'function bindCompanySubmit') && str_contains($formJs, 'data-cmb-form-revealed'));
expectTrue('form.js pending collapses form until 등록 click', str_contains($formJs, "st === 'pending'") && str_contains($formJs, 'is-collapsed'));
expectTrue('form.css locks approved company submit', str_contains($css, '.cmb-company-submit-top.is-locked') && str_contains($css, '.cmb-company-title-row'));
expectTrue('form.js daytime helper 09:00-17:00', str_contains($formJs, '09:00') && str_contains($formJs, '17:00') && str_contains($formJs, 'data-cmb-daytime'));
expectTrue('form.js QA fill removed', ! str_contains($formJs, 'fillQaDummy') && ! str_contains($formJs, 'bindQaFill') && ! str_contains($formJs, 'data-cmb-qa-fill'));
expectTrue('form.js select helpers remain', str_contains($formJs, 'catalogTypes') && str_contains($formJs, 'setG7Select') && str_contains($formJs, 'paintSelectTrigger') && str_contains($formJs, 'openSelectMenu'));
expectTrue('form.js keeps ext helpers', str_contains($formJs, 'ext_dwg'));
expectTrue('form.js does not emit upload events', ! str_contains($formJs, 'upload:maker_bids'));
expectTrue('form.js size rows add/delete max 20', str_contains($formJs, 'SIZE_MAX = 20') && str_contains($formJs, 'data-cmb-size-add') && str_contains($formJs, 'data-cmb-size-remove') && str_contains($formJs, '>삭제</button>'));
expectTrue('form.js profile/manager name helpers', str_contains($formJs, 'manager_name'));
expectTrue('form.js profile name helper', str_contains($formJs, 'data-cmb-profile-name') && str_contains($formJs, 'bindProfileName'));
expectTrue('form.js toggles modeling extensions', str_contains($formJs, 'includes_modeling') && str_contains($formJs, 'syncExtVisibility') && str_contains($formJs, 'cmb-cond-ext'));
expectTrue('form.js cond toggles rush/revision', str_contains($formJs, 'bindCondToggles') && str_contains($formJs, 'cmb-cond-rush'));
expectTrue('rush deadline ensure migration', is_file($root.'/database/migrations/2026_09_16_000006_ensure_rush_deadline.php'));
expectTrue('revision fields ensure migration', is_file($root.'/database/migrations/2026_09_16_000007_ensure_revision_fields.php'));
expectTrue('sizes and manager ensure migration', is_file($root.'/database/migrations/2026_09_16_000008_ensure_sizes_and_manager_fields.php'));
expectTrue('job refund account ensure migration', is_file($root.'/database/migrations/2026_09_18_000023_ensure_job_refund_account.php'));
expectTrue('includes_modeling ensure migration', is_file($root.'/database/migrations/2026_09_16_000009_ensure_includes_modeling.php'));
expectTrue('company profile admin fields migration', is_file($root.'/database/migrations/2026_09_16_000010_ensure_company_profile_admin_fields.php'));
expectTrue('job view_count migration', is_file($root.'/database/migrations/2026_09_17_000017_ensure_job_view_count.php'));
expectTrue('job audience and ownership migration', is_file($root.'/database/migrations/2026_09_16_000011_ensure_job_audience_and_copyright.php'));
expectTrue('module settings migration', is_file($root.'/database/migrations/2026_09_16_000012_create_maker_module_settings_table.php'));
expectTrue('company is_designated migration', is_file($root.'/database/migrations/2026_09_16_000013_ensure_company_is_designated.php'));
expectTrue('settings defaults json', is_file($root.'/config/settings/defaults.json'));

$fileModel = (string) file_get_contents($root.'/src/Models/MakerJobFile.php');
expectTrue('uploader payload wraps attachment data', str_contains($fileModel, 'function toUploaderPayload') && str_contains($fileModel, "'success' => true") && str_contains($fileModel, "'data' => \$att"));
expectTrue('attachment has hash download_url is_image', str_contains($fileModel, "'download_url'") && str_contains($fileModel, "'is_image'") && str_contains($fileModel, "'hash'"));
$uploadCtrl = (string) file_get_contents($root.'/src/Http/Controllers/JobFileController.php');
expectTrue('upload responds HTTP 200 wrapped payload', str_contains($uploadCtrl, 'toUploaderPayload()') && str_contains($uploadCtrl, ', 200)'));

$asset = (string) file_get_contents($root.'/src/Http/Controllers/AssetController.php');
expectTrue('asset controller serves form.css', str_contains($asset, 'formCss') && str_contains($asset, 'form.css'));
expectTrue('asset controller serves admin.css', str_contains($asset, 'adminCss') && str_contains($asset, 'admin.css'));
expectTrue('asset controller serves admin.js', str_contains($asset, 'adminJs') && str_contains($asset, 'admin.js'));

$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('form.css route', str_contains($api, "assets/form.css"));
expectTrue('admin.css route', str_contains($api, "assets/admin.css"));
expectTrue('admin.js route', str_contains($api, "assets/admin.js"));

$adminTypes = (string) file_get_contents($root.'/resources/layouts/admin/types_index.json');
expectTrue('admin types create', str_contains($adminTypes, '/admin/job-types'));
expectTrue('admin types move', str_contains($adminTypes, '/move'));
expectTrue('admin types delete', str_contains($adminTypes, '"method": "DELETE"'));
expectTrue('admin types includes_modeling checkbox', str_contains($adminTypes, 'includes_modeling') && str_contains($adminTypes, '모델링 포함 (제공 확장자)'));

$adminSettings = (string) file_get_contents($root.'/resources/layouts/admin/settings_index.json');
expectTrue('admin settings put', str_contains($adminSettings, '/admin/settings') && str_contains($adminSettings, '"method": "PUT"'));
expectTrue('admin settings menu fields', str_contains($adminSettings, 'nav_js_enabled') && str_contains($adminSettings, 'nav_insert') && str_contains($adminSettings, 'extension_user_base'));
expectTrue('admin settings notices', str_contains($adminSettings, 'list_body') && str_contains($adminSettings, 'create_body') && str_contains($adminSettings, '의뢰목록 안내문'));
expectTrue('admin settings general', str_contains($adminSettings, 'default_job_status') && str_contains($adminSettings, 'guests_see_list'));
expectTrue('admin settings notify toggles', str_contains($adminSettings, 'notify_email') && str_contains($adminSettings, 'notify_system') && str_contains($adminSettings, '사이트 알림'));
expectTrue('admin settings bid_allow', str_contains($adminSettings, 'bid_allow') && str_contains($adminSettings, '지정업체') && str_contains($adminSettings, '"value": "all"') && str_contains($adminSettings, '모든 등록된 업체') && str_contains($adminSettings, '등록된 개인회원') && str_contains($adminSettings, '일반회원') && str_contains($adminSettings, 'approved_bidders'));
expectTrue('admin settings bid_allow drops old short list', ! str_contains($adminSettings, '"value": "members"') && ! str_contains($adminSettings, '"value": "company"') && ! str_contains($adminSettings, '"value": "individual"'));
expectTrue('admin settings permission', str_contains($adminSettings, 'custom-maker_bids.settings.read'));

$navJs = (string) file_get_contents($root.'/resources/assets/nav.js');
expectTrue('nav.js fetches public settings', str_contains($navJs, '/api/modules/custom-maker_bids/settings'));
expectTrue('nav.js applies notice html', str_contains($navJs, 'data-cmb-notice') && str_contains($navJs, 'innerHTML'));
expectTrue('nav.js insert positions', str_contains($navJs, 'after_shop') && str_contains($navJs, 'after_home') && str_contains($navJs, 'prepend_row'));
expectTrue('nav.js syncs header active to current route', str_contains($navJs, 'function syncHeaderNav') && str_contains($navJs, 'cmb-nav-current') && str_contains($navJs, 'pushState'));
expectTrue('nav.js syncs sub-nav pills to current route', str_contains($navJs, 'function syncSubNav') && str_contains($navJs, 'cmb-tab-current') && str_contains($navJs, 'data-cmb-subnav'));
expectTrue('nav.js clears sticky focus chrome', str_contains($navJs, ':not(.cmb-nav-current):focus') && str_contains($navJs, ':not(.cmb-tab-current):focus'));
expectTrue('list/create/history sub-nav marked for sync', str_contains($list, 'data-cmb-subnav') && str_contains($create, 'data-cmb-subnav') && str_contains((string) file_get_contents($root.'/resources/layouts/user/jobs_history.json'), 'data-cmb-subnav'));

$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
expectTrue('create notice placeholder', str_contains($create, 'data-cmb-notice') && str_contains($create, '"create"'));
$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('show notice placeholder', str_contains($show, 'data-cmb-notice') && str_contains($show, '"show"'));
$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
expectTrue('edit notice placeholder', str_contains($edit, 'data-cmb-notice') && str_contains($edit, '"edit"'));
expectTrue('optional sanctum list still present', str_contains((string) file_get_contents($root.'/src/routes/api.php'), 'optional.sanctum'));

expectTrue('admin jobs list uses cmb-admin polish', str_contains($adminJobs, 'cmb-admin') && str_contains($adminJobs, 'cmb-admin-row-actions') && str_contains($adminJobs, 'cmb-admin-row w-full'));
expectTrue('admin jobs filter has purpose-fit fields', str_contains($adminJobs, 'cmb-admin-filter-type') && str_contains($adminJobs, 'cmb-admin-filter-status') && str_contains($adminJobs, 'cmb-admin-filter-go'));
expectTrue('admin jobs list is full width', str_contains($adminJobs, 'cmb-admin-list w-full') && str_contains($adminJobs, 'cmb-admin space-y-5 w-full'));
expectTrue('admin types wrap checkbox labels', str_contains($adminTypes, 'cmb-admin-check-label') && str_contains($adminTypes, 'cmb-admin-check-text') && str_contains($adminTypes, '"text": "주소 필수"') && ! str_contains($adminTypes, '"label": "주소 필수"'));
expectTrue('admin types list is full width', str_contains($adminTypes, 'cmb-admin-list w-full') && str_contains($adminTypes, 'cmb-admin-row w-full'));
expectTrue('admin companies use split card layout', str_contains($adminCos, 'cmb-admin-split') && str_contains($adminCos, 'cmb-admin-card') && str_contains($adminCos, 'cmb-admin-row-actions'));
expectTrue('admin companies list is full width', str_contains($adminCos, 'cmb-admin-list w-full') && str_contains($adminCos, 'cmb-admin-row w-full') && str_contains($adminCos, 'cmb-admin-filter-status'));
expectTrue('admin jobs show sized inputs and visible ext labels', str_contains($adminJobsShow, 'cmb-admin-w-xs') && str_contains($adminJobsShow, 'cmb-admin-check-text') && str_contains($adminJobsShow, '"text": "STL"'));
expectTrue('admin form.css has field widths and check contrast', str_contains($css, '.cmb-admin-w-xs') && str_contains($css, '.cmb-admin-row-actions') && str_contains($css, '.cmb-admin-check-text'));
expectTrue('admin form.css Select fallback keeps nowrap', str_contains($css, '.cmb-admin-select-host') && str_contains($css, '.cmb-admin-listbox'));
expectTrue('admin form.css does not cap page width', str_contains($css, 'max-width: none') && ! str_contains($css, 'max-width: 72rem'));
$adminCss = (string) file_get_contents($root.'/resources/assets/admin.css');
expectTrue('admin.css stretches list rows', str_contains($adminCss, '.cmb-admin-row') && str_contains($adminCss, 'width: 100% !important') && str_contains($adminCss, 'max-width: none !important'));
expectTrue('admin.css sizes filter type and status', str_contains($adminCss, '.cmb-admin-filter-type') && str_contains($adminCss, '.cmb-admin-filter-status') && str_contains($adminCss, '.cmb-admin-filter-go'));
expectTrue('admin.css keeps filter status purpose-fit', str_contains($adminCss, '.cmb-admin-filter-status') && str_contains($adminCss, 'width: 12rem'));
expectTrue('admin.css sizes G7 Select via host not body:has', str_contains($adminCss, '.cmb-admin-select-host') && str_contains($adminCss, '.cmb-admin-select-host-lg') && str_contains($adminCss, '[role="combobox"]') && str_contains($adminCss, 'white-space: nowrap') && str_contains($adminCss, 'width: max-content') && str_contains($adminCss, 'html.cmb-admin-ui [role="listbox"]') && ! str_contains($adminCss, 'body:has(.cmb-admin-select') && ! str_contains($adminCss, '--radix-select-trigger-width'));
expectTrue('admin.css marks portaled menus with cmb-admin-listbox', str_contains($adminCss, '.cmb-admin-listbox') && str_contains($adminCss, 'word-break: keep-all') && str_contains($adminCss, '[role="option"]'));
expectTrue('admin settings Selects sit in host wrappers', str_contains($adminSettings, 'cmb-admin-select-host cmb-admin-select-host-md') && str_contains($adminSettings, 'cmb-admin-select-host cmb-admin-select-host-lg') && str_contains($adminSettings, '보류 (비공개)'));
expectTrue('admin list/detail Selects use cmb-admin-select-host', str_contains($adminJobsShow, 'cmb-admin-select-host cmb-admin-select-host-md') && str_contains($adminCos, 'cmb-admin-select-host cmb-admin-select-host-sm'));
expectTrue('admin.css dark row border is high contrast', str_contains($adminCss, 'rgba(255, 255, 255, 0.06)') && (str_contains($adminCss, 'rgba(255, 255, 255, 0.14)') || str_contains($adminCss, 'rgba(255, 255, 255, 0.12)') || str_contains($adminCss, 'rgba(255, 255, 255, 0.16)')));
expectTrue('admin.css dark covers data-theme and cmb-admin-dark', str_contains($adminCss, 'html[data-theme="dark"]') && str_contains($adminCss, 'html.cmb-admin-dark') && str_contains($adminCss, 'html.cmb-dark-boot'));
expectTrue('admin.css dark chrome border is translucent', str_contains($adminCss, '--cmb-admin-border: rgba(255, 255, 255, 0.12)') || str_contains($adminCss, '--cmb-admin-border: rgb(148 163 184)') || str_contains($adminCss, '--cmb-admin-border: rgb(107 114 128)'));
expectTrue('listener injects admin.css via _admin_base', str_contains($nav, 'cmb_maker_admin_css') && str_contains($nav, 'admin.css?v=0.10.38'));
expectTrue('listener injects admin.js via _admin_base', str_contains($nav, 'cmb_maker_admin_js') && str_contains($nav, 'admin.js?v=0.10.38'));
$adminJs = (string) file_get_contents($root.'/resources/assets/admin.js');
expectTrue('admin.js injects portal CSS on html.cmb-admin-ui', str_contains($adminJs, 'injectPortalCss') && str_contains($adminJs, 'cmb-admin-select-portal-css') && str_contains($adminJs, 'html.cmb-admin-ui'));
expectTrue('admin.js unlocks Radix pointer lock', str_contains($adminJs, 'function unlockAdminPointer') && str_contains($adminJs, 'data-scroll-locked') && str_contains($adminJs, 'pointer-events'));
expectTrue('admin.js native type+status filters', str_contains($adminJs, "cmb_filter_") && str_contains($adminJs, "['disputed', '분쟁조정']") && str_contains($adminJs, 'data-cmb-filter-key'));
expectTrue('admin.js binds list row actions', str_contains($adminJs, 'function bindRowActions') && str_contains($adminJs, '/admin/jobs/'));
expectTrue('admin.js row actions send bearer token', str_contains($adminJs, 'function readAuthToken') && str_contains($adminJs, "headers.Authorization = 'Bearer '") && str_contains($adminJs, "credentials: 'include'"));
expectTrue('admin.js binds detail toolbar and job save', str_contains($adminJs, 'cmb-admin-toolbar') && str_contains($adminJs, '의뢰 저장') && str_contains($adminJs, "action = 'dispute'") && str_contains($adminJs, 'function jobIdFromPath'));
expectTrue('admin.js paint ignores non-status kinds', str_contains($adminJs, 'out[k] && out[k].indexOf'));
expectTrue('admin.js native job form selects', str_contains($adminJs, 'function ensureFormNativeSelects') && str_contains($adminJs, 'data-cmb-form-host') && str_contains($adminJs, "['disputed', '분쟁조정']"));
expectTrue('admin.js keeps company edit id', str_contains($adminJs, 'function rememberEditId') && str_contains($adminJs, '__cmbEditCompanyId') && str_contains($adminJs, 'is-cmb-editing'));
expectTrue('admin.js merges company edit snapshot', str_contains($adminJs, 'function patchG7EditMerge') && str_contains($adminJs, 'cmb-edit-company-snap') && str_contains($adminJs, 'function restoreStickyEdit') && str_contains($adminJs, 'logo_files'));
expectTrue('admin.js sticky title is not G7 owned', str_contains($adminJs, 'data-cmb-sticky-title') && str_contains($adminJs, 'function observeEditTitle'));
expectTrue('admin job detail marks form hosts', str_contains($adminJobsShow, 'data-cmb-job-edit') && str_contains($adminJobsShow, 'data-cmb-form-host') && str_contains($adminJobsShow, 'data-cmb-kind": "save'));
expectTrue('admin company edit keeps hidden id', str_contains($adminCos, '"name": "id"') && str_contains($adminCos, 'data-cmb-edit-title') && str_contains($adminCos, 'data-cmb-load') && str_contains($adminCos, '"trackChanges": false'));
expectTrue('listener does not bind job fields onto jobs_index filters', str_contains($nav, "'jobs_show'") && ! str_contains($nav, "['jobs_show', 'jobs_index']"));
expectTrue('admin jobs rows expose status for 승인/보류 paint', str_contains($adminJobs, 'data-cmb-status') && str_contains($adminJobs, 'cmb-status-{{$item.status}}') && str_contains($adminJobs, 'cmb-entity-job') && str_contains($adminJobs, 'data-cmb-kind'));
expectTrue('admin companies rows expose status for 승인/보류 paint', str_contains($adminCos, 'data-cmb-status') && str_contains($adminCos, 'cmb-status-{{$co.status}}') && str_contains($adminCos, 'cmb-entity-company') && str_contains($adminCos, 'data-cmb-kind'));
expectTrue('admin.css current 승인 beats dark outline', str_contains($adminCss, 'html.cmb-admin-dark .cmb-admin .cmb-btn-approve.is-active') && str_contains($adminCss, 'html.cmb-admin-dark .cmb-admin .cmb-status-quote_request .cmb-btn-approve'));
expectTrue('admin.css current 보류 beats dark outline', str_contains($adminCss, 'html.cmb-admin-dark .cmb-admin .cmb-btn-hold.is-active') && str_contains($adminCss, 'html.cmb-admin-dark .cmb-admin .cmb-status-hold .cmb-btn-hold'));
expectTrue('admin.css press effect on 승인/보류', str_contains($adminCss, 'data-cmb-pressed') && str_contains($adminCss, 'scale(0.9)') && str_contains($adminCss, 'is-pressed'));
expectTrue('admin.js infers status from muted not buttons', str_contains($adminJs, 'function statusFromRow') && str_contains($adminJs, 'cmb-admin-muted') && str_contains($adminJs, 'data-cmb-filter-wrap'));
expectTrue('admin.css listbox is dark and visible', str_contains($adminCss, 'background: #0f172a') && str_contains($adminCss, 'color: #f8fafc') && str_contains($adminCss, '-webkit-text-fill-color') && str_contains($adminCss, 'data-scroll-locked'));
expectTrue('admin.css native options are dark-on-light', str_contains($adminCss, 'color: #0f172a') && str_contains($adminCss, 'color-scheme: dark'));
expectTrue('admin.js paints open menus', str_contains($adminJs, 'function paintOpenAdminMenus') && str_contains($adminJs, 'observeAdminMenus') && str_contains($adminJs, '-webkit-text-fill-color'));
expectTrue('admin.css paints by data-cmb-kind', str_contains($adminCss, '[data-cmb-kind="approve"][data-cmb-active="1"]') && str_contains($adminCss, '[data-cmb-kind="hold"][data-cmb-active="1"]'));
expectTrue('admin jobs show hides legacy WDH row', str_contains($adminJobsShow, 'cmb-legacy-size-row') && str_contains($adminCss, '.cmb-legacy-size-row'));
expectTrue('admin jobs show has PDF checkbox', str_contains($adminJobsShow, '"text": "PDF"') && str_contains($adminJobsShow, 'ext_pdf'));
expectTrue('admin.js syncs cmb-admin-dark', str_contains($adminJs, 'syncAdminDark') && str_contains($adminJs, 'cmb-admin-dark-css'));
expectTrue('admin.js always forces cmb-admin-dark', str_contains($adminJs, 'Admin maker-bids always uses dark translucent') || str_contains($adminJs, "html.classList.add('cmb-admin-dark')"));
expectTrue('admin.css forces host bg-white kill', str_contains($adminCss, '.cmb-admin-card.bg-white') && str_contains($adminCss, '0.10.14: force dark translucent') && str_contains($adminCss, 'background-color: rgba(255, 255, 255, 0.06) !important'));
expectTrue('form.css forces admin surfaces without dark gate', str_contains($formCss, '0.10.14: admin surfaces always translucent'));
expectTrue('admin.js does not use body:has', ! str_contains($adminJs, 'body:has'));
$adminBidsLayout = (string) file_get_contents($root.'/resources/layouts/admin/bids_index.json');
expectTrue('admin bids list is full width', str_contains($adminBidsLayout, 'cmb-admin-list w-full') && str_contains($adminBidsLayout, 'cmb-admin-row w-full') && str_contains($adminBidsLayout, 'cmb-admin-filter-id'));
$adminActivity = (string) file_get_contents($root.'/resources/layouts/admin/activity_index.json');
expectTrue('admin activity lists are full width', str_contains($adminActivity, 'cmb-admin-list w-full') && str_contains($adminActivity, 'cmb-admin-row w-full') && str_contains($adminActivity, 'cmb-admin-filter-id'));
expectTrue('admin.css rows are nowrap horizontal cards', str_contains($adminCss, 'flex-direction: row !important') && str_contains($adminCss, 'flex-wrap: nowrap !important') && str_contains($adminCss, 'border-radius: 0.75rem'));
expectTrue('admin.css title and meta share one line', str_contains($adminCss, '.cmb-admin-row-main') && str_contains($adminCss, 'text-overflow: ellipsis') && str_contains($adminCss, '.cmb-admin-muted::before'));
expectTrue('admin.css allows wrap only on narrow screens', str_contains($adminCss, '@media (max-width: 640px)') && str_contains($adminCss, 'flex-wrap: wrap !important'));
expectTrue('form.css row fallback is nowrap', str_contains($css, 'flex-wrap: nowrap') && str_contains($css, '.cmb-admin-row-actions'));
expectTrue('admin jobs row is single-line card', str_contains($adminJobs, 'cmb-admin-row-main min-w-0 flex-1') && str_contains($adminJobs, 'cmb-admin-row-actions shrink-0'));
expectTrue('admin bids row is single-line card', str_contains($adminBidsLayout, 'flex-nowrap') && str_contains($adminBidsLayout, 'cmb-admin-row-actions shrink-0'));
expectTrue('admin companies row is single-line card', str_contains($adminCos, 'flex-nowrap') && str_contains($adminCos, 'cmb-admin-row-actions shrink-0'));
expectTrue('admin types row is single-line card', str_contains($adminTypes, 'flex-nowrap') && str_contains($adminTypes, 'cmb-admin-row-actions shrink-0'));
expectTrue('admin activity rows are single-line cards', str_contains($adminActivity, 'flex-nowrap') && str_contains($adminActivity, 'cmb-admin-row-actions shrink-0'));



$ops = (string) file_get_contents($root.'/resources/layouts/admin/ops_index.json');
expectTrue('admin ops route registered', in_array('*/admin/maker-bids/ops', $adminPaths, true));
expectTrue('admin disputes route registered', in_array('*/admin/maker-bids/disputes', $adminPaths, true));
expectTrue('admin payments route registered', in_array('*/admin/maker-bids/payments', $adminPaths, true));
expectTrue('admin ops uses row cards not raw P dump', str_contains($ops, 'cmb-admin-row') && str_contains($ops, 'admin/claims/{{$item.id}}'));
expectTrue('admin ops has claims reports audits sections', str_contains($ops, '클레임') && str_contains($ops, '신고') && str_contains($ops, '감사 로그'));
expectTrue('admin jobs uses shared nav stub', str_contains($adminJobs, 'cmb_admin_nav') && str_contains($adminJobs, 'data-cmb-admin-nav'));
expectTrue('admin jobs nav stub has no hardcoded 6 links', ! str_contains($adminJobs, '"id": "ntypes"') && ! str_contains($adminJobs, '"id": "nset"'));
expectTrue('listener fills shared admin nav with ops', str_contains($nav, 'ensureAdminNav') && str_contains($nav, '/admin/maker-bids/ops') && str_contains($nav, "'운영'"));
$modulePhpFile = (string) file_get_contents($root.'/module.php');
expectTrue('module getAdminMenus includes ops', str_contains($modulePhpFile, "/admin/maker-bids/ops") && str_contains($modulePhpFile, "'ops'"));
expectTrue('getDynamicTables includes marketplace extras', str_contains($modulePhpFile, 'maker_messages') && str_contains($modulePhpFile, 'maker_claims'));
expectTrue('jobs lead typo fixed', ! str_contains($adminJobs, '색이 치며니다') && str_contains($adminJobs, '색이 칠해집니다'));

$ws = (string) file_get_contents($root.'/resources/layouts/user/jobs_workspace.json');
expectTrue('workspace message compose', str_contains($ws, 'jobs/{{route.id}}/messages') && str_contains($ws, 'dataKey": "msg"'));
expectTrue('workspace complete uses local score/comment', str_contains($ws, '_local.complete') && ! str_contains($ws, '좋은 거래였습니다.'));
expectTrue('workspace claim and report', str_contains($ws, '/claim') && str_contains($ws, '/report'));

$hist = (string) file_get_contents($root.'/resources/layouts/user/jobs_history.json');
expectTrue('history notices panel', str_contains($hist, '/notices') && str_contains($hist, 'notices_card'));
$noticesLayout = (string) file_get_contents($root.'/resources/layouts/user/jobs_notices.json');
expectTrue('dedicated notices layout', str_contains($noticesLayout, 'jobs_notices') && str_contains($noticesLayout, '/notices/{{$n.id}}/read'));
expectTrue('form create/edit gated by route.id', str_contains($form, 'submit_create') && str_contains($form, 'submit_edit') && str_contains($form, '{{!route.id}}') && str_contains($form, '{{!!route.id}}'));
expectTrue('form job auto_fetch for edit', preg_match('/"id": "job"[\s\S]*?"auto_fetch": true/', $form) === 1);
expectTrue('form has create and edit notice keys', str_contains($form, '"data-cmb-notice": "create"') && str_contains($form, '"data-cmb-notice": "edit"'));


$market = (string) file_get_contents($root.'/src/Services/MarketplaceService.php');
expectTrue('marketplace guards messages claims reports', str_contains($market, "hasTable('maker_messages')") && str_contains($market, "hasTable('maker_claims')") && str_contains($market, "hasTable('maker_reports')"));

$oldIdHits = [];
$scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($scan as $file) {
    if (! $file->isFile()) {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root) + 1);
    if (str_starts_with($rel, '.git/') || str_starts_with($rel, 'tests/') || $rel === 'CHANGELOG.md' || $rel === 'README.md' || $rel === 'module.php') {
        continue;
    }
    $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
    if (! in_array($ext, ['php', 'json', 'js', 'css', 'md', 'txt'], true)) {
        continue;
    }
    $body = (string) file_get_contents($file->getPathname());
    if (preg_match('/custom-maker_bid(?!s)/', $body) === 1 || preg_match('/Modules\\\\Custom\\\\MakerBid(?!s)/', $body) === 1) {
        $oldIdHits[] = $rel;
    }
}
expectTrue('no leftover custom-maker_bid identifier or MakerBid namespace outside changelog/readme/tests/module.php', $oldIdHits === []);
expectTrue('module.php purges orphan custom-maker_bid menus on install', str_contains((string) file_get_contents($root.'/module.php'), "'custom-maker_bid'") && str_contains((string) file_get_contents($root.'/module.php'), 'custom-maker_bids'));

expectTrue('jobs_show compare card', str_contains($show, 'compare_card') && str_contains($show, '입찰 비교'));
expectTrue('jobs_show compare award/reject', str_contains($show, '/award') && str_contains($show, '/reject'));
expectTrue('jobs_show reviews card', str_contains($show, 'reviews_card'));
$workspace = (string) file_get_contents($root.'/resources/layouts/user/jobs_workspace.json');
expectTrue('workspace printing status', str_contains($workspace, 'printing') && str_contains($workspace, '출력중'));
expectTrue('workspace ship box', str_contains($workspace, 'ship_box') && str_contains($workspace, 'tracking_no'));
expectTrue('workspace delivery uploader', str_contains($workspace, 'delivery_box') && str_contains($workspace, 'cmb_delivery_uploader'));
$form = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
expectTrue('form terms checkbox', str_contains($form, 'terms_block') && str_contains($form, 'terms_agreed'));
expectTrue('form draft button', str_contains($form, 'draft_btn') && str_contains($form, '임시저장'));
expectTrue('form edit draft button', str_contains($form, 'draft_btn_edit') && str_contains($form, '"value": "draft"'));
$adminJobsShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('admin job status includes disputed', str_contains($adminJobsShow, '"value": "disputed"') && str_contains($adminJobsShow, '분쟁조정'));
expectTrue('admin job dispute button', str_contains($adminJobsShow, '/admin/jobs/{{route.id}}/dispute'));
$adminJobsIndex = (string) file_get_contents($root.'/resources/layouts/admin/jobs_index.json');
expectTrue('admin jobs filter uses native hosts not G7 Select', str_contains($adminJobsIndex, 'data-cmb-filter-host') && ! str_contains($adminJobsIndex, '"name": "Select"'));
expectTrue('admin jobs filter includes disputed', str_contains($adminJs, "['disputed', '분쟁조정']") && str_contains($adminJs, '분쟁조정'));
$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list search bar', str_contains($list, 'search_bar'));
expectTrue('list search q param', str_contains($list, 'q='));
$ops = (string) file_get_contents($root.'/resources/layouts/admin/ops_index.json');
expectTrue('ops report resolve UI', str_contains($ops, 'reports_resolve_help') && str_contains($ops, 'admin/reports/'));
expectTrue('ops resolve uses cmb-admin-card', str_contains($ops, 'cmb-admin-card mt-4') && ! str_contains($ops, 'cmb-section-card mt-4'));
expectTrue('module schedules in module.php', str_contains((string) file_get_contents($root.'/module.php'), 'maker-bids:run-schedule'));


$workspace = (string) file_get_contents($root.'/resources/layouts/user/jobs_workspace.json');
expectTrue('workspace 1:1 thread classes', str_contains($workspace, 'cmb-msg-thread') && str_contains($workspace, 'cmb-msg-bubble'));
expectTrue('workspace docs export buttons', str_contains($workspace, 'data-cmb-export') && str_contains($workspace, 'docs_card'));
expectTrue('workspace delivery collection', str_contains($workspace, '"collection": "delivery"'));
$pageJs = (string) file_get_contents($root.'/resources/assets/page.js');
expectTrue('page.js export binder', str_contains($pageJs, 'data-cmb-export') && str_contains($pageJs, 'cmb-export'));
$mig = (string) file_get_contents($root.'/database/migrations/2026_09_16_000016_delivery_file_expiry.php');
expectTrue('expiry migration expires_at', str_contains($mig, 'expires_at') && str_contains($mig, 'purged_at'));


$formJs = file_get_contents(dirname(__DIR__).'/resources/assets/form.js');
expectTrue('form.js has dummy fill', str_contains($formJs, 'applyJobDummyFill') && str_contains($formJs, 'applyCompanyDummyFill'));
expectTrue('form.js bindDummyFill', str_contains($formJs, 'bindDummyFill'));
$jobsForm = file_get_contents(dirname(__DIR__).'/resources/layouts/user/jobs_form.json');
expectTrue('jobs_form dummy button', str_contains($jobsForm, '더미 입력') && str_contains($jobsForm, 'data-cmb-dummy-fill'));
$coForm = file_get_contents(dirname(__DIR__).'/resources/layouts/user/company_apply.json');
expectTrue('company_apply dummy button', str_contains($coForm, '더미 입력') && str_contains($coForm, 'data-cmb-dummy-fill'));
expectTrue('page.js catalog has title', str_contains(file_get_contents(dirname(__DIR__).'/resources/assets/page.js'), "label: '제목'"));


$companyApply = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
expectTrue('company_apply notice slot', str_contains($companyApply, 'data-cmb-notice') && str_contains($companyApply, '"company"'));
expectTrue('company_apply logo initialFiles from me/local', str_contains($companyApply, 'me.data.logo_files') && str_contains($companyApply, 'company.logo_files'));
$companyList = (string) file_get_contents($root.'/resources/layouts/user/company_list.json');
expectTrue('company_list notice slot', str_contains($companyList, 'data-cmb-notice') && str_contains($companyList, '"companies"'));
expectTrue('nav.js caches notices for SPA reapply', str_contains($navJs, 'cachedNotices') && str_contains($navJs, 'reapplyNotice'));
$adminJobsShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('admin job edit copies image_files to local', str_contains($adminJobsShow, 'form.image_files') && str_contains($adminJobsShow, '_local.form.image_files'));
expectTrue('admin job edit copies archive_files to local', str_contains($adminJobsShow, 'form.archive_files') && str_contains($adminJobsShow, '_local.form.archive_files'));
expectTrue('admin job edit has archives uploader', str_contains($adminJobsShow, 'cmb_admin_archives_uploader'));
expectTrue('jobs_form edit initialFiles use local image/archive files', str_contains($form, '_local.form.image_files') && str_contains($form, '_local.form.archive_files'));
expectTrue('admin settings has companies notice fields', str_contains($adminSettings, 'companies_enabled') && str_contains($adminSettings, 'companies_body'));


$companyApply = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
expectTrue('company logo autoUpload true', str_contains($companyApply, '"autoUpload": true') && str_contains($companyApply, 'cmb_logo_uploader'));
expectTrue('company logo_count onFilesChange', str_contains($companyApply, 'company.logo_count'));
$jobsForm = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
expectTrue('jobs form has 랜덤 추가', str_contains($jobsForm, '랜덤 추가') && str_contains($jobsForm, 'data-cmb-size-random'));
expectTrue('form.js has normalizeStatusSlug', str_contains((string) file_get_contents($root.'/resources/assets/form.js'), 'normalizeStatusSlug'));
expectTrue('form.js collectCreateJobPayload', str_contains((string) file_get_contents($root.'/resources/assets/form.js'), 'collectCreateJobPayload'));
expectTrue('admin company load upload_token', str_contains((string) file_get_contents($root.'/resources/layouts/admin/companies_index.json'), 'edit.upload_token'));

expectTrue('jobs_form files_ready default', str_contains($form, '"files_ready": false') || str_contains($form, '"files_ready":false'));
expectTrue('jobs_form edit uploaders gated by files_ready', str_contains($form, '!!route.id && _local.form.files_ready'));
expectTrue('jobs_form onSuccess sets files_ready', str_contains($form, '"form.files_ready": true') || str_contains($form, '"form.files_ready":true'));
$adminJobsShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('admin jobs_show uploaders gated by files_ready', str_contains($adminJobsShow, '_local.form.files_ready'));
$companyApply = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
expectTrue('company_apply logo gated by files_ready', str_contains($companyApply, '_local.company.files_ready'));
$adminCos = (string) file_get_contents($root.'/resources/layouts/admin/companies_index.json');
expectTrue('admin company logo gated by files_ready', str_contains($adminCos, 'edit.files_ready'));
$jobsShow = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('jobs_show bid submit class cmb-bid-submit', str_contains($jobsShow, 'cmb-bid-submit') && str_contains($jobsShow, 'data-cmb-bid-submit'));
expectTrue('jobs_show bid update class cmb-bid-update', str_contains($jobsShow, 'cmb-bid-update') && str_contains($jobsShow, 'data-cmb-bid-update'));
// Submit button must not carry layout apiCall (page.js owns the POST)
// Prefer structural check: no actions block near submit with bids POST
$submitSlice = '';
if (preg_match('/"id":\s*"submit"[\s\S]{0,1200}?("id":\s*"esubmit"|"id":\s*"editform"|$)/', $jobsShow, $m)) {
    $submitSlice = $m[0];
}
expectTrue('jobs_show bid submit has no apiCall actions', $submitSlice !== '' && !str_contains($submitSlice, '"handler": "apiCall"') && !str_contains($submitSlice, '/jobs/{{route.id}}/bids'));
$editSlice = '';
if (preg_match('/"id":\s*"esubmit"[\s\S]{0,1200}?("id":\s*"|$)/', $jobsShow, $m)) {
    $editSlice = $m[0];
}
expectTrue('jobs_show bid update has no apiCall actions', $editSlice !== '' && !str_contains($editSlice, '"handler": "apiCall"'));
expectTrue('jobs_form existing files gallery', str_contains($form, 'cmb-existing-files') && str_contains($form, 'cmb_images_existing') && str_contains($form, 'cmb_archives_existing'));
expectTrue('admin jobs_show existing files gallery', str_contains($adminJobsShow, 'cmb_admin_images_existing') && str_contains($adminJobsShow, 'cmb_admin_archives_existing'));
expectTrue('company_apply existing logo gallery', str_contains($companyApply, 'cmb_logo_existing') && str_contains($companyApply, 'cmb-existing-files'));
expectTrue('admin company existing logo gallery', str_contains($adminCos, 'cmb_admin_logo_existing'));
expectTrue('admin company edit loads profile fields', str_contains($adminCos, 'edit.name') && str_contains($adminCos, 'edit.kind') && str_contains($adminCos, 'edit.bio') && str_contains($adminCos, 'edit.manager_name') && str_contains($adminCos, 'edit.address') && str_contains($adminCos, 'edit.job_types') && str_contains($adminCos, 'edit.rejected_reason'));
expectTrue('admin company logo sits in uploader box', str_contains($adminCos, 'cmb-admin-uploader') && str_contains($adminCos, 'cmb-existing-in-uploader') && str_contains($adminCos, 'cmb_admin_logo_existing') && str_contains($adminCos, 'cmb_admin_logo_uploader'));
expectTrue('admin company approve/hold/reject sit with save', str_contains($adminCos, 'cmb-admin-company-toolbar') && str_contains($adminCos, '선택 업체 저장') && str_contains($adminCos, '"id": "eapprove"') && str_contains($adminCos, '"id": "eholdbtn"') && str_contains($adminCos, '"id": "erejectbtn"'));
expectTrue('admin company edit harvests named fields', str_contains($adminJs, 'data-cmb-company-edit') && str_contains($adminJs, 'function harvestNamedInto') && str_contains($adminJs, 'nestExistingIntoUploader'));
expectTrue('admin uploader box is compact', str_contains($adminCss, 'cmb-admin-uploader-compact') && str_contains($adminCss, 'width: 40px') && str_contains($adminJs, 'cmb-admin-uploader-compact') && str_contains($adminCss, 'is-cmb-editing'));
expectTrue('page.js admin company fills profile', str_contains($pageJs, 'homepage_url') && str_contains($pageJs, 'applyAdminCompany') && str_contains($pageJs, 'formatClaimHistory'));
expectTrue('jobs_form uploader_epoch', str_contains($form, 'uploader_epoch'));
$pageJs = (string) file_get_contents($root.'/resources/assets/page.js');
expectTrue('page.js detail bid cookie POST', str_contains($pageJs, 'cmb-bid-submit') && str_contains($pageJs, '[data-cmb-bid-submit]') && str_contains($pageJs, 'credentials: \'include\''));
expectTrue('page.js detail bid update', str_contains($pageJs, 'cmb-bid-update') && str_contains($pageJs, 'updateDetailBid'));
expectTrue('UploadRules toUploaderFile', str_contains((string) file_get_contents($root.'/src/Support/UploadRules.php'), 'function toUploaderFile'));


echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
