<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Http\Requests\StoreBidRequest;
use Modules\Custom\MakerBids\Http\Requests\UpdateBidRequest;
use Modules\Custom\MakerBids\Services\BidService;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Support\DomainException;

class BidController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly BidService $bids,
        private readonly JobService $jobs,
    ) {}

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->bids->listMine((int) $request->user()->id)]);
    }

    public function store(StoreBidRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->bids->createOrUpdateOwn(
                (int) $request->user()->id,
                $id,
                $request->validated(),
                $this->jobs->isAdminActor($request->user()),
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $result['bid']], $result['created'] ? 201 : 200);
    }

    public function update(UpdateBidRequest $request, int $id, int $bidId): JsonResponse
    {
        try {
            $bid = $this->bids->updateOwn(
                (int) $request->user()->id,
                $id,
                $bidId,
                $request->validated(),
                $this->jobs->isAdminActor($request->user()),
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $bid]);
    }
}
