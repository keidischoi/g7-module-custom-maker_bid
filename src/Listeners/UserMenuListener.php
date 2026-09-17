<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;
use Modules\Custom\MakerBids\Support\SettingsRules;

class UserMenuListener implements HookListenerInterface
{
    private const NAV_SRC = '/api/modules/custom-maker_bids/assets/nav.js?v=0.9.8';
    private const FORM_SRC = '/api/modules/custom-maker_bids/assets/form.js?v=0.9.8';
    private const PAGE_SRC = '/api/modules/custom-maker_bids/assets/page.js?v=0.9.8';
    private const FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.9.8';
    private const ADMIN_CSS = '/api/modules/custom-maker_bids/assets/admin.css?v=0.9.8';
    private const ADMIN_JS = '/api/modules/custom-maker_bids/assets/admin.js?v=0.9.8';

    private const JOB_FIELDS = [
        'title', 'type', 'status', 'audience', 'budget_min', 'budget_max', 'description',
        'closes_at', 'rush_deadline', 'size_w', 'size_d', 'size_h', 'sizes_json',
        'contact_name', 'contact_phone', 'contact_hours', 'contact_email',
        'zipcode', 'address', 'address_detail', 'manager_name', 'manager_phone', 'manager_email',
        'revision_count', 'revision_cost',
    ];

    private const COMPANY_FIELDS = [
        'kind', 'name', 'business_no', 'bio', 'homepage_url', 'portfolio_url',
        'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
    ];

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
            if (! is_array($layout)) return $layout;
            $name = (string) ($layout['layout_name'] ?? '');
            if (in_array($name, ['jobs_edit', 'jobs_show'], true) || str_contains($name, 'jobs_show') || str_contains($name, 'jobs_edit')) {
                $layout = $this->bindNamedValues($layout, self::JOB_FIELDS, 'job.data');
            }
            if ($name === 'company_apply') {
                $layout = $this->bindNamedValues($layout, self::COMPANY_FIELDS, 'me.data');
            }
            if (($layout['extends'] ?? '') === '_admin_base') {
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $styles = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
                $layout['styles'] = $this->upsertStyle($styles, 'cmb_maker_admin_css', self::ADMIN_CSS);
                $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
                $scripts = $this->upsertScript($scripts, 'cmb_maker_admin_js', self::ADMIN_JS);
                $layout['scripts'] = $this->upsertScript($scripts, 'cmb_maker_page', self::PAGE_SRC);
                if (in_array($name, ['jobs_show', 'jobs_index'], true)) {
                    $layout = $this->bindNamedValues($layout, self::JOB_FIELDS, 'job.data');
                }
                return $layout;
            }
            if (str_starts_with($name, 'admin') || str_contains($name, 'admin')) return $layout;
            $menu = $this->menuSettings();
            if (! ($menu['extension_user_base'] ?? true)) $layout = $this->removeComponent($layout, 'maker_bids_user_nav');
            if (! ($menu['extension_home'] ?? true)) $layout = $this->removeComponent($layout, 'maker_bids_home_nav');
            $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
            $scripts = $this->upsertScript($scripts, 'cmb_maker_nav', self::NAV_SRC);
            $scripts = $this->upsertScript($scripts, 'cmb_maker_page', self::PAGE_SRC);
            $publicLayouts = [
                'jobs_create', 'jobs_edit', 'jobs_list', 'jobs_show', 'jobs_bids', 'jobs_history',
                'jobs_workspace', 'company_apply', 'company_list',
            ];
            if (in_array($name, $publicLayouts, true)) {
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $layout['styles'] = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
            }
            if (in_array($name, ['jobs_create', 'jobs_edit', 'company_apply', 'jobs_show'], true)) {
                $scripts = $this->upsertScript($scripts, 'cmb_maker_form', self::FORM_SRC);
            }
            $layout['scripts'] = $scripts;
        } catch (\Throwable) {}
        return $layout;
    }

    private function bindNamedValues(array $node, array $fields, string $source): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $field = (string) ($props['name'] ?? '');
        if ($field !== '' && in_array($field, $fields, true)) {
            $srcField = $field === 'closes_at' ? 'closes_at_local' : $field;
            $expr = '{{_local.form.'.$field.' || '.$source.'.'.$srcField.' || '.$source.'.'.$field.' || ""}}';
            if ($source === 'me.data') $expr = '{{_local.company.'.$field.' || me.data.'.$field.' || ""}}';
            $props['value'] = $expr;
            $node['props'] = $props;
        }
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) continue;
            foreach ($node[$key] as $i => $child) {
                if (is_array($child)) $node[$key][$i] = $this->bindNamedValues($child, $fields, $source);
            }
        }
        if (isset($node['slots']) && is_array($node['slots'])) {
            foreach ($node['slots'] as $slot => $items) {
                if (! is_array($items)) continue;
                foreach ($items as $i => $child) {
                    if (is_array($child)) $node['slots'][$slot][$i] = $this->bindNamedValues($child, $fields, $source);
                }
            }
        }
        return $node;
    }

    private function menuSettings(): array
    {
        try {
            if (function_exists('app')) {
                $all = app(MakerBidSettingsService::class)->getAllSettings();
                return is_array($all['menu'] ?? null) ? $all['menu'] : SettingsRules::defaults()['menu'];
            }
        } catch (\Throwable) {}
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
            $scripts[] = ['id' => $id, 'src' => $src, 'async' => true, 'optional' => true, 'required' => false, 'failOnError' => false, 'onError' => ['handler' => 'suppress']];
        }
        return $scripts;
    }

    private function upsertStyle(array $styles, string $id, string $href): array
    {
        $found = false;
        $needle = str_contains($href, 'admin.css') ? 'custom-maker_bids/assets/admin.css' : 'custom-maker_bids/assets/form.css';
        foreach ($styles as $i => $style) {
            $existing = (string) ($style['href'] ?? $style['src'] ?? '');
            if (is_array($style) && (($style['id'] ?? '') === $id || str_contains($existing, $needle))) {
                $styles[$i]['href'] = $href;
                $styles[$i]['src'] = $href;
                $found = true;
            }
        }
        if (! $found) $styles[] = ['id' => $id, 'href' => $href, 'src' => $href, 'rel' => 'stylesheet'];
        return $styles;
    }
}
