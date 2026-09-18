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
        $layout = $this->scrub($layout);

        return $this->injectListThumbs($this->bindUploaders($layout));
    }

    private function bindUploaders(array $node): array
    {
        if (($node['name'] ?? '') === 'FileUploader') {
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $collection = (string) ($props['collection'] ?? '');
            $id = (string) ($node['id'] ?? '');
            if ($collection === 'images' || str_contains($id, 'images')) {
                $props['initialFiles'] = '{{job.data.images || []}}';
            } elseif ($collection === 'archives' || str_contains($id, 'archives')) {
                $props['initialFiles'] = '{{job.data.archives || []}}';
            } elseif ($collection === 'logos' || str_contains($id, 'logo')) {
                $props['initialFiles'] = '{{me.data.logo_files || me.data.logo_file || []}}';
            }
            unset($props['key']);
            $node['props'] = $props;
        }
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            foreach ($node[$key] as $i => $child) {
                if (is_array($child)) {
                    $node[$key][$i] = $this->bindUploaders($child);
                }
            }
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $i => $child) {
                    if (is_array($child)) {
                        $node['slots'][$slot][$i] = $this->bindUploaders($child);
                    }
                }
            }
        }

        return $node;
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

    private function injectListThumbs(array $node): array
    {
        $cls = (string) (($node['props']['className'] ?? ''));
        $name = (string) ($node['name'] ?? '');
        if ($name === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->prependThumb($node, '{{$item.thumbnail_url || ""}}', 'cmb_job_thumb');
        }
        if ($name === 'A' && str_contains($cls, 'cmb-company-card')) {
            $node = $this->prependThumb($node, '{{$item.logo_url || ""}}', 'cmb_co_thumb');
        }
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            foreach ($node[$key] as $i => $child) {
                if (is_array($child)) {
                    $node[$key][$i] = $this->injectListThumbs($child);
                }
            }
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $i => $child) {
                    if (is_array($child)) {
                        $node['slots'][$slot][$i] = $this->injectListThumbs($child);
                    }
                }
            }
        }

        return $node;
    }

    private function prependThumb(array $node, string $srcExpr, string $id): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $cls = (string) ($props['className'] ?? '');
        if (! str_contains($cls, 'cmb-card-with-thumb')) {
            $props['className'] = trim($cls.' cmb-card-with-thumb');
        }
        $node['props'] = $props;
        $thumb = [
            'id' => $id,
            'type' => 'basic',
            'name' => 'Img',
            'props' => [
                'src' => $srcExpr,
                'alt' => '',
                'className' => 'cmb-list-thumb',
                'width' => '64',
                'height' => '64',
            ],
        ];
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $child) {
            if (is_array($child) && ($child['id'] ?? '') === $id) {
                return $node;
            }
        }
        array_unshift($kids, $thumb);
        $node['children'] = $kids;

        return $node;
    }
}
