<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class UserMenuListener implements HookListenerInterface
{
    private const NAV_SRC = '/api/modules/custom-maker_bid/assets/nav.js?v=0.5.0';

    private const FORM_SRC = '/api/modules/custom-maker_bid/assets/form.js?v=0.5.0';

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
            $scripts = $this->upsertScript($scripts, 'cmb_maker_nav', self::NAV_SRC);
            if (in_array($name, ['jobs_create', 'jobs_edit'], true)) {
                $scripts = $this->upsertScript($scripts, 'cmb_maker_form', self::FORM_SRC);
            }
            $layout['scripts'] = $scripts;
        } catch (\Throwable) {
        }

        return $layout;
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
}
