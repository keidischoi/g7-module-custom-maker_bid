<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;
use Modules\Custom\MakerBids\Support\SettingsRules;

class UserMenuListener implements HookListenerInterface
{
    private const NAV_SRC = '/api/modules/custom-maker_bids/assets/nav.js?v=0.9.10';
    private const FORM_SRC = '/api/modules/custom-maker_bids/assets/form.js?v=0.9.10';
    private const PAGE_SRC = '/api/modules/custom-maker_bids/assets/page.js?v=0.9.10';
    private const FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.9.10';
    private const ADMIN_CSS = '/api/modules/custom-maker_bids/assets/admin.css?v=0.9.10';
    private const ADMIN_JS = '/api/modules/custom-maker_bids/assets/admin.js?v=0.9.10';

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
            if (($layout['extends'] ?? '') === '_admin_base') {
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $styles = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
                $layout['styles'] = $this->upsertStyle($styles, 'cmb_maker_admin_css', self::ADMIN_CSS);
                $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
                $scripts = $this->upsertScript($scripts, 'cmb_maker_admin_js', self::ADMIN_JS);
                $layout['scripts'] = $this->upsertScript($scripts, 'cmb_maker_page', self::PAGE_SRC);

                return $layout;
            }
            if (str_starts_with($name, 'admin') || str_contains($name, 'admin')) {
                return $layout;
            }
            $menu = $this->menuSettings();
            if (! ($menu['extension_user_base'] ?? true)) {
                $layout = $this->removeComponent($layout, 'maker_bids_user_nav');
            }
            if (! ($menu['extension_home'] ?? true)) {
                $layout = $this->removeComponent($layout, 'maker_bids_home_nav');
            }
            $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
            $scripts = $this->upsertScript($scripts, 'cmb_maker_nav', self::NAV_SRC);
            $scripts = $this->upsertScript($scripts, 'cmb_maker_page', self::PAGE_SRC);
            if (in_array($name, ['jobs_create', 'jobs_edit', 'company_apply', 'jobs_show'], true)) {
                $scripts = $this->upsertScript($scripts, 'cmb_maker_form', self::FORM_SRC);
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $layout['styles'] = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
            }
            $layout['scripts'] = $scripts;
        } catch (\Throwable) {
        }

        return $layout;
    }

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

    private function removeComponent(array $node, string $id): array
    {
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) continue;
            $kept = [];
            foreach ($node[$key] as $child) {
                if (! is_array($child)) { $kept[] = $child; continue; }
                if (($child['id'] ?? '') === $id) continue;
                $kept[] = $this->removeComponent($child, $id);
            }
            $node[$key] = array_values($kept);
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) continue;
                $kept = [];
                foreach ($items as $child) {
                    if (! is_array($child)) { $kept[] = $child; continue; }
                    if (($child['id'] ?? '') === $id) continue;
                    $kept[] = $this->removeComponent($child, $id);
                }
                $node['slots'][$slot] = array_values($kept);
            }
        }
        return $node;
    }

    private function upsertScript(array $scripts, string $id, string $src): array
    {
        $found = false;
        $needles = [
            'cmb_maker_nav' => 'custom-maker_bids/assets/nav.js',
            'cmb_maker_form' => 'custom-maker_bids/assets/form.js',
            'cmb_maker_page' => 'custom-maker_bids/assets/page.js',
            'cmb_maker_admin_js' => 'custom-maker_bids/assets/admin.js',
        ];
        $needle = $needles[$id] ?? $id;
        foreach ($scripts as $i => $script) {
            if (is_array($script) && (($script['id'] ?? '') === $id || str_contains((string) ($script['src'] ?? ''), $needle))) {
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

    private function upsertStyle(array $styles, string $id, string $href): array
    {
        $found = false;
        $path = (string) (parse_url($href, PHP_URL_PATH) ?: $href);
        $needle = str_contains($path, 'admin.css') ? 'custom-maker_bids/assets/admin.css' : 'custom-maker_bids/assets/form.css';
        foreach ($styles as $i => $style) {
            $existing = (string) ($style['href'] ?? $style['src'] ?? '');
            if (is_array($style) && (($style['id'] ?? '') === $id || str_contains($existing, $needle))) {
                $styles[$i]['href'] = $href;
                $styles[$i]['src'] = $href;
                $found = true;
            }
        }
        if (! $found) {
            $styles[] = ['id' => $id, 'href' => $href, 'src' => $href, 'rel' => 'stylesheet'];
        }
        return $styles;
    }
}
