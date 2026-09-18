<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\AwardRules;
use Modules\Custom\MakerBids\Support\PaymentRules;
use Modules\Custom\MakerBids\Support\SettingsRules;

require __DIR__.'/bootstrap.php';

expect('default method bank_transfer', PaymentRules::normalizeMethod(''), 'bank_transfer');
expect('계좌이체 maps', PaymentRules::normalizeMethod('계좌이체'), 'bank_transfer');
expect('무통장 maps', PaymentRules::normalizeMethod('무통장입금'), 'bank_transfer');
expect('method label', PaymentRules::methodLabel('bank_transfer'), '계좌이체');
expect('status due', PaymentRules::normalizeStatus('대기'), 'due');
expect('status reported', PaymentRules::normalizeStatus('입금 신고'), 'reported');
expect('status confirmed', PaymentRules::normalizeStatus('입금확인'), 'confirmed');
expect('status refunded', PaymentRules::normalizeStatus('환불'), 'refunded');
expect('status label reported', PaymentRules::statusLabel('reported'), '입금 신고');
expect('dest platform', PaymentRules::normalizeDestination('플랫폼'), 'platform');
expect('dest maker', PaymentRules::normalizeDestination('제작자 계좌'), 'maker');
expect('dest label maker', PaymentRules::destinationLabel('maker'), '제작자 계좌');
expect('note interpolates job id', PaymentRules::interpolateNote('의뢰 #{job_id}', 12, '테스트'), '의뢰 #12');
expect('amount label', PaymentRules::amountLabel(150000), '150,000원');
expectTrue('method options one', count(PaymentRules::methodOptions()) === 1);
expectTrue('four statuses', count(PaymentRules::STATUSES) === 4);
expect('kind deposit', PaymentRules::normalizeKind('계약금'), 'deposit');
expect('kind balance', PaymentRules::normalizeKind('잔금'), 'balance');
expect('kind full', PaymentRules::normalizeKind('전액'), 'full');
expect('kind label deposit', PaymentRules::kindLabel('deposit'), '계약금');
expect('percent clamp 120', PaymentRules::normalizeDepositPercent(120), 100);
expect('percent empty uses fallback', PaymentRules::normalizeDepositPercent('', 30), 30);
expect('percent strip %', PaymentRules::normalizeDepositPercent('40%'), 40);
$split = PaymentRules::splitAmounts(10000, 30);
expect('split deposit 30%', $split[0], 3000);
expect('split balance 70%', $split[1], 7000);
$full = PaymentRules::splitAmounts(10000, 0);
expect('split 0% all balance', $full[0], 0);
expect('split 0% rest total', $full[1], 10000);
$allDep = PaymentRules::splitAmounts(10000, 100);
expect('split 100% deposit', $allDep[0], 10000);
expect('split 100% no balance', $allDep[1], 0);
expectTrue('kind options three', count(PaymentRules::kindOptions()) === 3);

$defaults = SettingsRules::defaults();
expectTrue('defaults have payment', isset($defaults['payment']));
expect('default dest platform', $defaults['payment']['destination'], 'platform');
expectTrue('default require confirmed', $defaults['payment']['require_confirmed'] === true);
expect('default deposit percent 30', $defaults['payment']['default_deposit_percent'], 30);

$saved = SettingsRules::fromInput([
    'method' => '계좌이체',
    'destination' => '제작자',
    'bank_name' => '국민은행',
    'account_no' => '111-22-3333',
    'account_holder' => '홍길동',
    'transfer_note' => '의뢰 #{job_id}',
    'require_confirmed' => '0',
]);
expect('fromInput method', $saved['payment']['method'], 'bank_transfer');
expect('fromInput dest maker', $saved['payment']['destination'], 'maker');
expect('fromInput bank', $saved['payment']['bank_name'], '국민은행');
expectTrue('fromInput require false', $saved['payment']['require_confirmed'] === false);

$public = SettingsRules::publicPayload($saved);
expectTrue('public payment nested', isset($public['payment']['account_no'], $public['payment']['method_label']));
expect('public method label', $public['payment']['method_label'], '계좌이체');

$flat = SettingsRules::flatten($saved);
expectTrue('flatten has account_no', isset($flat['account_no'], $flat['destination']));
$round = SettingsRules::merge(SettingsRules::unflatten($flat));
expect('unflatten dest', $round['payment']['destination'], 'maker');

$root = dirname(__DIR__);
$api = (string) file_get_contents($root.'/src/routes/api.php');
expectTrue('payments mine API', str_contains($api, "Route::get('payments'"));
expectTrue('payment report API', str_contains($api, 'payment/report'));
expectTrue('payment confirm API', str_contains($api, 'payment/confirm'));
expectTrue('admin payments API', str_contains($api, "Route::get('payments'") && str_contains($api, 'PaymentAdminController'));
expectTrue('admin confirm refund', str_contains($api, 'payments/{id}/confirm') && str_contains($api, 'payments/{id}/refund'));

