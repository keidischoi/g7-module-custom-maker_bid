<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Http\Requests\StoreBidRequest;
use Modules\Custom\MakerBids\Http\Requests\UpdateBidRequest;
use Modules\Custom\MakerBids\Services\BidService;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Support\ArrayPaginator;
use Modules\Custom\MakerBids\Support\CompanyPresenter;
use Modules\Custom\MakerBids\Support\DomainException;

class BidController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly BidService $bids,
        private readonly JobService $jobs,
    ) {}

    private function actor(Request $request)
    {
        return $request->user()
            ?? Auth::guard('web')->user()
            ?? Auth::guard('sanctum')->user();
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        if (! $user) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
        $items = $this->bids->listMine((int) $user->id);
        $list = [];
        foreach ($items as $bid) {
            $row = CompanyPresenter::presentBid($bid);
            $job = $bid->relationLoaded('job') ? $bid->job : null;
            $row['job'] = $job ? [
                'id' => (int) $job->id,
                'title' => (string) $job->title,
                'status' => (string) $job->status,
            ] : null;
            $list[] = $row;
        }

        return response()->json(ArrayPaginator::paginate($list, $request, 'page', 10));
    }

    public function store(StoreBidRequest $request, int $id): JsonResponse
    {
        $user = $this->actor($request);
        if (! $user) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
        try {
            $result = $this->bids->createOrUpdateOwn(
                (int) $user->id,
                $id,
                $request->validated(),
                $this->jobs->isAdminActor($user),
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $result['bid']], $result['created'] ? 201 : 200);
    }

    public function update(UpdateBidRequest $request, int $id, int $bidId): JsonResponse
    {
        $user = $this->actor($request);
        if (! $user) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
        try {
            $bid = $this->bids->updateOwn(
                (int) $user->id,
                $id,
                $bidId,
                $request->validated(),
                $this->jobs->isAdminActor($user),
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $bid]);
    }
}
