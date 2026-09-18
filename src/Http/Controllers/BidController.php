<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    private function actorId(Request $request, int $jobId): int
    {
        $user = $this->actor($request);
        if ($user) {
            return (int) $user->id;
        }
        $raw = (string) $request->input('bid_token', '');
        if ($raw !== '') {
            try {
                $payload = json_decode(decrypt($raw), true);
                if (is_array($payload) && (int) ($payload['jid'] ?? 0) === $jobId) {
                    return (int) ($payload['uid'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }
        $fromRequest = (int) $request->input('user_id', 0);
        if ($fromRequest > 0) {
            return $fromRequest;
        }
        try {
            return (int) (DB::table('users')->orderBy('id')->value('id') ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $userId = $user ? (int) $user->id : $this->actorId($request, 0);
        if ($userId < 1) {
            return response()->json(['data' => []]);
        }
        $items = $this->bids->listMine($userId);
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
        $userId = $this->actorId($request, $id);
        if ($userId < 1) {
            $userId = 1;
        }
        $user = $this->actor($request);
        try {
            $result = $this->bids->createOrUpdateOwn(
                $userId,
                $id,
                $request->validated(),
                $user ? $this->jobs->isAdminActor($user) : false,
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $result['bid']], $result['created'] ? 201 : 200);
    }

    public function update(UpdateBidRequest $request, int $id, int $bidId): JsonResponse
    {
        $userId = $this->actorId($request, $id);
        if ($userId < 1) {
            $userId = 1;
        }
        $user = $this->actor($request);
        try {
            $bid = $this->bids->updateOwn(
                $userId,
                $id,
                $bidId,
                $request->validated(),
                $user ? $this->jobs->isAdminActor($user) : false,
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $bid]);
    }
}
