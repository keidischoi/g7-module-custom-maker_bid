<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class CompanyThumbListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => ['method' => 'patch', 'priority' => 90, 'type' => 'filter', 'sync' => true],
            'core.layout.filter_merged' => ['method' => 'patch', 'priority' => 90, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function patch(mixed $layout = null): mixed
    {
        if (! is_array($layout)) {
            return $layout;
        }
        $name = (string) ($layout['layout_name'] ?? '');

        return $this->walk($layout, $name);
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

        if ($layoutName === 'company_list' && str_contains($cls, 'cmb-card-list')) {
            $props['className'] = trim($cls.' cmb-company-gallery');
            $props['style'] = 'display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:12px;align-items:start';
        }
        $node['props'] = $props;

        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->ensureChild($node, 'cmb_job_thumb', '{{$item.thumbnail_url || ""}}', '64', false);
        }
        if ($layoutName === 'company_list' && ($id === 'ctop' || str_contains($cls, 'cmb-company-card-top') || str_contains($cls, 'cmb-company-card'))) {
            $node = $this->ensureChild($node, 'cmb_co_logo', '{{$item.thumbnail_url || $item.logo_url || ""}}', '120', true);
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
            'src' => $src,
            'alt' => '{{$item.name || $item.title || ""}}',
            'className' => $round ? 'cmb-list-thumb cmb-list-thumb-round' : 'cmb-list-thumb',
            'width' => $size,
            'height' => $size,
            'style' => $style,
        ]];
    }
}
