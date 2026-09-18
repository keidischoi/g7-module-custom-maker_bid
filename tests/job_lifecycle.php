<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Services\JobCreateStatus;
use Modules\Custom\MakerBids\Support\BiddingRules;
use Modules\Custom\MakerBids\Support\JobPresenter;
use Modules\Custom\MakerBids\Support\JobRules;

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);

// --- 1. 회원 의뢰 작성 ---
expect('회원 기본 제출은 견적요청', JobCreateStatus::resolve('quote_request', 'quote_request'), 'quote_request');
expect('회원 의뢰 선택은 기본상태로 강제', JobCreateStatus::resolve('request', 'quote_request'), 'quote_request');
expect('회원 보류 선택도 기본상태로 강제', JobCreateStatus::resolve('hold', 'quote_request'), 'quote_request');
expect('임시저장만 draft로 유지', JobCreateStatus::resolve('draft', 'quote_request'), 'draft');
expect('회원은 취소 상태로 작성 불가', JobCreateStatus::resolve('cancelled', 'quote_request'), 'quote_request');
expect('한글 취소도 작성 시 견적요청', JobCreateStatus::resolve('취소', 'quote_request'), 'quote_request');
expectTrue('작성 규칙에 cancelled 없음', ! str_contains((string) JobRules::createRules()['status'][2], 'cancelled'));

$created = new MakerJob;
$created->status = JobCreateStatus::resolve('quote_request', 'quote_request');
$created->bidding_status = BiddingRules::OPEN;
$created->closes_at = null;
expectTrue('작성 직후 입찰 가능', $created->isOpen());
expect('작성 직후 배지 입찰중', JobPresenter::statusLabel($created), '입찰중');
expectTrue('작성 직후 공개 목록 대상', in_array((string) $created->status, JobRules::PUBLIC_STATUSES, true));
expectTrue('작성 직후 수정 가능', JobRules::isListingStatus((string) $created->status));

// --- 2. 관리자 취소 ---
expect('관리자 취소 슬러그', JobRules::normalizeStatus('cancelled'), 'cancelled');
expect('관리자 한글 취소', JobRules::normalizeStatus('취소'), 'cancelled');
expect('회원 수정은 취소를 견적요청으로', JobRules::normalizeListingStatus('cancelled'), 'quote_request');

$adminUpdate = (string) file_get_contents($root.'/src/Http/Requests/Admin/UpdateJobRequest.php');
$blank = (string) file_get_contents($root.'/src/Support/BlankToNull.php');
$adminCtrl = (string) file_get_contents($root.'/src/Http/Controllers/Admin/JobAdminController.php');
$jobService = (string) file_get_contents($root.'/src/Services/JobService.php');
expectTrue('관리자 PATCH는 전체 상태 정규화', str_contains($adminUpdate, 'normalizeJobStatusAndExtensions(false)'));
expectTrue('상태 정규화 플래그 기본은 listing', str_contains($blank, 'bool $listingOnly = true'));
expectTrue('관리자 취소 버튼은 cancelled 직행', str_contains($adminCtrl, "setStatusDirect(\$id, 'cancelled'"));
expectTrue('관리자 폴백은 listing이 아니라 전체 상태', str_contains($adminCtrl, 'JobRules::normalizeStatus($status)') && ! str_contains($adminCtrl, 'normalizeListingStatus($status)'));
expectTrue('취소 시 입찰 종료', str_contains($adminCtrl, 'BiddingRules::CLOSED') && str_contains($jobService, 'bidding_status = BiddingRules::CLOSED'));

$cancelled = new MakerJob;
$cancelled->status = 'cancelled';
$cancelled->bidding_status = BiddingRules::CLOSED;
$cancelled->closes_at = null;
$cancelled->user_id = 7;

expectFalse('취소 후 입찰 불가', $cancelled->isOpen());
expectFalse('JobRules도 취소는 비공개입찰', JobRules::isOpen('cancelled', null));
expectFalse('BiddingRules도 취소 거부', BiddingRules::isOpen(BiddingRules::OPEN, 'cancelled', null));
expect('취소 배지', JobPresenter::statusLabel($cancelled), '취소');
expect('취소 후 입찰 라벨 종료', JobPresenter::biddingLabel($cancelled), '종료');
expectFalse('취소는 회원 수정 상태 아님', JobRules::isListingStatus('cancelled'));
expectFalse('취소는 입찰 가능 상태 아님', JobRules::isBiddableStatus('cancelled'));
expectFalse('취소는 숨김 상태가 아님(공개 목록)', JobRules::isHiddenFromPublic('cancelled'));
expectTrue('취소는 공개 상태 집합', in_array('cancelled', JobRules::PUBLIC_STATUSES, true));
expect('취소 필터 영문', JobRules::listStatusFilter('cancelled'), ['cancelled']);
expect('취소 필터 한글', JobRules::listStatusFilter('취소'), ['cancelled']);
expect('완료 필터 한글', JobRules::listStatusFilter('완료'), ['done']);
expect('낙찰 필터 한글', JobRules::listStatusFilter('낙찰'), ['awarded']);

// --- 3. UI 경로: 회원 작성 → 관리자 취소 → 목록 칩 ---
$form = (string) file_get_contents($root.'/resources/layouts/user/jobs_form.json');
$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
$adminIndex = (string) file_get_contents($root.'/resources/layouts/admin/jobs_index.json');
$adminShow = (string) file_get_contents($root.'/resources/layouts/admin/jobs_show.json');
$searchFix = (string) file_get_contents($root.'/resources/assets/search-fix.js');
$api = (string) file_get_contents($root.'/src/routes/api.php');

expectTrue('회원 작성 POST /jobs', str_contains($form, '"target": "/api/modules/custom-maker_bids/jobs"'));
expectTrue('회원 작성은 cancel 엔드포인트 없음', ! str_contains($form, '/cancel'));
expectTrue('관리자 목록 취소 버튼', str_contains($adminIndex, '/admin/jobs/{{$item.id}}/cancel'));
expectTrue('관리자 상세 취소 버튼', str_contains($adminShow, '/admin/jobs/{{route.id}}/cancel'));
expectTrue('관리자 상태 선택에 cancelled', str_contains($adminShow, '"value": "cancelled"'));
expectTrue('공개 목록 취소 칩', str_contains($list, 'status=cancelled') && str_contains($list, '"text": "취소"'));
expectTrue('search-fix 공개 칩에 cancelled', str_contains($searchFix, "['cancelled', '취소']"));
expectTrue('상세는 닫힘 안내', str_contains($show, '열려 있지 않아'));
expectTrue('상세 수정은 can_edit 게이트', str_contains($show, 'can_edit'));
expectTrue('취소 API는 관리자 권한', str_contains($api, "jobs/{id}/cancel") && str_contains($api, 'custom-maker_bids.jobs.update'));

$css = (string) file_get_contents($root.'/resources/assets/form.css');
expectTrue('취소 배지 스타일', str_contains($css, '.cmb-badge-cancelled'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
