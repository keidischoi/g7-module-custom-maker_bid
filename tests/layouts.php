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
expectTrue('detail owner edit link', str_contains($show, '/maker-bid/{{route.id}}/edit'));

$create = (string) file_get_contents($root.'/resources/layouts/user/jobs_create.json');
expectTrue('create posts jobs', str_contains($create, '"target": "/api/modules/custom-maker_bid/jobs"'));
expectTrue('create has auth_required', str_contains($create, '"auth_required": true'));
expectTrue('create toasts errors', str_contains($create, 'error.message'));
expectTrue('create is order card', str_contains($create, '제작 주문서'));
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
expectTrue('nav cache bust 0.5.0', str_contains($nav, 'nav.js?v=0.5.0'));
expectTrue('form.js cache bust 0.5.0', str_contains($nav, 'form.js?v=0.5.0'));

$edit = (string) file_get_contents($root.'/resources/layouts/user/jobs_edit.json');
expectTrue('edit patches job', str_contains($edit, '/jobs/{{route.id}}'));
expectTrue('edit FileUploader present', str_contains($edit, 'FileUploader'));

$adminTypes = (string) file_get_contents($root.'/resources/layouts/admin/types_index.json');
expectTrue('admin types create', str_contains($adminTypes, '/admin/job-types'));
expectTrue('admin types move', str_contains($adminTypes, '/move'));
expectTrue('admin types delete', str_contains($adminTypes, '"method": "DELETE"'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
