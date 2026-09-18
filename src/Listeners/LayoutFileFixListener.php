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

        return $this->injectNameLogos($this->injectListThumbs($this->bindUploaders($this->scrub($layout))));
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
                $props['initialFiles'] = '{{me.data.logo_files || []}}';
            }
            unset($props['key']);
            $node['props'] = $props;
        }
        return $this->walk($node, fn ($c) => $this->bindUploaders($c));
    }

    private function injectListThumbs(array $node): array
    {
        $cls = (string) (($node['props']['className'] ?? ''));
        $nm = (string) ($node['name'] ?? '');
        if ($nm === 'A' && str_contains($cls, 'cmb-job-card')) {
            $node = $this->prependThumb($node, '{{$item.thumbnail_url || ""}}', 'cmb_job_thumb');
        }
        return $this->walk($node, fn ($c) => $this->injectListThumbs($c));
    }

    private function injectNameLogos(array $node): array
    {
        $cls = (string) (($node['props']['className'] ?? ''));
        $id = (string) ($node['id'] ?? '');
        $isName = str_contains($cls, 'cmb-company-card-title') || $id === 'cname';
        $isTop = str_contains($cls, 'cmb-company-card-top') || $id === 'ctop';
        if ($isName || $isTop) {
            $logo = [
                'id' => 'cmb_co_name_logo',
                'type' => 'basic',
                'name' => 'Img',
                'props' => [
                    'src' => '{{$item.thumbnail_url || $item.logo_files[0].thumbnail_url || $item.logo_files[0].url || ""}}',
                    'alt' => '{{$item.name || ""}}',
                    'className' => 'cmb-list-thumb cmb-name-logo',
                    'width' => '40',
                    'height' => '40',
                ],
            ];
            if ($isName) {
                $wrap = [
                    'id' => 'cmb_co_name_row',
                    'type' => 'basic',
                    'name' => 'Div',
                    'props' => [
                        'className' => 'cmb-name-with-logo',
                        'style' => 'display:flex;align-items:center;gap:10px',
                    ],
                    'children' => [$logo, $node],
                ];
                return $this->walk($wrap, fn ($c) => $this->injectNameLogos($c));
            }
            $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
            $has = false;
            foreach ($kids as $child) {
                if (is_array($child) && ($child['id'] ?? '') === 'cmb_co_name_logo') {
                    $has = true;
                }
            }
            if (! $has) {
                array_unshift($kids, $logo);
                $node['children'] = $kids;
                $props = is_array($node['props'] ?? null) ? $node['props'] : [];
                $props['className'] = trim(($props['className'] ?? '').' cmb-name-with-logo');
                $node['props'] = $props;
            }
        }
        return $this->walk($node, fn ($c) => $this->injectNameLogos($c));
    }

    private function prependThumb(array $node, string $srcExpr, string $id): array
    {
        $kids = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($kids as $child) {
            if (is_array($child) && ($child['id'] ?? '') === $id) {
                return $node;
            }
        }
        array_unshift($kids, [
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
        ]);
        $node['children'] = $kids;
        return $node;
    }

    private function walk(array $node, callable $fn): array
    {
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            foreach ($node[$key] as $i => $child) {
                if (is_array($child)) {
                    $node[$key][$i] = $fn($child);
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
                        $node['slots'][$slot][$i] = $fn($child);
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
}
