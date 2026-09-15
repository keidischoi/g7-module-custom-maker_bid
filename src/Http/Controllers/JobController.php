<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerJob;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = MakerJob::query()->withCount('bids')->latest();
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json(['data' => $q->limit(100)->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $job = MakerJob::query()->with('bids')->findOrFail($id);

        return response()->json(['data' => $job]);
    }

    public function store(Request $request): JsonResponse
    {
        $job = MakerJob::query()->create([
            'user_id' => $request->user()?->id,
            'type' => $request->input('type', 'print_3d'),
            'title' => $request->input('title', '새 의뢰'),
            'description' => $request->input('description'),
            'budget' => $request->input('budget'),
            'status' => 'open',
            'closes_at' => $request->input('closes_at'),
        ]);

        return response()->json(['data' => $job], 201);
    }

    public function bid(Request $request, int $id): JsonResponse
    {
        $job = MakerJob::query()->findOrFail($id);
        if ($job->status !== 'open') {
            return response()->json(['message' => '입찰이 마감된 의뢰입니다.'], 422);
        }

        $bid = MakerBid::query()->updateOrCreate(
            ['job_id' => $job->id, 'user_id' => (int) $request->user()?->id],
            [
                'amount' => (int) $request->input('amount', 0),
                'days' => $request->input('days'),
                'message' => $request->input('message'),
                'status' => 'pending',
            ]
        );

        return response()->json(['data' => $bid], 201);
    }

    public function award(Request $request, int $id): JsonResponse
    {
        $job = MakerJob::query()->findOrFail($id);
        if ((int) $job->user_id !== (int) $request->user()?->id && ! $request->user()?->hasRole('admin')) {
            return response()->json(['message' => '의뢰자만 내정할 수 있습니다.'], 403);
        }

        $bid = MakerBid::query()->where('job_id', $job->id)->findOrFail((int) $request->input('bid_id'));
        MakerBid::query()->where('job_id', $job->id)->update(['status' => 'rejected']);
        $bid->status = 'accepted';
        $bid->save();
        $job->status = 'awarded';
        $job->awarded_bid_id = $bid->id;
        $job->save();

        return response()->json(['data' => $job->fresh('bids')]);
    }
}
