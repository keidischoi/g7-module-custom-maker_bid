<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Requests\Admin\UpdateSettingsRequest;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;
use Modules\Custom\MakerBids\Support\SettingsRules;

class SettingsAdminController extends Controller
{
    public function __construct(
        private readonly MakerBidSettingsService $settings,
    ) {}

    public function show(): JsonResponse
    {
        $form = $this->settings->adminForm();

        return response()->json([
            'data' => $form,
            'success' => true,
            'pages' => SettingsRules::pages(),
            'nav_inserts' => SettingsRules::NAV_INSERTS,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $form = $this->settings->saveFromInput($request->all());
        $this->fireAfterSave($form);

        return response()->json([
            'data' => $form,
            'success' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function fireAfterSave(array $form): void
    {
        $payload = SettingsRules::unflatten($form);
        foreach ([
            'App\\Core\\Hook\\HookManager',
            'App\\Services\\HookManager',
            'App\\Support\\HookManager',
        ] as $class) {
            if (! class_exists($class) || ! method_exists($class, 'doAction')) {
                continue;
            }
            try {
                $class::doAction('core.module_settings.after_save', SettingsRules::MODULE_ID, $payload, true);
            } catch (\Throwable) {
            }
            break;
        }
    }
}
