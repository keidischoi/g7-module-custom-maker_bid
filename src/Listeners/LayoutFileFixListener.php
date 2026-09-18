<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;

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

        return $layout;
    }

    private function injectBidsPageForm(array $layout): array
    {
        $sources = is_array($layout['data_sources'] ?? null) ? $layout['data_sources'] : [];
        $hasJob = false;
        foreach ($sources as $src) {
            if (($src['id'] ?? '') === 'job') {
                $hasJob = true;
            }
        }
        if (! $hasJob) {
            $sources[] = [
                'id' => 'job',
                'type' => 'api',
                'endpoint' => '/api/modules/custom-maker_bids/jobs/{{query.job}}',
                'method' => 'GET',
                'auto_fetch' => true,
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
        foreach ($kids as $child) {
            if (($child['id'] ?? '') === 'cmb_bid_compose') {
                return $layout;
            }
        }
        array_splice($kids, 1, 0, [$this->composeForm()]);
        $content[0]['children'] = $kids;
        $layout['slots']['content'] = $content;

        return $layout;
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
                ['id' => 'cmb_bid_h', 'type' => 'basic', 'name' => 'H2', 'props' => ['text' => '견적서 작성', 'className' => 'text-xl font-semibold']],
                ['id' => 'cmb_bid_job', 'type' => 'basic', 'name' => 'P', 'props' => ['className' => 'text-sm text-gray-500', 'text' => '{{job.data.title || "의뢰 #" + query.job}}']],
                ['id' => 'cmb_bid_amt_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '견적 금액 (원) *']],
                ['id' => 'cmb_bid_amt', 'type' => 'basic', 'name' => 'Input', 'props' => ['name' => 'amount', 'type' => 'number', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm', 'placeholder' => '예: 150000', 'value' => '{{_local.bid.amount}}']],
                ['id' => 'cmb_bid_days_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '제작 기간 (일)']],
                ['id' => 'cmb_bid_days', 'type' => 'basic', 'name' => 'Input', 'props' => ['name' => 'days', 'type' => 'number', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm', 'placeholder' => '예: 7', 'value' => '{{_local.bid.days}}']],
                ['id' => 'cmb_bid_mat_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '소재 / 공정']],
                ['id' => 'cmb_bid_mat', 'type' => 'basic', 'name' => 'Input', 'props' => ['name' => 'material', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm', 'placeholder' => '예: PLA, 0.2mm', 'value' => '{{_local.bid.material}}']],
                ['id' => 'cmb_bid_msg_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['text' => '견적 설명']],
                ['id' => 'cmb_bid_msg', 'type' => 'basic', 'name' => 'Textarea', 'props' => ['name' => 'message', 'rows' => '8', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm min-h-[160px]', 'placeholder' => '포함 범위, 후가공, 배송, 수정 횟수', 'value' => '{{_local.bid.message}}']],
                [
                    'id' => 'cmb_bid_go', 'type' => 'basic', 'name' => 'Button', 'text' => '견적 제출',
                    'props' => ['className' => 'cmb-btn cmb-btn-primary', 'type' => 'button'],
                    'actions' => [[
                        'type' => 'click', 'handler' => 'apiCall', 'auth_required' => true,
                        'target' => '/api/modules/custom-maker_bids/jobs/{{query.job}}/bids',
                        'params' => ['method' => 'POST', 'body' => ['amount' => '{{_local.bid.amount}}', 'days' => '{{_local.bid.days}}', 'message' => '{{_local.bid.message}}']],
                        'onSuccess' => [
                            ['handler' => 'toast', 'params' => ['type' => 'success', 'message' => '견적을 등록했습니다.']],
                            ['handler' => 'navigate', 'params' => ['url' => '/maker-bids/bids']],
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
        $id = (string) ($node['id'] ?? '');
        $cls = (string) ($node['props']['className'] ?? '');

        return str_contains($cls, 'cmb-open-bid-panel') || $id === 'obid_panel';
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

        $goBid = str_contains($cls, 'cmb-open-bid-toggle') || $text === '견적 넣기' || $id === 'obid_btn'
            || ($layoutName === 'jobs_show' && str_contains($cls, 'cmb-bid-submit'));
        if ($goBid && in_array($name, ['Button', 'A'], true)) {
            $node['name'] = 'A';
            $node['text'] = '견적 넣기';
            $props['href'] = '/maker-bids/bids?job={{$item.id || route.id}}';
            $props['className'] = 'cmb-btn cmb-btn-primary';
            unset($node['actions'], $props['type']);
        }

        if ($layoutName === 'company_list' && str_contains($cls, 'cmb-card-list')) {
            $props['className'] = trim($cls.' cmb-company-gallery');
            $props['style'] = 'display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:12px;align-items:start';
        }
        $node['props'] = $props;
        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->ensureChild($node, 'cmb_job_thumb', '{{$item.thumbnail_url || ""}}', '64', false);
        }
        if ($layoutName === 'company_list' && ($id === 'ctop' || str_contains($cls, 'cmb-company-card-top'))) {
            $node = $this->ensureChild($node, 'cmb_co_name_logo', '{{$item.thumbnail_url || ""}}', '120', true);
        }

        return $node;
    }

    private function ensureChild(array $node, string $id, string $src, string $size, bool $round): array
    {
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $i => $child) {
            if (is_array($child) && ($child['id'] ?? '') === $id) {
                $kids[$i] = $this->thumb($id, $src, $size, $round);
                $node['children'] = $kids;

                return $node;
            }
        }
        array_unshift($kids, $this->thumb($id, $src, $size, $round));
        $node['children'] = $kids;

        return $node;
    }

    private function thumb(string $id, string $src, string $size, bool $round): array
    {
        $style = 'width:'.$size.'px;height:'.$size.'px;max-width:100%;object-fit:cover;flex-shrink:0;';
        $style .= $round ? 'border-radius:9999px;border:1px solid rgba(255,255,255,0.18);' : 'border-radius:8px;';

        return ['id' => $id, 'type' => 'basic', 'name' => 'Img', 'props' => [
            'src' => $src, 'alt' => '{{$item.name || $item.title || ""}}',
            'className' => $round ? 'cmb-list-thumb cmb-list-thumb-round' : 'cmb-list-thumb',
            'width' => $size, 'height' => $size, 'style' => $style,
        ]];
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
