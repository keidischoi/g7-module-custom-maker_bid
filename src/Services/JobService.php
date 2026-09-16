<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Http\Request;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerCompany;
use Modules\Custom\MakerBid\Models\MakerJob;
use Modules\Custom\MakerBid\Support\AwardRules;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\CompanyRules;
use Modules\Custom\MakerBid\Support\DomainException;
use Modules\Custom\MakerBid\Support\JobPresenter;
use Modules\Custom\MakerBid\Support\JobRules;
use Modules\Custom\MakerBid\Support\PrivacyRules;
use Modules\Custom\MakerBid\Support\TypeCatalog;

class JobService
{
    public function __construct(
        private readonly JobTypeService $types,
        private readonly JobFileService $files,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listPublic(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType'])->withCount('bids')->latest();
        $ctx = $this->viewerFromRequest($request);

        if ($type = JobRules::listTypeFilter($request->query('type'))) {
            $q->where('type', $type);
        }
        $statusFilter = JobRules::listStatusFilter($request->query('status'));
        if ($statusFilter !== null) {
            if ($statusFilter === []) {
                return [];
            }
            $q->whereIn('status', $statusFilter);
        }

        if (! $ctx['isAdmin']) {
            $allowed = JobRules::visibleAudiencesFor(
                $ctx['isMember'],
                $ctx['hasApprovedCompany'],
                $ctx['companyKind'],
            );
            $q->where(function ($outer) use ($allowed, $ctx) {
                $outer->where(function ($pub) use ($allowed) {
                    $pub->whereNotIn('status', JobRules::HIDDEN_PUBLIC_STATUSES)
                        ->where(function ($aud) use ($allowed) {
                            $aud->whereIn('audience', $allowed)->orWhereNull('audience');
                        });
                });
                if ($ctx['userId'] > 0) {
                    $outer->orWhere('user_id', $ctx['userId']);
                }
            });
        }

        return $q->limit(100)->get()->map(fn (MakerJob $job) => $this->present($job, $ctx, false, false))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMine(int $userId): array
    {
        return MakerJob::query()
            ->with(['jobType', 'files'])
            ->withCount('bids')
            ->where('user_id', $userId)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (MakerJob $job) => $this->present($job, $this->ownerContext($userId), true, true))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function viewerContext(int $userId, MakerJob $job, bool $isAdmin = false, array $ctx = []): array
    {
        $open = $job->isOpen();
        $isOwner = BidRules::isOwnJob($userId, $job->user_id);
        $myBid = MakerBid::query()
            ->where('job_id', $job->id)
            ->where('user_id', $userId)
            ->first();
        $canUpdate = $myBid !== null
            && BidRules::canUpdateOwn($userId, (int) $myBid->user_id, $open, (string) $myBid->status);
        $awardedUserId = $this->awardedBidderUserId($job);
        $canViewPersonal = PrivacyRules::canViewPersonal(
            $userId,
            $job->user_id,
            (string) $job->status,
            $awardedUserId,
            $isAdmin,
        );
        $hasApprovedCompany = (bool) ($ctx['hasApprovedCompany'] ?? false);
        $companyKind = $ctx['companyKind'] ?? null;
        $canBid = $userId > 0 && ! $isOwner && $open && JobRules::canBidAudience(
            $job->audience ?? 'all',
            true,
            $hasApprovedCompany,
            $companyKind,
        );

        return [
            'authenticated' => $userId > 0,
            'is_owner' => $isOwner,
            'is_open' => $open,
            'can_bid' => $canBid,
            'can_award' => AwardRules::canAward($userId, $job->user_id) && $open,
            'can_update_bid' => $canUpdate,
            'can_edit' => $isOwner && JobRules::isListingStatus((string) $job->status),
            'can_view_privacy' => $canViewPersonal,
            'my_bid' => $myBid,
            'privacy' => $canViewPersonal ? $this->personalPayload($job) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function findPublic(int $id, Request $request): array
    {
        $job = MakerJob::query()->with(['bids.company', 'jobType', 'files', 'awardedBid'])->withCount('bids')->find($id);
        if ($job === null) {
            $this->denyPublic();
        }
        $ctx = $this->viewerFromRequest($request);
        if (JobRules::isHiddenFromPublic((string) $job->status) && ! $this->canSeeHold($job, $ctx)) {
            $this->denyPublic();
        }
        $isOwner = $ctx['userId'] > 0 && (int) $job->user_id === $ctx['userId'];
        if (! JobRules::canViewAudience(
            $job->audience ?? 'all',
            $isOwner,
            $ctx['isAdmin'],
            $ctx['isMember'],
            $ctx['hasApprovedCompany'],
            $ctx['companyKind'],
        ) && ! $this->hasBidOnJob($job, $ctx['userId'])) {
            $this->denyPublic();
        }

        return $this->present($job, $ctx, true, true);
    }

    public function findOwned(int $userId, int $id): MakerJob
    {
        $job = MakerJob::query()->with(['jobType', 'files'])->findOrFail($id);
        if ((int) $job->user_id !== $userId) {
            throw new DomainException('본인 의뢰만 수정할 수 있습니다.', 403);
        }

        return $job;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(int $userId, array $payload): array
    {
        $type = $this->types->requireEnabled((string) $payload['type']);
        $this->assertAddress($type->toOptionArray(), $payload);

        $attrs = $this->jobAttributes($payload, $type->id, (string) $type->slug);
        $attrs['user_id'] = $userId;
        if (empty($attrs['status'])) {
            $attrs['status'] = 'quote_request';
        }

        $job = MakerJob::query()->create($attrs);
        $token = (string) ($payload['upload_token'] ?? '');
        if ($token !== '') {
            $this->files->claimToken($userId, $token, (int) $job->id);
        }

        $job = MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->findOrFail($job->id);

        return $this->present($job, $this->ownerContext($userId), true, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOwned(int $userId, int $id, array $payload): array
    {
        $job = $this->findOwned($userId, $id);
        if (! JobRules::isListingStatus((string) $job->status) && isset($payload['status'])) {
            throw new DomainException('이 상태의 의뢰는 목록 상태를 바꿀 수 없습니다.', 422);
        }

        if (isset($payload['type'])) {
            $type = $this->types->requireEnabled((string) $payload['type']);
            $payload['type_id'] = $type->id;
            $payload['type'] = $type->slug;
            $this->assertAddress($type->toOptionArray(), array_merge($job->toArray(), $payload));
        } elseif ($job->jobType) {
            $this->assertAddress($job->jobType->toOptionArray(), array_merge($job->toArray(), $payload));
        }

        $attrs = $this->jobAttributes($payload, $payload['type_id'] ?? $job->type_id, (string) ($payload['type'] ?? $job->type), false);
        $job->fill($attrs);
        $job->save();
        $token = (string) ($payload['upload_token'] ?? '');
        if ($token !== '') {
            $this->files->claimToken($userId, $token, (int) $job->id);
        }

        $job = MakerJob::query()->with(['jobType', 'files', 'bids'])->withCount('bids')->findOrFail($job->id);

        return $this->present($job, $this->ownerContext($userId), true, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdmin(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType'])->withCount('bids')->latest();
        if ($type = $request->query('type')) {
            $q->where('type', TypeCatalog::normalizeSlug((string) $type));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (int) $userId);
        }

        $ctx = ['userId' => 0, 'isAdmin' => true];

        return $q->limit(200)->get()->map(fn (MakerJob $job) => $this->present($job, $ctx, false, true))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function findAdmin(int $id): array
    {
        $job = MakerJob::query()->with(['bids.company', 'jobType', 'files'])->withCount('bids')->findOrFail($id);

        return $this->present($job, ['userId' => 0, 'isAdmin' => true], true, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAdmin(int $id, array $payload): array
    {
        $job = MakerJob::query()->findOrFail($id);
        if (isset($payload['type'])) {
            $type = $this->types->requireEnabled((string) $payload['type']);
            $payload['type_id'] = $type->id;
            $payload['type'] = $type->slug;
        }
        $attrs = $this->jobAttributes($payload, $payload['type_id'] ?? $job->type_id, (string) ($payload['type'] ?? $job->type), false);
        $job->fill($attrs);
        $job->save();

        return $this->findAdmin($id);
    }

    public function hold(int $id): array
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->status = 'hold';
        $job->save();

        return $this->findAdmin($id);
    }

    public function cancel(int $id): array
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->status = 'cancelled';
        $job->save();

        return $this->findAdmin($id);
    }

    public function destroy(int $id): void
    {
        $job = MakerJob::query()->findOrFail($id);
        MakerBid::query()->where('job_id', $job->id)->delete();
        foreach ($this->files->forJob((int) $job->id) as $file) {
            $this->files->destroyAdmin((string) $file->hash);
        }
        $job->delete();
    }

    public function assertOpen(MakerJob $job): void
    {
        if (! JobRules::isOpen((string) $job->status, $job->closes_at)) {
            throw new DomainException('입찰이 마감된 의뢰입니다.', 422);
        }
    }

    public function rawFind(int $id): MakerJob
    {
        return MakerJob::query()->with(['bids.company', 'jobType', 'files', 'awardedBid'])->withCount('bids')->findOrFail($id);
    }

    /**
     * @return array{userId:int,isAdmin:bool,isMember:bool,hasApprovedCompany:bool,companyKind:?string}
     */
    public function viewerFromRequest(Request $request): array
    {
        $user = $request->user() ?? (function_exists('auth') ? auth('sanctum')->user() : null);
        $userId = $user ? (int) $user->id : 0;
        $company = null;
        if ($userId > 0) {
            try {
                $company = MakerCompany::query()
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->first();
            } catch (\Throwable) {
                $company = null;
            }
        }

        return [
            'userId' => $userId,
            'isAdmin' => $this->isAdminUser($user),
            'isMember' => $userId > 0,
            'hasApprovedCompany' => CompanyRules::isApproved($company?->status),
            'companyKind' => $company?->kind,
        ];
    }

    /**
     * @param  array<string, mixed>  $type
     * @param  array<string, mixed>  $payload
     */
    private function assertAddress(array $type, array $payload): void
    {
        if (! TypeCatalog::requiresAddress($type, (string) ($payload['type'] ?? ''))) {
            return;
        }
        $address = trim((string) ($payload['address'] ?? ''));
        if ($address === '') {
            throw new DomainException('이 유형은 주소가 필요합니다.', 422);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function extensionsForType(array $payload, string $typeSlug): array
    {
        $typeRow = $this->types->findBySlug($typeSlug)?->toOptionArray();
        if (! TypeCatalog::includesModeling($typeRow, $typeSlug)) {
            return [];
        }

        return JobRules::collectProvidedExtensions($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function jobAttributes(array $payload, mixed $typeId, string $typeSlug, bool $creating = true): array
    {
        $min = $this->nullableInt($payload['budget_min'] ?? null);
        $max = $this->nullableInt($payload['budget_max'] ?? ($payload['budget'] ?? null));
        if ($min !== null && $max !== null && $max < $min) {
            throw new DomainException('예산 최댓값은 최솟값보다 크거나 같아야 합니다.', 422);
        }

        $rush = ! empty($payload['rush_fee_enabled']);
        $revision = ! empty($payload['revision_enabled']);
        $hours = JobRules::contactHours(
            $payload['contact_hours_from'] ?? null,
            $payload['contact_hours_to'] ?? null,
            $payload['contact_hours'] ?? null,
        );

        $attrs = [
            'type_id' => $typeId !== null ? (int) $typeId : null,
            'type' => TypeCatalog::normalizeSlug($typeSlug),
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'budget_min' => $min,
            'budget_max' => $max,
            'budget' => $max,
            'closes_at' => $payload['closes_at'] ?? null,
            'audience' => JobRules::normalizeAudience($payload['audience'] ?? 'all'),
            'rush_fee_enabled' => $rush,
            'rush_deadline' => $rush ? ($payload['rush_deadline'] ?? null) : null,
            'schedule_premium_enabled' => ! empty($payload['schedule_premium_enabled']),
            'sizes' => JobRules::normalizeSizes($payload),
            'size_w' => null,
            'size_d' => null,
            'size_h' => null,
            'provided_extensions' => $this->extensionsForType($payload, $typeSlug),
            'ownership_requested' => ! empty($payload['ownership_requested']),
            'revision_enabled' => $revision,
            'revision_count' => $revision ? $this->nullableInt($payload['revision_count'] ?? null) : null,
            'revision_cost' => $revision ? $this->nullableInt($payload['revision_cost'] ?? null) : null,
            'contact_name' => $payload['contact_name'] ?? null,
            'contact_phone' => $payload['contact_phone'] ?? null,
            'contact_hours' => $hours,
            'contact_email' => $payload['contact_email'] ?? null,
            'zipcode' => $payload['zipcode'] ?? null,
            'address' => $payload['address'] ?? null,
            'address_detail' => $payload['address_detail'] ?? null,
            'manager_name' => $payload['manager_name'] ?? null,
            'manager_phone' => $payload['manager_phone'] ?? null,
            'manager_email' => $payload['manager_email'] ?? null,
            'upload_token' => $payload['upload_token'] ?? null,
        ];

        $first = $attrs['sizes'][0] ?? null;
        $attrs['size_w'] = $first['w'] ?? $this->nullableInt($payload['size_w'] ?? null);
        $attrs['size_d'] = $first['d'] ?? $this->nullableInt($payload['size_d'] ?? null);
        $attrs['size_h'] = $first['h'] ?? $this->nullableInt($payload['size_h'] ?? null);
        if ($attrs['sizes'] === []) {
            $attrs['sizes'] = null;
        }

        if (isset($payload['status']) && $payload['status'] !== '') {
            $attrs['status'] = $payload['status'] === 'open' ? 'quote_request' : $payload['status'];
        } elseif ($creating) {
            $attrs['status'] = 'quote_request';
        }

        if (! $creating) {
            $attrs = array_filter($attrs, static function (mixed $value, string $key) use ($payload): bool {
                if (in_array($key, ['rush_fee_enabled', 'schedule_premium_enabled', 'revision_enabled', 'provided_extensions', 'ownership_requested'], true)) {
                    return array_key_exists($key, $payload)
                        || array_key_exists('ext_stl', $payload)
                        || array_key_exists('type', $payload)
                        || ($key === 'rush_fee_enabled' && array_key_exists('rush_fee_enabled', $payload))
                        || ($key === 'revision_enabled' && array_key_exists('revision_enabled', $payload))
                        || ($key === 'schedule_premium_enabled' && array_key_exists('schedule_premium_enabled', $payload));
                }
                if ($key === 'type' || $key === 'type_id') {
                    return array_key_exists('type', $payload);
                }
                if ($key === 'budget') {
                    return array_key_exists('budget', $payload) || array_key_exists('budget_max', $payload);
                }
                if ($key === 'contact_hours') {
                    return array_key_exists('contact_hours', $payload)
                        || array_key_exists('contact_hours_from', $payload)
                        || array_key_exists('contact_hours_to', $payload);
                }
                if ($key === 'rush_deadline') {
                    return array_key_exists('rush_fee_enabled', $payload) || array_key_exists('rush_deadline', $payload);
                }
                if ($key === 'revision_count' || $key === 'revision_cost') {
                    return array_key_exists('revision_enabled', $payload) || array_key_exists($key, $payload);
                }
                if ($key === 'sizes' || $key === 'size_w' || $key === 'size_d' || $key === 'size_h') {
                    return array_key_exists('sizes', $payload)
                        || array_key_exists('size_w', $payload)
                        || array_key_exists('size_d', $payload)
                        || array_key_exists('size_h', $payload);
                }

                $map = [
                    'title' => 'title',
                    'description' => 'description',
                    'budget_min' => 'budget_min',
                    'budget_max' => 'budget_max',
                    'closes_at' => 'closes_at',
                    'contact_name' => 'contact_name',
                    'contact_phone' => 'contact_phone',
                    'contact_email' => 'contact_email',
                    'zipcode' => 'zipcode',
                    'address' => 'address',
                    'address_detail' => 'address_detail',
                    'manager_name' => 'manager_name',
                    'manager_phone' => 'manager_phone',
                    'manager_email' => 'manager_email',
                    'upload_token' => 'upload_token',
                    'status' => 'status',
                    'audience' => 'audience',
                ];

                return isset($map[$key]) && array_key_exists($map[$key], $payload);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return array_filter($attrs, static fn (mixed $value): bool => $value !== null || true);
    }

    /**
     * @param  array{userId:int,isAdmin:bool}  $ctx
     * @return array<string, mixed>
     */
    private function present(MakerJob $job, array $ctx, bool $includeBids, bool $includeFiles): array
    {
        $awardedUserId = $this->awardedBidderUserId($job);
        $canPersonal = PrivacyRules::canViewPersonal(
            $ctx['userId'],
            $job->user_id,
            (string) $job->status,
            $awardedUserId,
            $ctx['isAdmin'],
        );
        $canArchives = PrivacyRules::canViewArchives(
            $ctx['userId'],
            $job->user_id,
            (string) $job->status,
            $awardedUserId,
            $ctx['isAdmin'],
        );
        $files = $includeFiles ? ($job->relationLoaded('files') ? $job->files : $this->files->forJob((int) $job->id)) : [];

        return JobPresenter::present($job, $canPersonal, $canArchives, $files, $includeBids);
    }

    /**
     * @return array{userId:int,isAdmin:bool}
     */
    private function ownerContext(int $userId): array
    {
        return ['userId' => $userId, 'isAdmin' => false, 'isMember' => true, 'hasApprovedCompany' => false, 'companyKind' => null];
    }

    /**
     * Same 404 copy for missing, hold, and out-of-audience so existence is not leaked.
     */
    private function denyPublic(): never
    {
        throw new DomainException('의뢰를 찾을 수 없습니다.', 404);
    }

    /**
     * @param  array{userId:int,isAdmin:bool}  $ctx
     */
    private function canSeeHold(MakerJob $job, array $ctx): bool
    {
        if ($ctx['isAdmin']) {
            return true;
        }

        return $ctx['userId'] > 0 && (int) $job->user_id === $ctx['userId'];
    }

    private function hasBidOnJob(MakerJob $job, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return MakerBid::query()->where('job_id', $job->id)->where('user_id', $userId)->exists();
    }

    private function awardedBidderUserId(MakerJob $job): ?int
    {
        if ($job->status !== 'awarded') {
            return null;
        }
        if ($job->relationLoaded('awardedBid') && $job->awardedBid) {
            return (int) $job->awardedBid->user_id;
        }
        if ($job->awarded_bid_id) {
            $bid = MakerBid::query()->find($job->awarded_bid_id);

            return $bid ? (int) $bid->user_id : null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function personalPayload(MakerJob $job): array
    {
        $out = [];
        foreach (PrivacyRules::personalKeys() as $key) {
            $out[$key] = $job->{$key};
        }

        return $out;
    }

    private function isAdminUser(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return (bool) ($user->is_admin ?? $user->is_super ?? false);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
