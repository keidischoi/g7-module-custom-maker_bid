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
        return $this->walk($this->scrub($layout), $name);
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
            foreach ($node[$key] as $i => $child) {
                $node[$key][$i] = $this->walk($child, $layoutName);
            }
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $i => $child) {
                    $node['slots'][$slot][$i] = $this->walk($child, $layoutName);
                }
            }
        }

        return $node;
    }

    private function touch(array $node, string $layoutName): array
    {
        $name = (string) ($node['name'] ?? '');
        $id = (string) ($node['id'] ?? '');
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $cls = (string) ($props['className'] ?? '');

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

        if (str_contains($cls, 'cmb-open-bid-panel')) {
            $node = $this->expandOpenPanel($node);
            $props = is_array($node['props'] ?? null) ? $node['props'] : $props;
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

    private function expandOpenPanel(array $node): array
    {
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $i => $child) {
            if (($child['id'] ?? '') === 'obid_msg') {
                $p = is_array($child['props'] ?? null) ? $child['props'] : [];
                $p['placeholder'] = '포함 범위, 후가공, 배송, 수정 횟수, 유의사항';
                $p['rows'] = '6';
                $p['className'] = trim(($p['className'] ?? '').' min-h-[140px]');
                $kids[$i]['props'] = $p;
            }
        }
        $has = false;
        foreach ($kids as $child) {
            if (($child['id'] ?? '') === 'obid_mat') {
                $has = true;
            }
        }
        if (! $has) {
            $extra = [
                ['id' => 'obid_mat_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['className' => 'text-sm font-medium', 'text' => '소재 / 공정']],
                ['id' => 'obid_mat', 'type' => 'basic', 'name' => 'Input', 'props' => ['name' => 'material', 'placeholder' => '예: PLA, 0.2mm, 무후가공', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm']],
                ['id' => 'obid_scope_l', 'type' => 'basic', 'name' => 'Label', 'props' => ['className' => 'text-sm font-medium', 'text' => '포함 범위']],
                ['id' => 'obid_scope', 'type' => 'basic', 'name' => 'Input', 'props' => ['name' => 'scope', 'placeholder' => '예: 출력+후가공+포장 (모델링 별도)', 'className' => 'cmb-order-field w-full rounded-lg border px-3 py-2.5 text-sm']],
            ];
            $at = count($kids);
            foreach ($kids as $i => $child) {
                if (($child['id'] ?? '') === 'obid_msg') {
                    $at = $i + 1;
                    break;
                }
            }
            array_splice($kids, $at, 0, $extra);
        }
        $node['children'] = $kids;

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

        return [
            'id' => $id,
            'type' => 'basic',
            'name' => 'Img',
            'props' => [
                'src' => $src,
                'alt' => '{{$item.name || $item.title || ""}}',
                'className' => $round ? 'cmb-list-thumb cmb-list-thumb-round' : 'cmb-list-thumb',
                'width' => $size,
                'height' => $size,
                'style' => $style,
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
