<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\MakerBid\Services\MakerBidSettingsService;
use Modules\Custom\MakerBid\Support\SettingsRules;

class UserMenuListener implements HookListenerInterface
{
    private const NAV_SRC = '/api/modules/custom-maker_bid/assets/nav.js?v=0.7.2';

    private const FORM_SRC = '/api/modules/custom-maker_bid/assets/form.js?v=0.7.2';

    private const FORM_CSS = '/api/modules/custom-maker_bid/assets/form.css?v=0.7.2';

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
            $menu = $this->menuSettings();
            if (! ($menu['extension_user_base'] ?? true)) {
                $layout = $this->removeComponent($layout, 'maker_bid_user_nav');
            }
            if (! ($menu['extension_home'] ?? true)) {
                $layout = $this->removeComponent($layout, 'maker_bid_home_nav');
            }
            $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
            $scripts = $this->upsertScript($scripts, 'cmb_maker_nav', self::NAV_SRC);
            if (in_array($name, ['jobs_create', 'jobs_edit', 'company_apply'], true)) {
                $scripts = $this->upsertScript($scripts, 'cmb_maker_form', self::FORM_SRC);
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $layout['styles'] = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
            }
            $layout['scripts'] = $scripts;
        } catch (\Throwable) {
        }

        return $layout;
    }

    /**
     * @return array<string, mixed>
     */
    private function menuSettings(): array
    {
        try {
            if (function_exists('app')) {
                $all = app(MakerBidSettingsService::class)->getAllSettings();

                return is_array($all['menu'] ?? null) ? $all['menu'] : SettingsRules::defaults()['menu'];
            }
        } catch (\Throwable) {
        }

        return SettingsRules::defaults()['menu'];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function removeComponent(array $node, string $id): array
    {
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            $kept = [];
            foreach ($node[$key] as $child) {
                if (! is_array($child)) {
                    $kept[] = $child;
                    continue;
                }
                if (($child['id'] ?? '') === $id) {
                    continue;
                }
                $kept[] = $this->removeComponent($child, $id);
            }
            $node[$key] = array_values($kept);
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) {
                    continue;
                }
                $kept = [];
                foreach ($items as $child) {
                    if (! is_array($child)) {
                        $kept[] = $child;
                        continue;
                    }
                    if (($child['id'] ?? '') === $id) {
                        continue;
                    }
                    $kept[] = $this->removeComponent($child, $id);
                }
                $node['slots'][$slot] = array_values($kept);
            }
        }

        return $node;
    }

    /**
     * @param  list<array<string, mixed>>  $scripts
     * @return list<array<string, mixed>>
     */
    private function upsertScript(array $scripts, string $id, string $src): array
    {
        $found = false;
        foreach ($scripts as $i => $script) {
            if (is_array($script) && (($script['id'] ?? '') === $id || str_contains((string) ($script['src'] ?? ''), $id === 'cmb_maker_nav' ? 'custom-maker_bid/assets/nav.js' : 'custom-maker_bid/assets/form.js'))) {
                $scripts[$i]['src'] = $src;
                $found = true;
            }
        }
        if (! $found) {
            $scripts[] = [
                'id' => $id,
                'src' => $src,
                'async' => true,
                'optional' => true,
                'required' => false,
                'failOnError' => false,
                'onError' => ['handler' => 'suppress'],
            ];
        }

        return $scripts;
    }

    /**
     * @param  list<array<string, mixed>>  $styles
     * @return list<array<string, mixed>>
     */
    private function upsertStyle(array $styles, string $id, string $href): array
    {
        $found = false;
        foreach ($styles as $i => $style) {
            if (is_array($style) && (($style['id'] ?? '') === $id || str_contains((string) ($style['href'] ?? $style['src'] ?? ''), 'custom-maker_bid/assets/form.css'))) {
                $styles[$i]['href'] = $href;
                $styles[$i]['src'] = $href;
                $found = true;
            }
        }
        if (! $found) {
            $styles[] = [
                'id' => $id,
                'href' => $href,
                'src' => $href,
                'rel' => 'stylesheet',
            ];
        }

        return $styles;
    }
}
