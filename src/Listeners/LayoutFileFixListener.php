<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\MakerBids\Support\HistoryLayout;

class LayoutFileFixListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => ['method' => 'patch', 'priority' => 80, 'type' => 'filter', 'sync' => true],
            'core.layout.filter_merged' => ['method' => 'patch', 'priority' => 80, 'type' => 'filter', 'sync' => true],
            'core.layout_extension.after_apply' => ['method' => 'patch', 'priority' => 80, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function patch(mixed $layout = null): mixed
    {
        if (! is_array($layout)) {
            return $layout;
        }
        $name = (string) ($layout['layout_name'] ?? '');
        $layout = $this->walk($this->scrub($layout), $name);
        if ($name === 'jobs_bids') {
            $layout = $this->injectBidsPageForm($layout);
        }
        if (in_array($name, ['jobs_show', 'jobs_bids'], true)) {
            $layout = $this->ensureRevisionSource($layout, $name === 'jobs_show' ? '{{route.id}}' : '{{query.job}}');
        }
        if ($name === 'jobs_show') {
            $layout = $this->injectShowHistory($layout);
        }

        return $layout;
    }

    private function ensureRevisionSource(array $layout, string $jobExpr): array
    {
        $sources = is_array($layout['data_sources'] ?? null) ? $layout['data_sources'] : [];
        foreach ($sources as $src) {
            if (($src['id'] ?? '') === 'bid_revisions') {
                return $layout;
            }
        }
        $sources[] = [
            'id' => 'bid_revisions',
            'type' => 'api',
            'endpoint' => '/api/modules/custom-maker_bids/jobs/'.$jobExpr.'/bid-revisions',
            'method' => 'GET',
            'auto_fetch' => true,
            'auth_required' => true,
            'refetchOnMount' => true,
            'errorHandling' => ['401' => ['handler' => 'suppress'], 'default' => ['handler' => 'suppress']],
            'fallback' => ['data' => []],
        ];
        $layout['data_sources'] = $sources;

        return $layout;
    }

    private function historyPanel(): array
    {
        return HistoryLayout::panel();
    }

    private function injectShowHistory(array $layout): array
    {
        $content = $layout['slots']['content'] ?? null;
        if (! is_array($content)) {
            return $layout;
        }
        $this->appendHistory($content);
        $layout['slots']['content'] = $content;

        return $layout;
    }

    private function appendHistory(array &$nodes): bool
    {
        foreach ($nodes as $i => $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['id'] ?? '') === 'cmb_bid_hist') {
                $nodes[$i] = $this->historyPanel();

                return true;
            }
            $kids = is_array($node['children'] ?? null) ? $node['children'] : null;
            if ($kids && $this->appendHistory($kids)) {
                $nodes[$i]['children'] = $kids;

                return true;
            }
            if (($node['id'] ?? '') === 'editform') {
                $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
                $kids[] = $this->historyPanel();
                $nodes[$i]['children'] = $kids;

                return true;
            }
        }

        return false;
    }

    private function injectBidsPageForm(array $layout): array
    {
        $sources = is_array($layout['data_sources'] ?? null) ? $layout['data_sources'] : [];
        $ids = [];
        foreach ($sources as $src) {
            $ids[(string) ($src['id'] ?? '')] = true;
        }
        if (empty($ids['job'])) {
            $sources[] = [
                'id' => 'job',
                'type' => 'api',
                'endpoint' => '/api/modules/custom-maker_bids/jobs/{{query.job}}',
                'method' => 'GET',
                'auto_fetch' => true,
                'errorHandling' => ['default' => ['handler' => 'suppress']],
            ];
        }
        if (empty($ids['viewer'])) {
            $sources[] = [
                'id' => 'viewer',
                'type' => 'api',
                'endpoint' => '/api/modules/custom-maker_bids/jobs/{{query.job}}/viewer',
                'method' => 'GET',
                'auto_fetch' => true,
                'auth_required' => true,
                'errorHandling' => ['default' => ['handler' => 'suppress']],
            ];
        }
        $layout['data_sources'] = $sources;
        $state = is_array($layout['state'] ?? null) ? $layout['state'] : [];
        $state['bid'] = $state['bid'] ?? ['amount' => '', 'days' => '', 'material' => '', 'message' => ''];
        $layout['state'] = $state;
        $content = $layout['slots']['content'] ?? null;
        if (! is_array($content) || ! isset($content[0])) {
            return $layout;
        }
        $kids = is_array($content[0]['children'] ?? null) ? $content[0]['children'] : [];
        $replaced = false;
        foreach ($kids as $i => $child) {
            if (($child['id'] ?? '') === 'cmb_bid_compose') {
                $form = $this->composeForm();
                $form['children'][] = $this->historyPanel();
                $kids[$i] = $form;
                $replaced = true;
            }
        }
        if (! $replaced) {
            $form = $this->composeForm();
            $form['children'][] = $this->historyPanel();
            array_splice($kids, 1, 0, [$form]);
        }
        $content[0]['children'] = $kids;
        $layout['slots']['content'] = $content;

        return $layout;
    }

    private function bind(string $id, string $name, string $widget, string $placeholder, string $extraClass = ''): array
    {
        $key = 'bid.'.$name;

        return [
            'id' => $id,
            'type' => 'basic',
            'name' => $widget,
            'props' => [
                'name' => $name,
                'type' => $name === 'amount' || $name === 'days' ? 'number' : 'text',
                'placeholder' => $placeholder,
                'className' => trim('cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm '.$extraClass),
                'value' => '{{_local.'.$key.'}}',
                'rows' => $widget === 'Textarea' ? '8' : null,
            ],
            'actions' => [
                ['type' => 'input', 'event' => 'input', 'handler' => 'setState', 'params' => ['target' => 'local', $key => '{{$event.target.value}}']],
                ['type' => 'change', 'event' => 'change', 'handler' => 'setState', 'params' => ['target' => 'local', $key => '{{$event.target.value}}']],
            ],
        ];
    }

    private function composeForm(): array
    {
        return [
            'id' => 'cmb_bid_compose',
            'type' => 'basic',
            'name' => 'Div',
            'if' => '{{query.job}}',
            'props' => ['className' => 'cmb-section-card cmb-form-stack cmb-bid-form space-y-4 rounded-xl border p-5 mb-6', 'dataKey' => 'bid', 'trackChanges' => true],
            'children' => [
                ['id' => 'cmb_bid_h', 'type' => 'basic', 'name' => 'H2', 'props' => ['text' => '견적 등록 / 수정', 'className' => 'text-xl font-semibold']],
                ['id' => 'cmb_bid_job', 'type' => 'basic', 'name' => 'P', 'props' => ['className' => 'text-sm text-gray-500', 'text' => '{{job.data.title || query.job}}']],
                ['id' => 'cmb_bid_amt_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '견적 금액 (원) *']],
                $this->bind('cmb_bid_amt', 'amount', 'Input', '예: 150000'),
                ['id' => 'cmb_bid_days_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '제작 기간 (일)']],
                $this->bind('cmb_bid_days', 'days', 'Input', '예: 7'),
                ['id' => 'cmb_bid_msg_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '견적 설명']],
                $this->bind('cmb_bid_msg', 'message', 'Textarea', '포함 범위, 후가공, 배송', 'min-h-[160px]'),
                [
                    'id' => 'cmb_bid_go', 'type' => 'basic', 'name' => 'Button', 'text' => '견적 저장',
                    'props' => ['className' => 'cmb-btn cmb-btn-primary', 'type' => 'button'],
                    'actions' => [[
                        'type' => 'click', 'handler' => 'apiCall', 'auth_required' => true,
                        'target' => '/api/modules/custom-maker_bids/jobs/{{query.job}}/bids',
                        'params' => ['method' => 'POST', 'body' => [
                            'amount' => '{{_local.bid.amount}}',
                            'days' => '{{_local.bid.days}}',
                            'message' => '{{_local.bid.message}}',
                        ]],
                        'onSuccess' => [
                            ['handler' => 'toast', 'params' => ['type' => 'success', 'message' => '견적을 저장했습니다.']],
                        ],
                    ]],
                ],
            ],
        ];
    }

    private function walk(mixed $node, string $layoutName): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $node = $this->touch($node, $layoutName);
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            $kept = [];
            foreach ($node[$key] as $child) {
                $child = $this->walk($child, $layoutName);
                if ($this->isInlineBidPanel($child)) {
                    continue;
                }
                $kept[] = $child;
            }
            $node[$key] = $kept;
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                $kept = [];
                foreach ($items as $child) {
                    $child = $this->walk($child, $layoutName);
                    if ($this->isInlineBidPanel($child)) {
                        continue;
                    }
                    $kept[] = $child;
                }
                $node['slots'][$slot] = $kept;
            }
        }

        return $node;
    }

    private function isInlineBidPanel(mixed $node): bool
    {
        if (! is_array($node)) {
            return false;
        }

        return str_contains((string) ($node['props']['className'] ?? ''), 'cmb-open-bid-panel')
            || ($node['id'] ?? '') === 'obid_panel';
    }

    private function fieldBind(string $field): array
    {
        return [
            ['type' => 'input', 'event' => 'input', 'handler' => 'setState', 'params' => ['target' => 'local', 'bid.'.$field => '{{$event.target.value}}']],
            ['type' => 'change', 'event' => 'change', 'handler' => 'setState', 'params' => ['target' => 'local', 'bid.'.$field => '{{$event.target.value}}']],
        ];
    }

    private function touch(array $node, string $layoutName): array
    {
        $name = (string) ($node['name'] ?? '');
        $id = (string) ($node['id'] ?? '');
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $cls = (string) ($props['className'] ?? '');
        $text = (string) ($node['text'] ?? $props['text'] ?? '');

        if ($name === 'FileUploader') {
            $collection = (string) ($props['collection'] ?? '');
            if ($collection === 'images' || str_contains($id, 'images')) {
                $props['initialFiles'] = '{{job.data.images || []}}';
            } elseif ($collection === 'archives' || str_contains($id, 'archives')) {
                $props['initialFiles'] = '{{job.data.archives || []}}';
            } elseif ($collection === 'logos' || str_contains($id, 'logo')) {
                $props['initialFiles'] = '{{me.data.logo_files || []}}';
            }
            unset($props['key']);
        }

        if ($id === 'default_job_status' && $name === 'Select') {
            $props['options'] = [
                ['value' => 'quote_request', 'label' => '승인 (공개·입찰 가능)'],
                ['value' => 'draft', 'label' => '승인대기 (비공개)'],
                ['value' => 'hold', 'label' => '보류 (비공개)'],
            ];
        }

        if ($id === 'eamount') {
            $node['actions'] = $this->fieldBind('amount');
            $props['value'] = '{{_local.bid.amount || viewer.data.my_bid.amount}}';
        }
        if ($id === 'edays') {
            $node['actions'] = $this->fieldBind('days');
            $props['value'] = '{{_local.bid.days || viewer.data.my_bid.days}}';
        }
        if ($id === 'emsg') {
            $node['actions'] = $this->fieldBind('message');
            $props['value'] = '{{_local.bid.message || viewer.data.my_bid.message}}';
        }

        $isUpdate = str_contains($cls, 'cmb-bid-update') || $text === '금액 수정' || $id === 'esubmit';
        if ($isUpdate && $name === 'Button') {
            $node['actions'] = [[
                'type' => 'click', 'handler' => 'apiCall', 'auth_required' => true,
                'target' => '/api/modules/custom-maker_bids/jobs/{{route.id}}/bids',
                'params' => ['method' => 'POST', 'body' => [
                    'amount' => '{{_local.bid.amount}}',
                    'days' => '{{_local.bid.days}}',
                    'message' => '{{_local.bid.message}}',
                ]],
                'onSuccess' => [['handler' => 'toast', 'params' => ['type' => 'success', 'message' => '견적을 수정했습니다.']]],
            ]];
        }

        if ($id === 'ma1' && $name === 'A') {
            $props['href'] = '/maker-bids/bids?job={{$item.job_id || $item.job.id}}';
            $node['text'] = '견적 수정';
        }

        $goBid = str_contains($cls, 'cmb-open-bid-toggle') || $text === '견적 넣기' || $id === 'obid_btn'
            || ($layoutName === 'jobs_show' && str_contains($cls, 'cmb-bid-submit'));
        if ($goBid && in_array($name, ['Button', 'A'], true)) {
            $node['name'] = 'A';
            $node['text'] = '견적 넣기';
            $props['href'] = '/maker-bids/bids?job={{$item.id || route.id}}';
            $props['className'] = 'cmb-btn cmb-btn-primary';
            unset($node['actions'], $props['type']);
        }

        if (str_contains($cls, 'cmb-card-list')) {
            $props['className'] = trim($cls.' cmb-company-gallery');
            $props['style'] = 'display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:12px';
        }
        $node['props'] = $props;

        if ($id === 'ctop' || $id === 'citem') {
            $node = $this->ensureLogo($node, '{{$item.thumbnail_url}}');
        }
        if ($id === 'me_top' || $id === 'me_card') {
            $node = $this->ensureLogo($node, '{{me.data.thumbnail_url}}');
        }
        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->ensureLogo($node, '{{$item.thumbnail_url}}', false);
        }

        return $node;
    }

    private function ensureLogo(array $node, string $src, bool $round = true): array
    {
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $i => $child) {
            if (is_array($child) && ($child['id'] ?? '') === 'cmb_co_logo') {
                $kids[$i] = $this->logoNode($src, $round);
                $node['children'] = $kids;

                return $node;
            }
        }
        array_unshift($kids, $this->logoNode($src, $round));
        $node['children'] = $kids;

        return $node;
    }

    private function logoNode(string $src, bool $round): array
    {
        $style = 'width:96px;height:96px;object-fit:cover;flex-shrink:0;';
        $style .= $round ? 'border-radius:9999px;' : 'border-radius:8px;width:64px;height:64px;';

        return [
            'id' => 'cmb_co_logo',
            'type' => 'basic',
            'name' => 'Image',
            'props' => [
                'src' => $src,
                'url' => $src,
                'className' => $round ? 'cmb-list-thumb cmb-list-thumb-round' : 'cmb-list-thumb',
                'style' => $style,
                'width' => $round ? '96' : '64',
                'height' => $round ? '96' : '64',
            ],
        ];
    }

    private function scrub(mixed $node): mixed
    {
        if (is_string($node) && str_contains($node, 'Date.now()')) {
            return '1';
        }
        if (! is_array($node)) {
            return $node;
        }
        foreach ($node as $k => $v) {
            $node[$k] = $this->scrub($v);
        }

        return $node;
    }
}
