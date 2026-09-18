<?php

namespace Modules\Custom\MakerBids\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class LayoutFileFixListener implements HookListenerInterface
{
    private const EXISTING_SRC = '/api/modules/custom-maker_bids/assets/existing-files.js?v=0.10.21';

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
        $name = (string) ($layout['layout_name'] ?? '');
        $layout = $this->scrub($layout);
        if (in_array($name, ['jobs_form', 'company_apply', 'jobs_show', 'jobs_edit'], true)
            || str_contains($name, 'jobs_form')
            || str_contains($name, 'company_apply')) {
            $scripts = is_array($layout['scripts'] ?? null) ? $layout['scripts'] : [];
            $found = false;
            foreach ($scripts as $i => $script) {
                if (is_array($script) && str_contains((string) ($script['src'] ?? ''), 'existing-files.js')) {
                    $scripts[$i]['src'] = self::EXISTING_SRC;
                    $found = true;
                }
            }
            if (! $found) {
                $scripts[] = [
                    'id' => 'cmb_maker_existing',
                    'src' => self::EXISTING_SRC,
                    'async' => true,
                    'optional' => true,
                    'required' => false,
                    'failOnError' => false,
                ];
            }
            $layout['scripts'] = $scripts;
        }

        return $layout;
    }

    private function scrub(mixed $node): mixed
    {
        if (is_string($node)) {
            if (str_contains($node, 'Date.now()')) {
                return '1';
            }

            return $node;
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
