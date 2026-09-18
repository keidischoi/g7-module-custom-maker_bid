<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;
use Modules\Custom\MakerBids\Support\SettingsRules;

class UserMenuListener implements HookListenerInterface
{
    private const NAV_SRC = '/api/modules/custom-maker_bids/assets/nav.js?v=0.10.34';
    private const FORM_SRC = '/api/modules/custom-maker_bids/assets/form.js?v=0.10.34';
    private const PAGE_SRC = '/api/modules/custom-maker_bids/assets/page.js?v=0.10.34';
    private const FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.10.34';
    private const ADMIN_CSS = '/api/modules/custom-maker_bids/assets/admin.css?v=0.10.34';
    private const ADMIN_JS = '/api/modules/custom-maker_bids/assets/admin.js?v=0.10.34';

    private const JOB_FIELDS = [
        'title', 'type', 'status', 'audience', 'budget_min', 'budget_max', 'description',
        'closes_at', 'rush_deadline', 'size_w', 'size_d', 'size_h', 'sizes_json',
        'contact_name', 'contact_phone', 'contact_hours', 'contact_email',
        'zipcode', 'address', 'address_detail', 'manager_name', 'manager_phone', 'manager_email',
        'refund_bank_name', 'refund_account_holder', 'refund_account_no',
        'revision_count', 'revision_cost',
    ];

    private const COMPANY_FIELDS = [
        'kind', 'name', 'business_no', 'bio', 'homepage_url', 'portfolio_url',
        'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
        'bank_name', 'account_no', 'account_holder', 'deposit_percent', 'deposit_terms',
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
            if (in_array($name, ['jobs_form', 'jobs_edit', 'jobs_show'], true) || str_contains($name, 'jobs_show') || str_contains($name, 'jobs_edit') || str_contains($name, 'jobs_form')) {
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
                $layout = $this->ensureAdminNav($layout);
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
                'jobs_form', 'jobs_list', 'jobs_show', 'jobs_bids', 'jobs_history', 'jobs_notices',
                'jobs_workspace', 'jobs_disputes', 'jobs_payments', 'company_apply', 'company_list',
            ];
            if (in_array($name, $publicLayouts, true)) {
                $styles = is_array($layout['styles'] ?? null) ? $layout['styles'] : [];
                $styles = $this->upsertStyle($styles, 'cmb_maker_form_css', self::FORM_CSS);
                $layout['styles'] = $this->preferStyleFirst($styles, 'cmb_maker_form_css');
            }
            if (in_array($name, ['jobs_form', 'company_apply', 'jobs_show'], true)) {
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


    /**
     * Shared admin chrome: one 9-link nav (includes ops·분쟁조정·결제). Layouts keep an empty
     * #cmb_admin_nav / .cmb-admin-nav stub; this fills children so pages stop
     * duplicating the link list.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private function ensureAdminNav(array $layout): array
    {
        $children = $this->adminNavChildren();
        $filled = false;
        $layout = $this->fillAdminNavNode($layout, $children, $filled);
        if (! $filled) {
            $layout = $this->insertAdminNavStub($layout, $children);
        }

        return $layout;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adminNavChildren(): array
    {
        $links = [
            ['cmb_an_jobs', '/admin/maker-bids', '의뢰 목록'],
            ['cmb_an_types', '/admin/maker-bids/types', '유형 관리'],
            ['cmb_an_bids', '/admin/maker-bids/bids', '입찰 관리'],
            ['cmb_an_cos', '/admin/maker-bids/companies', '회사 목록'],
            ['cmb_an_ops', '/admin/maker-bids/ops', '운영'],
            ['cmb_an_disputes', '/admin/maker-bids/disputes', '분쟁조정'],
            ['cmb_an_pay', '/admin/maker-bids/payments', '결제'],
            ['cmb_an_act', '/admin/maker-bids/activity', '회원 활동'],
            ['cmb_an_set', '/admin/maker-bids/settings', '설정'],
        ];
        $out = [];
        foreach ($links as [$id, $href, $label]) {
            $out[] = [
                'id' => $id,
                'type' => 'basic',
                'name' => 'A',
                'props' => ['href' => $href],
                'text' => $label,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function fillAdminNavNode(array $node, array $children, bool &$filled): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $cls = (string) ($props['className'] ?? '');
        $id = (string) ($node['id'] ?? '');
        if (($node['name'] ?? '') === 'Div' && ($id === 'cmb_admin_nav' || str_contains($cls, 'cmb-admin-nav'))) {
            $node['id'] = 'cmb_admin_nav';
            $props['className'] = 'cmb-admin-nav text-sm';
            $props['data-cmb-admin-nav'] = '1';
            $node['props'] = $props;
            $node['children'] = $children;
            $filled = true;

            return $node;
        }
        foreach (['children', 'injections', 'components'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }
            foreach ($node[$key] as $i => $child) {
                if (is_array($child)) {
                    $node[$key][$i] = $this->fillAdminNavNode($child, $children, $filled);
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
                        $node['slots'][$slot][$i] = $this->fillAdminNavNode($child, $children, $filled);
                    }
                }
            }
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function insertAdminNavStub(array $layout, array $children): array
    {
        $stub = [
            'id' => 'cmb_admin_nav',
            'type' => 'basic',
            'name' => 'Div',
            'props' => ['className' => 'cmb-admin-nav text-sm', 'data-cmb-admin-nav' => '1'],
            'children' => $children,
        ];
        if (! isset($layout['slots']['content']) || ! is_array($layout['slots']['content'])) {
            return $layout;
        }
        $layout['slots']['content'] = $this->insertAfterFirstH1($layout['slots']['content'], $stub);

        return $layout;
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  array<string, mixed>  $stub
     * @return list<mixed>
     */
    private function insertAfterFirstH1(array $nodes, array $stub): array
    {
        foreach ($nodes as $i => $child) {
            if (! is_array($child)) {
                continue;
            }
            if (($child['name'] ?? '') === 'H1') {
                array_splice($nodes, $i + 1, 0, [$stub]);

                return $nodes;
            }
            if (isset($child['children']) && is_array($child['children'])) {
                $before = $child['children'];
                $child['children'] = $this->insertAfterFirstH1($child['children'], $stub);
                $nodes[$i] = $child;
                if ($child['children'] !== $before) {
                    return $nodes;
                }
            }
        }

        return $nodes;
    }


    /**
     * Move a stylesheet to the front of the list so critical CSS paints sooner.
     *
     * @param  list<array<string, mixed>>  $styles
     * @return list<array<string, mixed>>
     */
    private function preferStyleFirst(array $styles, string $id): array
    {
        $picked = null;
        $rest = [];
        foreach ($styles as $style) {
            if (is_array($style) && ($style['id'] ?? '') === $id) {
                $picked = $style;
                continue;
            }
            $rest[] = $style;
        }
        if ($picked === null) {
            return $styles;
        }

        return array_values(array_merge([$picked], $rest));
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
        // nav.js boot CSS must run early to kill white FOUC — keep it sync.
        $async = $id !== 'cmb_maker_nav';
        foreach ($scripts as $i => $script) {
            if (is_array($script) && (($script['id'] ?? '') === $id || str_contains((string) ($script['src'] ?? ''), $needle))) {
                $scripts[$i]['id'] = $id;
                $scripts[$i]['src'] = $src;
                $scripts[$i]['async'] = $async;
                $found = true;
            }
        }
        if (! $found) {
            $scripts[] = ['id' => $id, 'src' => $src, 'async' => $async, 'optional' => true, 'required' => false, 'failOnError' => false, 'onError' => ['handler' => 'suppress']];
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
