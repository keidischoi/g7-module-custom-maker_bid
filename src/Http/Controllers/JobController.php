<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBid\Http\Requests\AwardJobRequest;
use Modules\Custom\MakerBid\Http\Requests\StoreJobRequest;
use Modules\Custom\MakerBid\Services\AwardService;
use Modules\Custom\MakerBid\Services\JobService;
use Modules\Custom\MakerBid\Support\DomainException;

class JobController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobService $jobs,
        private readonly AwardService $awards,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listPublic($request)]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->findPublic($id)]);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listMine((int) $request->user()->id)]);
    }

    public function viewer(Request $request, int $id): JsonResponse
    {
        $job = $this->jobs->findPublic($id);

        return response()->json(['data' => $this->jobs->viewerContext((int) $request->user()->id, $job)]);
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = $this->jobs->create((int) $request->user()->id, $request->validated());

        return response()->json(['data' => $job], 201);
    }

    public function award(AwardJobRequest $request, int $id): JsonResponse
    {
        try {
            $job = $this->awards->award(
                (int) $request->user()->id,
                $id,
                (int) $request->validated()['bid_id'],
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $job]);
    }
}
