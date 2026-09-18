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
use Modules\Custom\MakerBids\Models\MakerJobFile;
use Modules\Custom\MakerBids\Services\AwardService;
use Modules\Custom\MakerBids\Services\BidService;
use Modules\Custom\MakerBids\Services\JobFileService;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Services\JobTypeService;
use Modules\Custom\MakerBids\Services\MakerBidSettingsService;
use Modules\Custom\MakerBids\Services\MarketplaceService;
use Modules\Custom\MakerBids\Support\ArrayPaginator;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobPresenter;
use Modules\Custom\MakerBids\Support\SettingsRules;
use Modules\Custom\MakerBids\Support\UploadRules;

class JobController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobService $jobs,
        private readonly AwardService $awards,
        private readonly JobTypeService $types,
        private readonly JobFileService $files,
        private readonly MakerBidSettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try { app(MarketplaceService::class)->closeExpired(); } catch (\Throwable) {}
        $data = $this->attachListThumbs($this->jobs->listPublic($request));
        $payload = ArrayPaginator::paginate($data, $request, 'page', 10);
        $ctx = $this->jobs->viewerFromRequest($request);
        $payload['meta']['viewer'] = [
            'is_admin' => (bool) ($ctx['isAdmin'] ?? false),
            'user_id' => (int) ($ctx['userId'] ?? 0),
        ];
        $payload['meta']['viewer_status_chips'] = $this->jobs->viewerSelfStatusChips($request);

        return response()->json($payload);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            try { app(MarketplaceService::class)->closeExpired(); } catch (\Throwable) {}
            return response()->json(['data' => $this->jobs->findPublic($id, $request)]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $items = $this->jobs->listMine((int) $request->user()->id);

        return response()->json(ArrayPaginator::paginate($items, $request, 'page', 10));
    }

    public function formDefaults(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->files->newUploadToken();
        $memberName = (string) ($user->name ?? '');
        $companyName = '';
        try {
            $companyName = (string) (MakerCompany::query()->where('user_id', (int) $user->id)->where('status', 'approved')->value('name') ?? '');
        } catch (\Throwable) {
            $companyName = '';
        }
        $profileName = $companyName !== '' ? $companyName : $memberName;

        return response()->json(['data' => [
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
            'provided_extensions' => $this->jobs->providedExtensionAllowList(),
        ]]);
    }

    public function viewer(Request $request, int $id): JsonResponse
    {
        try {
            $this->jobs->findPublic($id, $request);
            $job = $this->jobs->rawFind($id);
            $ctx = $this->jobs->viewerFromRequest($request);
            $userId = (int) ($ctx['userId'] ?? 0);
            $data = $this->jobs->viewerContext($userId, $job, (bool) ($ctx['isAdmin'] ?? false), $ctx);
            $data['my_bid_history'] = [];
            if ($userId > 0) {
                try {
                    $data['my_bid_history'] = app(BidService::class)->listRevisions(
                        $id,
                        $userId,
                        ! empty($ctx['isAdmin'])
                    );
                } catch (\Throwable) {
                    $data['my_bid_history'] = [];
                }
                $data['bid_token'] = encrypt(json_encode([
                    'uid' => $userId,
                    'jid' => $id,
                    'exp' => time() + 3600,
                ]));
            }

            return response()->json(['data' => $data]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function editData(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->findForEdit((int) $request->user()->id, $id)]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function store(StoreJobRequest $request): JsonResponse
    {
        $isDraft = ($request->input('status') === 'draft');
        if (! $isDraft && ! $request->boolean('terms_agreed')) {
            return response()->json(['message' => '약관과 개인정보 처리에 동의해야 합니다.'], 422);
        }
        try {
            $payload = $request->validated();
            if ($isDraft) {
                $payload['status'] = 'draft';
            }
            $payload['terms_agreed'] = $isDraft ? (bool) $request->boolean('terms_agreed') : true;
            $job = $this->jobs->create((int) $request->user()->id, $payload);
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
            $this->awards->award((int) $request->user()->id, $id, (int) $request->validated()['bid_id']);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
        return response()->json(['data' => $this->jobs->findPublic($id, $request)]);
    }

    private function attachListThumbs(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return $rows;
        }
        $files = MakerJobFile::query()
            ->whereIn('job_id', $ids)
            ->where('collection', UploadRules::COLLECTION_IMAGES)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $first = [];
        foreach ($files as $file) {
            $jid = (int) $file->job_id;
            if (! isset($first[$jid])) {
                $att = $file->toAttachmentArray();
                $first[$jid] = $att['thumbnail_url'] ?: $att['url'] ?: $att['download_url'];
            }
        }
        foreach ($rows as $i => $row) {
            $jid = (int) ($row['id'] ?? 0);
            $rows[$i]['thumbnail_url'] = $first[$jid] ?? null;
        }

        return $rows;
    }
}
