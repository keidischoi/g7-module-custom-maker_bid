<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBid\Http\Requests\AwardJobRequest;
use Modules\Custom\MakerBid\Http\Requests\StoreJobRequest;
use Modules\Custom\MakerBid\Http\Requests\UpdateOwnedJobRequest;
use Modules\Custom\MakerBid\Services\AwardService;
use Modules\Custom\MakerBid\Services\JobFileService;
use Modules\Custom\MakerBid\Services\JobService;
use Modules\Custom\MakerBid\Services\JobTypeService;
use Modules\Custom\MakerBid\Support\DomainException;

class JobController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobService $jobs,
        private readonly AwardService $awards,
        private readonly JobTypeService $types,
        private readonly JobFileService $files,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listPublic($request)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->findPublic($id, $request)]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listMine((int) $request->user()->id)]);
    }

    public function formDefaults(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->files->newUploadToken();

        return response()->json([
            'data' => [
                'upload_token' => $token,
                'contact_name' => (string) ($user->name ?? ''),
                'contact_phone' => (string) ($user->mobile ?? $user->phone ?? ''),
                'contact_hours' => '',
                'contact_hours_from' => '09:00',
                'contact_hours_to' => '18:00',
                'contact_email' => (string) ($user->email ?? ''),
                'zipcode' => (string) ($user->zipcode ?? ''),
                'address' => (string) ($user->address ?? ''),
                'address_detail' => (string) ($user->address_detail ?? ''),
                'types' => $this->types->listPublic()->map->toOptionArray()->values()->all(),
            ],
        ]);
    }

    public function viewer(Request $request, int $id): JsonResponse
    {
        try {
            $this->jobs->findPublic($id, $request);
            $job = $this->jobs->rawFind($id);
            $ctx = $this->jobs->viewerFromRequest($request);

            return response()->json(['data' => $this->jobs->viewerContext(
                (int) $request->user()->id,
                $job,
                $ctx['isAdmin'],
            )]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        try {
            $job = $this->jobs->create((int) $request->user()->id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $job], 201);
    }

    public function update(UpdateOwnedJobRequest $request, int $id): JsonResponse
    {
        try {
            $job = $this->jobs->updateOwned((int) $request->user()->id, $id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $job]);
    }

    public function award(AwardJobRequest $request, int $id): JsonResponse
    {
        try {
            $this->awards->award(
                (int) $request->user()->id,
                $id,
                (int) $request->validated()['bid_id'],
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $this->jobs->findPublic($id, $request)]);
    }
}
