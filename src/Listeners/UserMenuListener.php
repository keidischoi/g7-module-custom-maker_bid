<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class UserMenuListener implements HookListenerInterface
{
    private const SCRIPT_SRC = '/api/modules/custom-maker_bid/assets/nav.js?v=0.3.0';

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => ['method' => 'patch', 'priority' => 46, 'type' => 'filter', 'sync' => true],
            'core.layout.filter_merged' => ['method' => 'patch', 'priority' => 46, 'type' => 'filter', 'sync' => true],
            'core.layout_extension.after_apply' => ['method' => 'patch', 'priority' => 46, 'type' => 'filter', 'sync' => true],
        ];
    }

    public function handle(...$args): void {}

    public function patch(mixed $layout = null): mixed
    {
        try {
            if (! is_array($layout)) {
                return $layout;
            }
            $name = (string) ($layout['layout_name'] ?? '');
            if (str_starts_with($name, 'admin') || str_contains($name, 'admin')) {
                return $layout;
            }
            $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
            $found = false;
            foreach ($scripts as $i => $script) {
                if (is_array($script) && (($script['id'] ?? '') === 'cmb_maker_nav' || str_contains((string) ($script['src'] ?? ''), 'custom-maker_bid/assets/nav.js'))) {
                    $scripts[$i]['src'] = self::SCRIPT_SRC;
                    $found = true;
                }
            }
            if (! $found) {
                $scripts[] = [
                    'id' => 'cmb_maker_nav',
                    'src' => self::SCRIPT_SRC,
                    'async' => true,
                    'optional' => true,
                    'required' => false,
                    'failOnError' => false,
                    'onError' => ['handler' => 'suppress'],
                ];
            }
            $layout['scripts'] = $scripts;
        } catch (\Throwable) {
        }

        return $layout;
    }
}
