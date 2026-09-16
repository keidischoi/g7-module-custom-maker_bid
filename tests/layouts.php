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
expectTrue('detail has award action', str_contains($show, '/jobs/{{route.id}}/award'));
expectTrue('detail has bid create', str_contains($show, '/jobs/{{route.id}}/bids'));
expectTrue('detail has bid patch', str_contains($show, '/bids/{{viewer.data.my_bid.id}}'));
expectTrue('detail mutate uses auth_required', str_contains($show, '"auth_required": true'));
expectTrue('detail job fetch uses optional auth_mode', str_contains($show, '"auth_mode": "optional"') && preg_match('/"id": "job"[\s\S]*?"auth_mode": "optional"[\s\S]*?"id": "viewer"/', $show) === 1);
expectTrue('detail toasts errors', str_contains($show, '"handler": "toast"') && str_contains($show, '{{error.message}}'));
expectTrue('detail empty bids copy', str_contains($show, '아직 들어온 견적이 없습니다.'));
expectTrue('detail privacy blocked copy', str_contains($show, '제작 의뢰가 확정'));
expectTrue('detail rush deadline label', str_contains($show, 'rush_deadline_label'));
expectTrue('detail manager info when privacy visible', str_contains($show, '담당자 정보') && str_contains($show, 'manager_name'));

$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_create.json');
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
expectTrue('create does not stash PendingFile.file', ! str_contains($create, '.file'));
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
expectTrue('create temp QA fill button', str_contains($create, 'data-cmb-qa-fill') && str_contains($create, '임의입력') && str_contains($create, 'cmb-qa-fill'));
expectTrue('create tab is 입찰자 등록', str_contains($create, '입찰자 등록'));
expectTrue('create 소유권한 요청 near extensions', str_contains($create, '소유권한 요청') && str_contains($create, 'ownership_requested') && ! str_contains($create, '저작권 있음'));
expectTrue('create 공개 설정 전체/업체만/개인만', str_contains($create, '공개 설정') && str_contains($create, '"value": "all"') && str_contains($create, '업체만') && str_contains($create, '개인만'));
expectTrue('create status options 보류', str_contains($create, '보류'));
$qrOpt = strpos($create, '"value": "quote_request"');
$holdOpt = strpos($create, '"value": "hold"');
expectTrue('create status lists 견적요청 before 보류', $qrOpt !== false && $holdOpt !== false && $qrOpt < $holdOpt);
expectTrue('create privacy block', str_contains($create, '주문자명 또는 업체명'));

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
expectTrue('company apply posts companies', str_contains($company, '"target": "/api/modules/custom-maker_bids/companies"'));
expectTrue('company apply auth_required', str_contains($company, '"auth_required": true'));
expectTrue('company apply title is 입찰자 등록', str_contains($company, '입찰자 등록'));
expectTrue('company apply FileUploader logos', str_contains($company, 'FileUploader') && str_contains($company, '"collection": "logos"'));
expectTrue('company apply profile fill helper', str_contains($company, 'data-cmb-profile-fill') && str_contains($company, '회원정보'));
expectTrue('company apply job types host', str_contains($company, 'data-cmb-job-types'));
expectTrue('company apply daum postcode', str_contains($company, 'data-cmb-postcode'));
expectTrue('company apply designated is read-only copy', str_contains($company, '지정업체') && ! str_contains($company, '"name": "is_designated"'));
expectTrue('company apply submit is 입찰자 등록 top-right', str_contains($company, 'cmb-company-submit-top') && str_contains($company, '"text": "입찰자 등록"') && str_contains($company, 'data-cmb-company-submit'));
expectTrue('company apply submit sits in header not form card', preg_match('/"id": "head_right"[\s\S]*"id": "submit"[\s\S]*"id": "card"/', $company) === 1);
expectTrue('company apply submit posts companies', str_contains($company, '"target": "/api/modules/custom-maker_bids/companies"') && str_contains($company, '"method": "POST"'));

$adminJobs = (string) file_get_contents($root.'/resources/layouts/admin/jobs_index.json');
expectTrue('admin jobs hold', str_contains($adminJobs, '/hold'));
expectTrue('admin jobs cancel', str_contains($adminJobs, '/cancel'));
expectTrue('admin jobs delete', str_contains($adminJobs, '"method": "DELETE"'));

$adminJobsShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
expectTrue('admin job detail patches job', str_contains($adminJobsShow, '"method": "PATCH"') && str_contains($adminJobsShow, '/admin/jobs/{{route.id}}'));
expectTrue('admin job detail has title field', str_contains($adminJobsShow, '"name": "title"'));
expectTrue('admin job detail has status select', str_contains($adminJobsShow, '"value": "hold"') && str_contains($adminJobsShow, '"value": "awarded"') && str_contains($adminJobsShow, '"value": "done"'));
expectTrue('admin job detail has audience', str_contains($adminJobsShow, '"value": "company"') && str_contains($adminJobsShow, 'ownership_requested'));
expectTrue('admin job detail keeps hold/cancel', str_contains($adminJobsShow, '/hold') && str_contains($adminJobsShow, '/cancel'));

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
expectTrue('nav cache bust 0.8.0', str_contains($nav, 'nav.js?v=0.8.0'));
expectTrue('form.js cache bust 0.8.0', str_contains($nav, 'form.js?v=0.8.0'));
expectTrue('form.css cache bust 0.8.0', str_contains($nav, 'form.css?v=0.8.0'));
expectTrue('listener injects admin form.css via _admin_base', str_contains($nav, "=== '_admin_base'"));
expectTrue('listener strips extension nav by settings', str_contains($nav, 'maker_bids_user_nav') && str_contains($nav, 'extension_user_base'));

$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_edit.json');
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

$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('show 소유권한 요청', str_contains($show, '소유권한 요청') && str_contains($show, 'ownership_requested') && ! str_contains($show, '저작권 있음'));
expectTrue('show audience label', str_contains($show, 'audience_label'));
expectTrue('list audience label', str_contains($list, 'audience_label'));

$css = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('form.css themes dark card', str_contains($css, 'color-scheme: dark') && str_contains($css, '--cmb-card'));
expectTrue('form.css styles fields and uploader', str_contains($css, '.cmb-order-field') && str_contains($css, '.cmb-order-uploader'));
expectTrue('form.css forces checkbox label contrast', str_contains($css, '--cmb-check-fg') && str_contains($css, '.cmb-order-check-label') && str_contains($css, '.cmb-order-check-text'));
expectTrue('form.css has temp QA fill button', str_contains($css, '.cmb-qa-fill') && str_contains($css, '모듈 완성 후 삭제 예정'));
expectTrue('form.css styles size add/remove', str_contains($css, '.cmb-size-add') && str_contains($css, '.cmb-size-remove'));
expectTrue('form.css styles manager box', str_contains($css, '.cmb-manager-box'));
expectTrue('form.css conditional rush/rev/ext', str_contains($css, '.cmb-cond-rush') && str_contains($css, '.cmb-cond-rev') && str_contains($css, '.cmb-cond-ext') && str_contains($css, 'pointer-events: auto'));

$formJs = (string) file_get_contents($root.'/resources/assets/form.js');
expectTrue('form.js injects form.css', str_contains($formJs, 'form.css?v=0.8.0'));
expectTrue('form.js daytime helper 09:00-17:00', str_contains($formJs, '09:00') && str_contains($formJs, '17:00') && str_contains($formJs, 'data-cmb-daytime'));
expectTrue('form.js temp QA fill skips FileUploader', str_contains($formJs, 'fillQaDummy') && str_contains($formJs, '모듈 완성 후 삭제 예정') && str_contains($formJs, 'FileUploader'));
expectTrue('form.js QA type picks catalog then clicks Select', str_contains($formJs, 'pickQaType') && str_contains($formJs, 'catalogTypes') && str_contains($formJs, 'setG7Select') && str_contains($formJs, 'paintSelectTrigger') && str_contains($formJs, 'openSelectMenu'));
expectTrue('form.js dummy fills audience and ownership', str_contains($formJs, 'form.audience') && str_contains($formJs, 'ownership_requested') && str_contains($formJs, 'ext_dwg'));
expectTrue('form.js QA fill does not emit upload events', ! str_contains($formJs, 'upload:maker_bids'));
expectTrue('form.js dummy fills rush datetime', str_contains($formJs, 'rush_deadline') && str_contains($formJs, 'futureStamp'));
expectTrue('form.js dummy fills revision fields', str_contains($formJs, 'revision_count') && str_contains($formJs, 'revision_cost'));
expectTrue('form.js size rows add/delete max 20', str_contains($formJs, 'SIZE_MAX = 20') && str_contains($formJs, 'data-cmb-size-add') && str_contains($formJs, 'data-cmb-size-remove') && str_contains($formJs, 'fillSizeRows') && str_contains($formJs, '>삭제</button>'));
expectTrue('form.js dummy fills manager fields', str_contains($formJs, 'manager_name') && str_contains($formJs, 'manager_phone') && str_contains($formJs, 'manager_email'));
expectTrue('form.js profile name helper', str_contains($formJs, 'data-cmb-profile-name') && str_contains($formJs, 'bindProfileName'));
expectTrue('form.js toggles modeling extensions', str_contains($formJs, 'includes_modeling') && str_contains($formJs, 'syncExtVisibility') && str_contains($formJs, 'cmb-cond-ext'));
expectTrue('form.js cond toggles rush/revision', str_contains($formJs, 'bindCondToggles') && str_contains($formJs, 'cmb-cond-rush'));
expectTrue('rush deadline ensure migration', is_file($root.'/database/migrations/2026_09_16_000006_ensure_rush_deadline.php'));
expectTrue('revision fields ensure migration', is_file($root.'/database/migrations/2026_09_16_000007_ensure_revision_fields.php'));
expectTrue('sizes and manager ensure migration', is_file($root.'/database/migrations/2026_09_16_000008_ensure_sizes_and_manager_fields.php'));
expectTrue('includes_modeling ensure migration', is_file($root.'/database/migrations/2026_09_16_000009_ensure_includes_modeling.php'));
expectTrue('company profile admin fields migration', is_file($root.'/database/migrations/2026_09_16_000010_ensure_company_profile_admin_fields.php'));
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