$award = (string) file_get_contents($root.'/src/Services/AwardService.php');
expectTrue('award ensures due payment', str_contains($award, 'ensureDue'));
expectTrue('award passes deposit terms', str_contains($award, 'array $terms') && str_contains($award, 'ensureDue($job, $bid, $terms)'));
$market = (string) file_get_contents($root.'/src/Services/MarketplaceService.php');
expectTrue('complete requires payment', str_contains($market, 'assertCompleteAllowed'));

$userRoutes = json_decode((string) file_get_contents($root.'/resources/routes/user.json'), true);
expectTrue('user payments route', in_array('*/maker-bids/payments', array_column($userRoutes['routes'], 'path'), true));
$adminRoutes = json_decode((string) file_get_contents($root.'/resources/routes/admin.json'), true);
expectTrue('admin payments route', in_array('*/admin/maker-bids/payments', array_column($adminRoutes['routes'], 'path'), true));

$mod = (string) file_get_contents($root.'/module.php');
expectTrue('admin menu 결제', str_contains($mod, "'결제'") && str_contains($mod, '/admin/maker-bids/payments'));
expectTrue('dynamic table maker_payments', str_contains($mod, 'maker_payments'));

$nav = (string) file_get_contents($root.'/resources/assets/nav.js');
expectTrue('nav has 결제 tab', str_contains($nav, "/maker-bids/payments") && str_contains($nav, "label: '결제'"));

$ws = (string) file_get_contents($root.'/resources/layouts/user/jobs_workspace.json');
expectTrue('workspace pay box', str_contains($ws, '"id": "pay_box"') && str_contains($ws, 'payment/report'));
expectTrue('workspace confirm button', str_contains($ws, 'payment/confirm'));
expectTrue('complete mentions 입금', str_contains($ws, '입금이 확인된 뒤'));

$show = (string) file_get_contents($root.'/resources/layouts/user/jobs_show.json');
expectTrue('detail payment card', str_contains($show, 'pay_card') && str_contains($show, '결제 · 계좌이체'));

$list = (string) file_get_contents($root.'/resources/layouts/user/jobs_list.json');
expectTrue('list subnav 결제', str_contains($list, '/maker-bids/payments'));

$settingsUi = (string) file_get_contents($root.'/resources/layouts/admin/settings_index.json');
expectTrue('settings payment card', str_contains($settingsUi, 'pay_box') && str_contains($settingsUi, 'bank_name') && str_contains($settingsUi, 'require_confirmed'));
expectTrue('settings default deposit percent', str_contains($settingsUi, 'default_deposit_percent'));

$mig = (string) file_get_contents($root.'/database/migrations/2026_09_18_000021_create_maker_payments_table.php');
expectTrue('payments migration', str_contains($mig, 'maker_payments') && str_contains($mig, 'depositor_name'));

$adminPay = (string) file_get_contents($root.'/resources/layouts/admin/payments_index.json');
expectTrue('admin confirm/refund buttons', str_contains($adminPay, '입금 확인') && str_contains($adminPay, '/refund'));

$listener = (string) file_get_contents($root.'/src/Listeners/UserMenuListener.php');
expectTrue('admin nav 결제', str_contains($listener, '/admin/maker-bids/payments') && str_contains($listener, "'결제'"));
expectTrue('company fields include bank', str_contains($listener, "'bank_name'") && str_contains($listener, "'deposit_percent'"));

$svc = (string) file_get_contents($root.'/src/Services/PaymentService.php');
expectTrue('ensureDue accepts terms', str_contains($svc, 'function ensureDue') && str_contains($svc, 'array $terms = []'));
expectTrue('presentBundle exists', str_contains($svc, 'function presentBundle'));
expectTrue('balance waits for deposit', str_contains($svc, '계약금 입금 확인 후에 잔금을 처리할 수 있습니다.'));

$applyUi = (string) file_get_contents($root.'/resources/layouts/user/company_apply.json');
expectTrue('company apply bank fields', str_contains($applyUi, '"name": "bank_name"') && str_contains($applyUi, '"name": "deposit_percent"') && str_contains($applyUi, '"name": "deposit_terms"'));

expectTrue('detail award terms', str_contains($show, '"id": "award_terms"') && str_contains($show, '_local.award.deposit_percent'));
expectTrue('award body includes deposit', str_contains($show, '"deposit_percent": "{{_local.award.deposit_percent}}"'));
expectTrue('workspace pay items', str_contains($ws, '"id": "pay_items"') && str_contains($ws, 'job.data.payment.kind'));

$mig2 = (string) file_get_contents($root.'/database/migrations/2026_09_18_000022_payment_deposit_terms.php');
expectTrue('deposit migration kind unique', str_contains($mig2, 'maker_payments_job_kind_unique') && str_contains($mig2, 'deposit_percent'));

$coAdmin = (string) file_get_contents($root.'/resources/layouts/admin/companies_index.json');
expectTrue('admin company bank edit', str_contains($coAdmin, '"id": "epay_block"') && str_contains($coAdmin, 'edit.bank_name'));

$awardRules = AwardRules::requestRules();
expectTrue('award rules deposit percent', isset($awardRules['deposit_percent'], $awardRules['deposit_terms']));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
