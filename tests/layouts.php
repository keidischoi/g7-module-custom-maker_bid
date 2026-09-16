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

expectTrue('user list route', in_array('*/maker-bid', $userPaths, true));
expectTrue('user create route', in_array('*/maker-bid/new', $userPaths, true));
expectTrue('user bids route', in_array('*/maker-bid/bids', $userPaths, true));
expectTrue('user history route', in_array('*/maker-bid/history', $userPaths, true));
expectTrue('user company route', in_array('*/maker-bid/company', $userPaths, true));
expectTrue('user edit route', in_array('*/maker-bid/:id/edit', $userPaths, true));
expectTrue('user detail route', in_array('*/maker-bid/:id', $userPaths, true));
expectTrue('admin types route', in_array('*/admin/maker-bid/types', $adminPaths, true));
expectTrue('create requires auth', $userRoutes['routes'][1]['auth_required'] === true);
expectTrue('company requires auth', in_array(true, array_map(
    static fn (array $r): bool => $r['path'] === '*/maker-bid/company' && ($r['auth_required'] ?? false) === true,
    $userRoutes['routes'],
), true));

expectTrue('admin jobs route', in_array('*/admin/maker-bid', $adminPaths, true));
expectTrue('admin job detail route', in_array('*/admin/maker-bid/jobs/:id', $adminPaths, true));
expectTrue('admin bids route', in_array('*/admin/maker-bid/bids', $adminPaths, true));
expectTrue('admin companies route', in_array('*/admin/maker-bid/companies', $adminPaths, true));
expectTrue('admin activity route', in_array('*/admin/maker-bid/activity', $adminPaths, true));

$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('detail has award action', str_contains($show, '/jobs/{{route.id}}/award'));
expectTrue('detail has bid create', str_contains($show, '/jobs/{{route.id}}/bids'));
expectTrue('detail has bid patch', str_contains($show, '/bids/{{viewer.data.my_bid.id}}'));
expectTrue('detail mutate uses auth_required', str_contains($show, '"auth_required": true'));
expectTrue('detail toasts errors', str_contains($show, '"handler": "toast"') && str_contains($show, '{{error.message}}'));
expectTrue('detail empty bids copy', str_contains($show, '아직 들어온 견적이 없습니다.'));
expectTrue('detail privacy blocked copy', str_contains($show, '제작 의뢰가 확정'));
expectTrue('detail rush deadline label', str_contains($show, 'rush_deadline_label'));
expectTrue('detail manager info when privacy visible', str_contains($show, '담당자 정보') && str_contains($show, 'manager_name'));

$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_create.json');
expectTrue('create posts jobs', str_contains($create, '"target": "/api/modules/custom-maker_bid/jobs"'));
expectTrue('create has auth_required', str_contains($create, '"auth_required": true'));
expectTrue('create toasts errors', str_contains($create, 'error.message'));
expectTrue('create is order card', str_contains($create, '제작 주문서'));
expectTrue('create card uses theme surface', str_contains($create, 'cmb-order-card') && str_contains($create, 'dark:bg-gray-800'));
expectTrue('create fields use theme tokens', str_contains($create, 'cmb-order-field') && str_contains($create, 'dark:bg-gray-900'));
expectTrue('create does not use zinc paper card', ! str_contains($create, 'zinc-900') && ! str_contains($create, 'zinc-950'));
expectTrue('create FileUploader themed', str_contains($create, 'cmb-order-uploader'));
expectTrue('create FileUploader images', str_contains($create, 'cmb_images_uploader'));
expectTrue('create FileUploader archives', str_contains($create, 'cmb_archives_uploader'));
expectTrue('create uploadTriggerEvent images', str_contains($create, 'upload:maker_bid_images'));
expectTrue('create uploadTriggerEvent archives', str_contains($create, 'upload:maker_bid_archives'));
expectTrue('create apiEndpoints.upload', str_contains($create, '"upload": "/api/modules/custom-maker_bid/uploads"'));
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
expectTrue('create extension checkbox labels are explicit text', str_contains($create, '"text": "STL"') && str_contains($create, '"text": "3MF"') && str_contains($create, '"text": "GCODE"') && str_contains($create, '"text": "FBX"'));
expectTrue('create checkbox labels use contrast classes', str_contains($create, 'cmb-order-check-label') && str_contains($create, 'cmb-order-check-text') && str_contains($create, 'dark:text-gray-100'));
expectTrue('create rush/premium/revision checkbox labels', str_contains($create, '"text": "적용 가능"') && str_contains($create, '"text": "적용 유무"'));
expectTrue('create daytime helper 주간만', str_contains($create, '주간만') && str_contains($create, 'data-cmb-daytime'));
expectTrue('create revision min count and cost per revision under checkbox', str_contains($create, '최소 횟수') && str_contains($create, '회당 / 최대 수정비용') && preg_match('/"id": "rev_row"[\s\S]*"id": "rev_fields"[\s\S]*"id": "desc_label"/', $create) === 1);
expectTrue('create revision fields not gated only by if', str_contains($create, 'cmb-cond-rev'));
expectTrue('create profile name helper 회원정보 사용', str_contains($create, '회원정보 사용') && str_contains($create, 'data-cmb-profile-name'));
expectTrue('create extensions hidden unless modeling', str_contains($create, 'cmb-cond-ext') && str_contains($create, 'data-cmb-ext') && str_contains($create, 'includes_modeling'));
expectTrue('create size rows add and delete', str_contains($create, 'data-cmb-size-add') && str_contains($create, '"text": "추가"') && str_contains($create, 'data-cmb-sizes-list') && str_contains($create, 'data-cmb-sizes'));
expectTrue('create manager info card', str_contains($create, '담당자 정보') && str_contains($create, '담당자 명') && str_contains($create, '담당자 연락처') && str_contains($create, '담당자 이메일') && str_contains($create, 'cmb-manager-box') && str_contains($create, 'manager_name'));
expectTrue('create temp QA fill button', str_contains($create, 'data-cmb-qa-fill') && str_contains($create, '임의입력') && str_contains($create, 'cmb-qa-fill'));
expectTrue('create status options 보류', str_contains($create, '보류'));
expectTrue('create privacy block', str_contains($create, '주문자명 또는 업체명'));

