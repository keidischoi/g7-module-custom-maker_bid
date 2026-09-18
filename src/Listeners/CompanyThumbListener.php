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
            'core.layout_extension.after_apply' => ['method' => 'patch', 'priority' => 90, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function patch(mixed $layout = null): mixed
    {
        return is_array($layout) ? $this->walk($layout) : $layout;
    }

    private function walk(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        $node = $this->touch($node);
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            foreach ($node[$key] as $i => $child) {
                $node[$key][$i] = $this->walk($child);
            }
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $i => $child) {
                    $node['slots'][$slot][$i] = $this->walk($child);
                }
            }
        }

        return $node;
    }

    private function touch(array $node): array
    {
        $name = (string) ($node['name'] ?? '');
        $id = (string) ($node['id'] ?? '');
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $cls = (string) ($props['className'] ?? '');

        if (str_contains($cls, 'cmb-card-list')) {
            unset($props['style']);
            if ($id === 'clist' || str_contains($cls, 'cmb-company-gallery')) {
                $props['className'] = trim(preg_replace('/\s+/', ' ', str_replace('cmb-company-gallery', '', $cls)).' cmb-company-gallery');
            }
        }
        $node['props'] = $props;

        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->ensureChild($node, 'cmb_job_thumb', '{{$item.thumbnail_url}}', '64', false);
        }
        if ($id === 'ctop') {
            $node = $this->ensureChild($node, 'cmb_co_logo', '{{$item.thumbnail_url || $item.logo_url}}', '72', true);
        }
        if ($id === 'me_top') {
            $node = $this->ensureChild($node, 'cmb_me_logo', '{{me.data.thumbnail_url || me.data.logo_url}}', '72', true);
        }

        return $node;
    }

    private function ensureChild(array $node, string $id, string $src, string $size, bool $round): array
    {
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $child) {
            if (is_array($child) && ($child['id'] ?? '') === $id) {
                return $node;
            }
        }
        array_unshift($kids, $this->thumb($id, $src, $size, $round));
        $node['children'] = $kids;

        return $node;
    }

    private function thumb(string $id, string $src, string $size, bool $round): array
    {
        $style = 'width:'.$size.'px;height:'.$size.'px;object-fit:cover;flex-shrink:0;';
        $style .= $round ? 'border-radius:9999px;' : 'border-radius:8px;';

        return ['id' => $id, 'type' => 'basic', 'name' => 'Img', 'props' => [
            'src' => $src,
            'alt' => '',
            'className' => $round ? 'cmb-list-thumb cmb-list-thumb-round' : 'cmb-list-thumb',
            'width' => $size,
            'height' => $size,
            'style' => $style,
        ]];
    }
}
