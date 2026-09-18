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

    private function actorId(Request $request, int $jobId): int
    {
        $user = $this->actor($request);
        if ($user) {
            return (int) $user->id;
        }
        $raw = (string) $request->input('bid_token', '');
        if ($raw === '') {
            return 0;
        }
        try {
            $payload = json_decode(decrypt($raw), true);
        } catch (\Throwable) {
            return 0;
        }
        if (! is_array($payload) || (int) ($payload['jid'] ?? 0) !== $jobId) {
            return 0;
        }
        if ((int) ($payload['exp'] ?? 0) < time()) {
            return 0;
        }

        return (int) ($payload['uid'] ?? 0);
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

    public function revisions(Request $request, int $id): JsonResponse
    {
        $user = $this->actor($request);
        if (! $user) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
        $admin = $this->jobs->isAdminActor($user);
        $rows = $this->bids->listRevisions($id, (int) $user->id, $admin);

        return response()->json(['data' => $rows]);
    }

    public function store(StoreBidRequest $request, int $id): JsonResponse
    {
        $user = $this->actor($request);
        $userId = $this->actorId($request, $id);
        if ($userId < 1) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
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

        return response()->json(['success' => true, 'data' => $result['bid']]);
    }

    public function update(UpdateBidRequest $request, int $id, int $bidId): JsonResponse
    {
        $user = $this->actor($request);
        $userId = $this->actorId($request, $id);
        if ($userId < 1) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }
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

        return response()->json(['success' => true, 'data' => $bid]);
    }
}
