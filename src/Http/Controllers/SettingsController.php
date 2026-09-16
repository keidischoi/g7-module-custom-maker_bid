<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;

class SettingsController extends Controller
{
    public function __construct(
        private readonly MakerBidSettingsService $settings,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicPayload()]);
    }
}
