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
        if (MakerJob::query()->count() === 0) {
            MakerJob::query()->insert([
                [
                    'user_id' => $request->user()?->id,
                    'type' => 'print_3d',
                    'title' => '피규어 3D 출력 의뢰',
                    'description' => 'PLA 기준 높이 15cm 피규어 출력. 서포트 제거 포함.',
                    'budget' => 35000,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $request->user()?->id,
                    'type' => 'design',
                    'title' => '제품 케이스 디자인 의뢰',
                    'description' => '전자기기 케이스 외형 디자인. STEP 파일 납품.',
                    'budget' => 200000,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $request->user()?->id,
                    'type' => 'manufacture',
                    'title' => '소량 사출 시제품 제작',
                    'description' => 'ABS 시제품 20개. 금형은 기존 것 사용.',
                    'budget' => 800000,
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

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
        return response()->json(['data' => MakerJob::query()->with('bids')->findOrFail($id)]);
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
