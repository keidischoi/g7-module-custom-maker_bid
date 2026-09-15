<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class UserMenuListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => [
                'method' => 'filterLayout',
                'priority' => 55,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout.filter_merged' => [
                'method' => 'filterLayout',
                'priority' => 55,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout_extension.after_apply' => [
                'method' => 'filterLayout',
                'priority' => 910,
                'type' => 'filter',
                'sync' => true,
            ],
        ];
    }

    public function handle(...$args): void
    {
    }

    public function filterLayout(mixed $layout = null): mixed
    {
        try {
            if (! is_array($layout)) {
                return $layout;
            }

            return $this->injectHeaderItem($layout);
        } catch (\Throwable) {
            return $layout;
        }
    }

    private function injectHeaderItem(array $node): array
    {
        $id = (string) ($node['id'] ?? '');
        $name = (string) ($node['name'] ?? '');

        if ($id === 'desktop_header' || $name === 'Header') {
            if (! isset($node['props']) || ! is_array($node['props'])) {
                $node['props'] = [];
            }
            $boards = $node['props']['boards'] ?? '{{boards.data ?? []}}';
            if (is_string($boards) && ! str_contains($boards, 'maker-bid')) {
                $node['props']['boards'] = '{{[...(boards.data ?? []), {name:{ko:"의뢰/입찰",en:"Jobs"},slug:"maker-bid",url:"/maker-bid"}]}}';
            }
            if (is_array($boards)) {
                $exists = false;
                foreach ($boards as $row) {
                    if (is_array($row) && (($row['slug'] ?? '') === 'maker-bid' || ($row['url'] ?? '') === '/maker-bid')) {
                        $exists = true;
                        break;
                    }
                }
                if (! $exists) {
                    $boards[] = [
                        'name' => ['ko' => '의뢰/입찰', 'en' => 'Jobs'],
                        'slug' => 'maker-bid',
                        'url' => '/maker-bid',
                    ];
                    $node['props']['boards'] = $boards;
                }
            }
        }

        foreach (['children', 'slots', 'content'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            if (array_is_list($node[$key])) {
                foreach ($node[$key] as $i => $child) {
                    if (is_array($child)) {
                        $node[$key][$i] = $this->injectHeaderItem($child);
                    }
                }
            } else {
                foreach ($node[$key] as $i => $child) {
                    if (is_array($child)) {
                        $node[$key][$i] = $this->injectHeaderItem($child);
                    }
                }
            }
        }

        return $node;
    }
}
