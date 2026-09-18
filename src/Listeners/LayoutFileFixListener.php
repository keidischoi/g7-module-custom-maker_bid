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

        return $this->walk($this->scrub($layout));
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
        $cls = (string) (($node['props']['className'] ?? ''));

        if ($name === 'FileUploader') {
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $collection = (string) ($props['collection'] ?? '');
            if ($collection === 'images' || str_contains($id, 'images')) {
                $props['initialFiles'] = '{{job.data.images || []}}';
            } elseif ($collection === 'archives' || str_contains($id, 'archives')) {
                $props['initialFiles'] = '{{job.data.archives || []}}';
            } elseif ($collection === 'logos' || str_contains($id, 'logo')) {
                $props['initialFiles'] = '{{me.data.logo_files || []}}';
            }
            unset($props['key']);
            $node['props'] = $props;
        }

        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->ensureChild($node, 'cmb_job_thumb', '{{$item.thumbnail_url || ""}}', '64', false);
        }

        if ($id === 'ctop' || str_contains($cls, 'cmb-company-card-top')) {
            $node = $this->ensureChild($node, 'cmb_co_name_logo', '{{$item.thumbnail_url || ""}}', '40', true);
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $props['className'] = trim(($props['className'] ?? '').' cmb-name-with-logo');
            $node['props'] = $props;
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
        $style = 'width:'.$size.'px;height:'.$size.'px;object-fit:cover;flex-shrink:0;';
        $style .= $round
            ? 'border-radius:9999px;border:1px solid rgba(255,255,255,0.18);'
            : 'border-radius:8px;';

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
