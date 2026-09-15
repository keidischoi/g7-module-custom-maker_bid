<?php

namespace Modules\Custom\MakerBid\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Models\MakerJob;

class JobAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MakerJob::query()->withCount('bids')->latest()->limit(200)->get(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->fill($request->only(['title', 'description', 'budget', 'type', 'status']));
        $job->save();

        return response()->json(['data' => $job]);
    }

    public function hold(int $id): JsonResponse
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->status = 'hold';
        $job->save();

        return response()->json(['data' => $job]);
    }

    public function destroy(int $id): JsonResponse
    {
        MakerJob::query()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
