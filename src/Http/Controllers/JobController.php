<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Http\Requests\AwardJobRequest;
use Modules\Custom\MakerBids\Http\Requests\StoreJobRequest;
use Modules\Custom\MakerBids\Http\Requests\UpdateOwnedJobRequest;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Services\AwardService;
use Modules\Custom\MakerBids\Services\JobFileService;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Services\JobTypeService;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobPresenter;

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
        $memberName = (string) ($user->name ?? '');
        $companyName = '';
        try {
            $companyName = (string) (MakerCompany::query()
                ->where('user_id', (int) $user->id)
                ->where('status', 'approved')
                ->value('name') ?? '');
        } catch (\Throwable) {
            $companyName = '';
        }
        $profileName = $companyName !== '' ? $companyName : $memberName;

        return response()->json([
            'data' => [
                'upload_token' => $token,
                'contact_name' => $profileName,
                'profile_contact_name' => $profileName,
                'company_name' => $companyName,
                'contact_phone' => (string) ($user->mobile ?? $user->phone ?? ''),
                'contact_hours' => '',
                'contact_hours_from' => '09:00',
                'contact_hours_to' => '18:00',
                'contact_email' => (string) ($user->email ?? ''),
                'zipcode' => (string) ($user->zipcode ?? ''),
                'address' => (string) ($user->address ?? ''),
                'address_detail' => (string) ($user->address_detail ?? ''),
                'manager_name' => '',
                'manager_phone' => '',
                'manager_email' => '',
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
                $ctx,
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

        return response()->json(JobPresenter::envelope($job), 201);
    }

    public function update(UpdateOwnedJobRequest $request, int $id): JsonResponse
    {
        try {
            $job = $this->jobs->updateOwned((int) $request->user()->id, $id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(JobPresenter::envelope($job));
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