$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list empty state', str_contains($list, '등록된 의뢰가 없습니다'));
expectTrue('list keeps 의뢰목록 tab', str_contains($list, '의뢰목록'));
expectTrue('list uses catalog types filter', str_contains($list, 'job-types'));
expectTrue('list notes 보류 hidden', str_contains($list, '보류'));

$company = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
expectTrue('company apply posts companies', str_contains($company, '"target": "/api/modules/custom-maker_bid/companies"'));
expectTrue('company apply auth_required', str_contains($company, '"auth_required": true'));

$adminJobs = (string) file_get_contents($root.'/resources/layouts/admin/jobs_index.json');
expectTrue('admin jobs hold', str_contains($adminJobs, '/hold'));
expectTrue('admin jobs cancel', str_contains($adminJobs, '/cancel'));
expectTrue('admin jobs delete', str_contains($adminJobs, '"method": "DELETE"'));

$adminBids = (string) file_get_contents($root.'/resources/layouts/admin/bids_index.json');
expectTrue('admin bids filter job_id', str_contains($adminBids, 'job_id'));
expectTrue('admin bids delete', str_contains($adminBids, '/admin/bids/{{$item.id}}'));

$adminCos = (string) file_get_contents($root.'/resources/layouts/admin/companies_index.json');
expectTrue('admin company approve', str_contains($adminCos, '/approve'));
expectTrue('admin company reject', str_contains($adminCos, '/reject'));
expectTrue('admin company delete', str_contains($adminCos, '/admin/companies/{{$co.id}}'));

$nav = (string) file_get_contents($root.'/src/Listeners/UserMenuListener.php');
expectTrue('nav cache bust 0.5.3', str_contains($nav, 'nav.js?v=0.5.3'));
expectTrue('form.js cache bust 0.5.3', str_contains($nav, 'form.js?v=0.5.3'));
expectTrue('form.css cache bust 0.5.3', str_contains($nav, 'form.css?v=0.5.3'));

$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_edit.json');
expectTrue('edit patches job', str_contains($edit, '/jobs/{{route.id}}'));
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

$css = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('form.css themes dark card', str_contains($css, 'color-scheme: dark') && str_contains($css, '--cmb-card'));
expectTrue('form.css styles fields and uploader', str_contains($css, '.cmb-order-field') && str_contains($css, '.cmb-order-uploader'));
expectTrue('form.css forces checkbox label contrast', str_contains($css, '--cmb-check-fg') && str_contains($css, '.cmb-order-check-label') && str_contains($css, '.cmb-order-check-text'));
expectTrue('form.css has temp QA fill button', str_contains($css, '.cmb-qa-fill') && str_contains($css, '모듈 완성 후 삭제 예정'));
expectTrue('form.css styles size add/remove', str_contains($css, '.cmb-size-add') && str_contains($css, '.cmb-size-remove'));
expectTrue('form.css styles manager box', str_contains($css, '.cmb-manager-box'));
expectTrue('form.css conditional rush/rev/ext', str_contains($css, '.cmb-cond-rush') && str_contains($css, '.cmb-cond-rev') && str_contains($css, '.cmb-cond-ext') && str_contains($css, 'pointer-events: auto'));

$formJs = (string) file_get_contents($root.'/resources/assets/form.js');
expectTrue('form.js injects form.css', str_contains($formJs, 'form.css?v=0.5.3'));
expectTrue('form.js daytime helper 09:00-17:00', str_contains($formJs, '09:00') && str_contains($formJs, '17:00') && str_contains($formJs, 'data-cmb-daytime'));
expectTrue('form.js temp QA fill skips FileUploader', str_contains($formJs, 'fillQaDummy') && str_contains($formJs, '모듈 완성 후 삭제 예정') && str_contains($formJs, 'FileUploader'));
expectTrue('form.js QA fill does not emit upload events', ! str_contains($formJs, 'upload:maker_bid'));
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

$asset = (string) file_get_contents($root.'/src/Http/Controllers/AssetController.php');
expectTrue('asset controller serves form.css', str_contains($asset, 'formCss') && str_contains($asset, 'form.css'));

$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('form.css route', str_contains($api, "assets/form.css"));

$adminTypes = (string) file_get_contents($root.'/resources/layouts/admin/types_index.json');
expectTrue('admin types create', str_contains($adminTypes, '/admin/job-types'));
expectTrue('admin types move', str_contains($adminTypes, '/move'));
expectTrue('admin types delete', str_contains($adminTypes, '"method": "DELETE"'));
expectTrue('admin types includes_modeling checkbox', str_contains($adminTypes, 'includes_modeling') && str_contains($adminTypes, '모델링 포함 (제공 확장자)'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
