<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Services\MarketplaceService;

class MarketplaceAdminController extends Controller
{
    public function __construct(private readonly MarketplaceService $market) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->market->adminBundle()]);
    }

    public function resolveClaim(Request $request, int $id): JsonResponse
    {
        $this->market->resolveClaim($id, (string) $request->input('status', 'closed'), $request->input('admin_note'));

        return response()->json(['ok' => true]);
    }
}