$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('form.css route', str_contains($api, "assets/form.css"));

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
expectTrue('admin settings bid_allow', str_contains($adminSettings, 'bid_allow') && str_contains($adminSettings, '지정업체') && str_contains($adminSettings, '"value": "all"') && str_contains($adminSettings, '모든 등록된 업체') && str_contains($adminSettings, '등록된 개인회원') && str_contains($adminSettings, '일반회원') && str_contains($adminSettings, 'approved_bidders'));
expectTrue('admin settings bid_allow drops old short list', ! str_contains($adminSettings, '"value": "members"') && ! str_contains($adminSettings, '"value": "company"') && ! str_contains($adminSettings, '"value": "individual"'));
expectTrue('admin settings permission', str_contains($adminSettings, 'custom-maker_bids.settings.read'));

$navJs = (string) file_get_contents($root.'/resources/assets/nav.js');
expectTrue('nav.js fetches public settings', str_contains($navJs, '/api/modules/custom-maker_bids/settings'));
expectTrue('nav.js applies notice html', str_contains($navJs, 'data-cmb-notice') && str_contains($navJs, 'innerHTML'));
expectTrue('nav.js insert positions', str_contains($navJs, 'after_shop') && str_contains($navJs, 'after_home') && str_contains($navJs, 'prepend_row'));

$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_create.json');
expectTrue('create notice placeholder', str_contains($create, 'data-cmb-notice') && str_contains($create, '"create"'));
$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('show notice placeholder', str_contains($show, 'data-cmb-notice') && str_contains($show, '"show"'));
$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_edit.json');
expectTrue('edit notice placeholder', str_contains($edit, 'data-cmb-notice') && str_contains($edit, '"edit"'));
expectTrue('optional sanctum list still present', str_contains((string) file_get_contents($root.'/src/routes/api.php'), 'optional.sanctum'));

expectTrue('admin jobs list uses cmb-admin polish', str_contains($adminJobs, 'cmb-admin') && str_contains($adminJobs, 'cmb-admin-row-actions') && str_contains($adminJobs, 'cmb-admin-w-'));
expectTrue('admin types wrap checkbox labels', str_contains($adminTypes, 'cmb-admin-check-label') && str_contains($adminTypes, 'cmb-admin-check-text') && str_contains($adminTypes, '"text": "주소 필수"') && ! str_contains($adminTypes, '"label": "주소 필수"'));
expectTrue('admin companies use split card layout', str_contains($adminCos, 'cmb-admin-split') && str_contains($adminCos, 'cmb-admin-card') && str_contains($adminCos, 'cmb-admin-row-actions'));
expectTrue('admin jobs show sized inputs and visible ext labels', str_contains($adminJobsShow, 'cmb-admin-w-xs') && str_contains($adminJobsShow, 'cmb-admin-check-text') && str_contains($adminJobsShow, '"text": "STL"'));
expectTrue('admin form.css has field widths and check contrast', str_contains($css, '.cmb-admin-w-xs') && str_contains($css, '.cmb-admin-row-actions') && str_contains($css, '.cmb-admin-check-text'));

$oldIdHits = [];
$scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($scan as $file) {
    if (! $file->isFile()) {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root) + 1);
    if (str_starts_with($rel, '.git/') || $rel === 'CHANGELOG.md' || $rel === 'README.md' || $rel === 'tests/layouts.php') {
        continue;
    }
    $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
    if (! in_array($ext, ['php', 'json', 'js', 'css', 'md', 'txt'], true)) {
        continue;
    }
    $body = (string) file_get_contents($file->getPathname());
    if (preg_match('/custom-maker_bid(?!s)/', $body) === 1) {
        $oldIdHits[] = $rel;
    }
}
expectTrue('no leftover custom-maker_bid identifier outside changelog/readme', $oldIdHits === []);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
