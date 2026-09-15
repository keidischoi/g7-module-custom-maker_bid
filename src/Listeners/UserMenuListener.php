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
            if (! isset($layout['scripts']) || ! is_array($layout['scripts'])) {
                $layout['scripts'] = [];
            }
            $exists = false;
            foreach ($layout['scripts'] as $script) {
                if (is_array($script) && (($script['id'] ?? '') === 'maker_bid_nav_js')) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $layout['scripts'][] = [
                    'id' => 'maker_bid_nav_js',
                    'src' => '/api/modules/custom-maker_bid/assets/nav.js',
                ];
            }

            return $layout;
        } catch (\Throwable) {
            return $layout;
        }
    }
}
