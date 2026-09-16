<?php

declare(strict_types=1);

use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\SettingsRules;

require __DIR__.'/bootstrap.php';

$defaults = SettingsRules::defaults();
expectTrue('defaults have menu notices general', isset($defaults['menu'], $defaults['notices'], $defaults['general']));
expectTrue('default nav insert append_row', $defaults['menu']['nav_insert'] === 'append_row');
expectTrue('default nav js on', $defaults['menu']['nav_js_enabled'] === true);
expectTrue('default guests see list', $defaults['general']['guests_see_list'] === true);
expectTrue('default job status quote_request', $defaults['general']['default_job_status'] === 'quote_request');
expectTrue('default bid allow all', $defaults['general']['bid_allow'] === 'all');
expectTrue('list notice off by default', $defaults['notices']['list_enabled'] === false);

$pages = SettingsRules::pages();
expectTrue('notice pages include list and show', isset($pages['list'], $pages['create'], $pages['bids'], $pages['history'], $pages['company'], $pages['show'], $pages['edit']));

$saved = SettingsRules::fromInput([
    'nav_js_enabled' => '0',
    'nav_insert' => 'after_shop',
    'nav_label' => '제작의뢰',
    'extension_user_base' => false,
    'list_enabled' => true,
    'list_body' => '<p>안녕</p><script>alert(1)</script>',
    'default_job_status' => 'hold',
    'guests_see_list' => 'false',
    'bid_allow' => '지정업체',
]);
expectTrue('fromInput turns off nav js', $saved['menu']['nav_js_enabled'] === false);
expectTrue('fromInput nav insert after_shop', $saved['menu']['nav_insert'] === 'after_shop');
expectTrue('fromInput nav label', $saved['menu']['nav_label'] === '제작의뢰');
expectTrue('fromInput strips script', ! str_contains($saved['notices']['list_body'], 'script') && str_contains($saved['notices']['list_body'], '<p>안녕</p>'));
expectTrue('fromInput default status hold', $saved['general']['default_job_status'] === 'hold');
expectTrue('fromInput guests false string is false', $saved['general']['guests_see_list'] === false);
expectTrue('fromInput Korean designated mode', $saved['general']['bid_allow'] === 'designated');
expectTrue('bad bid_allow falls back to all', SettingsRules::fromInput(['bid_allow' => 'nope'])['general']['bid_allow'] === 'all');
expectTrue('legacy members maps to all', SettingsRules::fromInput(['bid_allow' => 'members'])['general']['bid_allow'] === 'all');
expectTrue('union Korean maps', SettingsRules::fromInput(['bid_allow' => '모든 등록된 업체 & 등록된 개인회원'])['general']['bid_allow'] === 'approved_bidders');
expectTrue('일반회원 maps', SettingsRules::fromInput(['bid_allow' => '일반회원'])['general']['bid_allow'] === 'member');
expectTrue('bad insert falls back', SettingsRules::fromInput(['nav_insert' => 'nope'])['menu']['nav_insert'] === 'append_row');
expectTrue('open status maps to quote_request', SettingsRules::fromInput(['default_job_status' => 'open'])['general']['default_job_status'] === 'quote_request');
expectTrue('awarded not allowed as default', SettingsRules::fromInput(['default_job_status' => 'awarded'])['general']['default_job_status'] === 'quote_request');
expectTrue('listing statuses include hold', in_array('hold', JobRules::LISTING_STATUSES, true));

$public = SettingsRules::publicPayload($saved);
expectTrue('public notices nested', isset($public['notices']['list']['enabled'], $public['notices']['list']['body']));
expectTrue('public list notice enabled', $public['notices']['list']['enabled'] === true);
expectTrue('disabled notice body emptied', $public['notices']['create']['body'] === '');

$flat = SettingsRules::flatten($saved);
expectTrue('flatten has list_body', isset($flat['list_body'], $flat['nav_insert']));
$round = SettingsRules::merge(SettingsRules::unflatten($flat));
expectTrue('unflatten roundtrip insert', $round['menu']['nav_insert'] === 'after_shop');

$nested = SettingsRules::fromInput(['form' => ['nav_label' => '폼라벨', 'list_enabled' => true]]);
expectTrue('nested form lifted', $nested['menu']['nav_label'] === '폼라벨' && $nested['notices']['list_enabled'] === true);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
