<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Requests\Admin\UpdateJobRequest;
use Modules\Custom\MakerBids\Services\JobService;

class JobAdminController extends Controller
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listAdmin($request)]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->findAdmin($id)]);
    }

    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->updateAdmin($id, $request->validated())]);
    }

    public function approve(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->approve($id)]);
    }

    public function hold(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->hold($id)]);
    }

    public function cancel(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->cancel($id)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->jobs->destroy($id);

        return response()->json(['ok' => true]);
    }
}
