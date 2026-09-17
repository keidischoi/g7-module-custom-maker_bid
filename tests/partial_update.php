<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\BlankToNull;

require __DIR__.'/bootstrap.php';

// --- Bid pending label ---
expect('bid pending is 검토중', BidRules::statusLabel('pending'), '검토중');
expect('bid accepted still 낙찰', BidRules::statusLabel('accepted'), '낙찰');

// --- Company partial update attrs ---
$existing = [
    'name' => '기존랩',
    'kind' => 'company',
    'type' => 'print_3d',
    'business_no' => '1234567890',
    'address' => '서울시 강남구',
    'bio' => '기존소개',
];
$partial = CompanyRules::applicantUpdateAttributes([
    'upload_token' => 'tok_logo_only',
], $existing);
expectFalse('logo-only omits name', array_key_exists('name', $partial));
expectFalse('logo-only omits address', array_key_exists('address', $partial));
expectFalse('logo-only omits business_no', array_key_exists('business_no', $partial));
expect('logo-only keeps upload_token', $partial['upload_token'] ?? null, 'tok_logo_only');

$named = CompanyRules::applicantUpdateAttributes([
    'name' => '새이름',
    'address' => '',
    'bio' => '',
], $existing);
expect('name updated', $named['name'], '새이름');
expectFalse('blank address omitted', array_key_exists('address', $named));
expectFalse('blank bio omitted', array_key_exists('bio', $named));

$withTypes = CompanyRules::applicantUpdateAttributes([
    'job_type_print_3d' => true,
    'job_type_modeling_3d' => false,
], $existing);
expectTrue('job types from flags', in_array('print_3d', $withTypes['job_types'] ?? [], true));

$unboundTypes = CompanyRules::applicantUpdateAttributes([
    'job_type_print_3d' => false,
    'job_type_modeling_3d' => false,
], $existing);
expectFalse('all-false checkboxes do not wipe types', array_key_exists('job_types', $unboundTypes));

expectTrue('applyUpdateRules softens name', in_array('sometimes', CompanyRules::applyUpdateRules()['name'], true));
expectTrue('applyRules still requires name', in_array('required', CompanyRules::applyRules()['name'], true));

// --- Job update rules soft required ---
$memberUp = JobRules::memberUpdateRules();
expectTrue('member update title sometimes', in_array('sometimes', $memberUp['title'], true));
expectTrue('member update contact_email sometimes', in_array('sometimes', $memberUp['contact_email'], true));

// --- BlankToNull dropBlankKeys behavior via anon class ---
$req = new class {
    use BlankToNull;
    private array $bag = ['title' => '', 'description' => '', 'audience' => 'all', 'budget_min' => ''];
    public function exists($k): bool { return array_key_exists($k, $this->bag); }
    public function input($k, $d = null) { return $this->bag[$k] ?? $d; }
    public function offsetUnset($k): void { unset($this->bag[$k]); }
    public function all(): array { return $this->bag; }
    public function drop(array $keys): void { $this->dropBlankKeys($keys); }
};
$req->drop(['title', 'description', 'budget_min', 'audience']);
expectFalse('dropBlank removes empty title', $req->exists('title'));
expectFalse('dropBlank removes empty description', $req->exists('description'));
expectFalse('dropBlank removes empty budget_min', $req->exists('budget_min'));
expectTrue('dropBlank keeps non-empty audience', $req->exists('audience'));

// --- JobPresenter contact hours split (reflection of private via public present keys) ---
// Smoke: JobRules contactHours combine used by create path
expect('contact hours combine', JobRules::contactHours('09:00', '18:00', null), '09:00 ~ 18:00');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
