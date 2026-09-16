<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Requests\Admin\UpdateBidRequest;
use Modules\Custom\MakerBids\Services\BidService;

class BidAdminController extends Controller
{
    public function __construct(
        private readonly BidService $bids,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->bids->listAdmin($request)]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->bids->findAdmin($id)]);
    }

    public function update(UpdateBidRequest $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->bids->updateAdmin($id, $request->validated())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->bids->destroyAdmin($id);

        return response()->json(['ok' => true]);
    }
}
