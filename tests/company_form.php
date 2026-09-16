<?php

declare(strict_types=1);

use Modules\Custom\MakerBid\Support\CompanyRules;
use Modules\Custom\MakerBid\Support\UploadRules;

require __DIR__.'/bootstrap.php';

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
expectTrue('can hold approved', CompanyRules::canHold('approved'));
expectFalse('cannot hold already pending', CompanyRules::canHold('pending'));
expect('pending label is 보류', CompanyRules::statusLabel('pending'), '보류');
expect('approved label is 승인', CompanyRules::statusLabel('approved'), '승인');
expect('kind company label', CompanyRules::kindLabel('company'), '업체');
expect('kind individual from 개인', CompanyRules::normalizeKind('개인'), 'individual');

$types = CompanyRules::collectJobTypes([
    'job_types' => '["print_3d","modeling_3d"]',
    'job_type_full_package' => '1',
]);
expectTrue('collects json job types', in_array('print_3d', $types, true) && in_array('modeling_3d', $types, true));
expectTrue('collects checkbox job type', in_array('full_package', $types, true));

$history = CompanyRules::normalizeClaimHistory("지연\n파손");
expect('claim history two lines', count($history), 2);
expect('claim history text', $history[0]['text'], '지연');

expect('rating clamps high', CompanyRules::clampRatingScore(9), 5.0);
expect('priority clamps', CompanyRules::clampPriority(20000), 9999);

$rec = ['is_recommended' => true, 'priority' => 1, 'id' => 1];
$plain = ['is_recommended' => false, 'priority' => 50, 'id' => 9];
$high = ['is_recommended' => true, 'priority' => 8, 'id' => 2];
expectTrue('recommended sorts before non-recommended', CompanyRules::compareListing($plain, $rec) > 0);
expectTrue('higher priority among recommended first', CompanyRules::compareListing($rec, $high) > 0);

$apply = CompanyRules::applyRules();
expectTrue('apply requires name', in_array('required', $apply['name'], true));
expectTrue('apply requires kind', in_array('required', $apply['kind'], true));
expectTrue('business_no optional', in_array('nullable', $apply['business_no'], true));

$attrs = CompanyRules::applicantAttributes([
    'name' => '테스트랩',
    'kind' => '업체',
    'job_type_print_3d' => true,
    'bio' => '소개',
    'phone' => '010-0000-0000',
]);
expect('kind stored as company', $attrs['kind'], 'company');
expect('type from first job type', $attrs['type'], 'print_3d');
expect('bio copied to note', $attrs['note'], '소개');

expectTrue('logos collection allowed', UploadRules::isAllowedCollection('logos'));
expectTrue('png allowed as logo', UploadRules::isAllowedExtension('logos', 'mark.png'));
expectTrue('512 square ok', UploadRules::isWithinLogoDimensions(512, 512));
expectFalse('513 too wide', UploadRules::isWithinLogoDimensions(513, 200));
expectTrue('logo max files is 1', UploadRules::LOGO_MAX_FILES === 1);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
