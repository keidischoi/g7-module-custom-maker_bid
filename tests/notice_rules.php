<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\NoticeRules;

require __DIR__.'/bootstrap.php';

$root = dirname(__DIR__);

expect('g7 type key', NoticeRules::TYPE, 'maker_bids.notice');
expect('job click url', NoticeRules::clickUrl(12), '/maker-bids/12');
expect('inbox click url', NoticeRules::clickUrl(null), '/maker-bids/notices');

$row = NoticeRules::databasePayload('award', '낙찰했습니다.', '테스트 의뢰', 9);
expect('payload type', $row['type'], NoticeRules::TYPE);
expect('payload subject', $row['subject'], '낙찰했습니다.');
expect('payload body', $row['body'], '테스트 의뢰');
expect('payload click_url', $row['click_url'], '/maker-bids/9');
expect('payload event', $row['data']['event'], 'award');
expect('payload action_url', $row['data']['action_url'], '/maker-bids/9');

$market = (string) file_get_contents($root.'/src/Services/MarketplaceService.php');
expectTrue('notify writes maker_notices', str_contains($market, "hasTable('maker_notices')") && str_contains($market, 'function notify'));
expectTrue('notify sends g7 database channel', str_contains($market, 'trySendSystemNotification') && str_contains($market, 'MakerBidsDatabaseNotification'));
expectTrue('notify still emails', str_contains($market, 'trySendEmail') && str_contains($market, 'Mail::raw'));
expectTrue('system notify gated by setting', str_contains($market, "noticeChannelEnabled('system')") && str_contains($market, 'notify_system'));
expectTrue('email notify gated by setting', str_contains($market, "noticeChannelEnabled('email')") && str_contains($market, 'notify_email'));
expectTrue('fallback inserts notifications table', str_contains($market, "hasTable('notifications')") && str_contains($market, 'insertDatabaseNotification'));

$notif = (string) file_get_contents($root.'/src/Notifications/MakerBidsDatabaseNotification.php');
expectTrue('database via only', str_contains($notif, "return ['database']") && str_contains($notif, 'function toDatabase'));

$mod = (string) file_get_contents($root.'/module.php');
expectTrue('definition database channel', str_contains($mod, "'channels' => ['database']") && str_contains($mod, "'type' => 'maker_bids.notice'"));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
