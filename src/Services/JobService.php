<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Http\Request;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\AwardRules;
use Modules\Custom\MakerBids\Support\CompanyPresenter;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobPresenter;
use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\PrivacyRules;
use Modules\Custom\MakerBids\Support\SettingsRules;
use Modules\Custom\MakerBids\Support\UploadRules;
use Modules\Custom\MakerBids\Support\TypeCatalog;

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
        $q = MakerJob::query()->with(['jobType'])->withCount('bids');
        $ctx = $this->viewerFromRequest($request);

        if (! $ctx['isAdmin'] && ! $ctx['isMember'] && ! $this->settingBool('general.guests_see_list', true)) {
            return [];
        }

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

        $this->applyListSearch($q, (string) $request->query('q', ''));
        $this->applyListSort($q, $request->query('sort'));

        $jobs = $q->limit(100)->get();
        $this->attachOwnerSearchMeta($jobs);

        return $jobs->map(fn (MakerJob $job) => $this->present($job, $ctx, false, false))->all();
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
        $isDesignated = (bool) ($ctx['isDesignated'] ?? false);
        $allowMode = $this->bidAllowMode();
        $canBid = $userId > 0 && ! $isOwner && $open && JobRules::canBidAudience(
            $job->audience ?? 'all',
            true,
            $hasApprovedCompany,
            $companyKind,
            $allowMode,
            $isAdmin,
            $isDesignated,
        );

        $canWorkspace = $userId > 0 && (
            $isOwner
            || $isAdmin
            || ($awardedUserId !== null && $awardedUserId === $userId)
        ) && in_array((string) $job->status, ['awarded', 'done'], true);

        return [
            'authenticated' => $userId > 0,
            'is_owner' => $isOwner,
            'is_open' => $open,
            'can_bid' => $canBid,
            'can_award' => AwardRules::canAward($userId, $job->user_id) && $open,
            'can_update_bid' => $canUpdate,
            'can_edit' => $isOwner && JobRules::isListingStatus((string) $job->status),
            'can_view_privacy' => $canViewPersonal,
            'can_workspace' => $canWorkspace,
            'my_bid' => $myBid ? CompanyPresenter::presentBid($myBid) : null,
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

        $this->incrementViewCount($job);

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
     * Owner-only payload for the public edit form (not admin).
     *
     * @return array<string, mixed>
     */
    public function findForEdit(int $userId, int $id): array
    {
        $job = $this->findOwned($userId, $id);
        if (! JobRules::isListingStatus((string) $job->status)) {
            throw new DomainException('이 상태의 의뢰는 수정할 수 없습니다.', 422);
        }
        $job = MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->findOrFail($job->id);

        return $this->present($job, $this->ownerContext($userId), true, true);
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
        if (empty($payload['status']) || empty($attrs['status'])) {
            $attrs['status'] = $this->defaultCreateStatus();
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

        $payload = $this->resolveTypePayload($payload, $job);
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
        $payload = $this->resolveTypePayload($payload, $job);
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


    /**
     * Blank type from G7 Select harvest must not wipe/revalidate; prefer type_id.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveTypePayload(array $payload, MakerJob $job): array
    {
        if (array_key_exists('type', $payload)) {
            $raw = $payload['type'];
            if ($raw === null || (is_string($raw) && trim($raw) === '') || $raw === []) {
                unset($payload['type']);
            }
        }
        if (! isset($payload['type']) && isset($payload['type_id']) && $payload['type_id'] !== '' && $payload['type_id'] !== null) {
            $row = $this->types->findBySlug((string) $payload['type_id']);
            if ($row) {
                $payload['type'] = $row->slug;
                $payload['type_id'] = $row->id;
            }
        }
        if (! isset($payload['type']) && $job->type) {
            // keep existing type via jobAttributes fallback
        }

        return $payload;
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
     * @return array{userId:int,isAdmin:bool,isMember:bool,hasApprovedCompany:bool,companyKind:?string,isDesignated:bool}
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
            'isDesignated' => CompanyRules::isDesignated($company),
        ];
    }

    public function isAdminActor(mixed $user): bool
    {
        return $this->isAdminUser($user);
    }


    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Modules\Custom\MakerBids\Models\MakerJob>  $q
     */
    private function applyListSearch($q, string $term): void
    {
        $term = trim($term);
        if ($term === '' || in_array(strtolower($term), ['undefined', 'null', '*'], true)) {
            return;
        }
        $like = '%'.$term.'%';
        $userIds = $this->searchOwnerUserIds($term);
        $q->where(function ($w) use ($like, $term, $userIds) {
            $w->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('type', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('manager_name', 'like', $like);
            if (ctype_digit($term)) {
                $w->orWhere('user_id', (int) $term)
                    ->orWhere('id', (int) $term);
            }
            try {
                $w->orWhereIn('user_id', function ($sub) use ($like) {
                    $sub->select('user_id')
                        ->from('maker_companies')
                        ->where('name', 'like', $like);
                });
            } catch (\Throwable) {
            }
            if ($userIds !== []) {
                $w->orWhereIn('user_id', $userIds);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Modules\Custom\MakerBids\Models\MakerJob>  $q
     */
    private function applyListSort($q, mixed $sortRaw): void
    {
        $sort = JobRules::normalizeListSort($sortRaw);
        if ($sort === JobRules::LIST_SORT_CREATED) {
            $q->orderBy('created_at')->orderBy('id');

            return;
        }
        if ($sort === JobRules::LIST_SORT_VIEWS) {
            $q->orderByDesc('view_count')->orderByDesc('id');

            return;
        }
        $q->orderByDesc('id');
    }

    /**
     * @return list<int>
     */
    private function searchOwnerUserIds(string $term): array
    {
        $ids = [];
        try {
            if (! class_exists(\App\Models\User::class)) {
                return [];
            }
            $table = (new \App\Models\User)->getTable();
            $cols = [];
            if (class_exists(\Illuminate\Support\Facades\Schema::class)) {
                foreach (['name', 'userid', 'user_id', 'login', 'login_id', 'username', 'email', 'nickname'] as $col) {
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                            $cols[] = $col;
                        }
                    } catch (\Throwable) {
                    }
                }
            } else {
                $cols = ['name', 'email'];
            }
            if ($cols === [] && ! ctype_digit($term)) {
                return [];
            }
            $like = '%'.$term.'%';
            $query = \App\Models\User::query()->where(function ($w) use ($cols, $like, $term) {
                foreach ($cols as $col) {
                    $w->orWhere($col, 'like', $like);
                }
                if (ctype_digit($term)) {
                    $w->orWhere('id', (int) $term);
                }
            });
            $ids = $query->limit(50)->pluck('id')->map(static fn ($id) => (int) $id)->all();
        } catch (\Throwable) {
            $ids = [];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MakerJob>|iterable<MakerJob>  $jobs
     */
    private function attachOwnerSearchMeta($jobs): void
    {
        $list = is_array($jobs) ? $jobs : (method_exists($jobs, 'all') ? $jobs->all() : iterator_to_array($jobs));
        if ($list === []) {
            return;
        }
        $userIds = [];
        foreach ($list as $job) {
            if ($job->user_id) {
                $userIds[] = (int) $job->user_id;
            }
        }
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return;
        }
        $companies = [];
        try {
            foreach (MakerCompany::query()->whereIn('user_id', $userIds)->get(['user_id', 'name']) as $row) {
                $companies[(int) $row->user_id] = $row;
            }
        } catch (\Throwable) {
            $companies = [];
        }
        $users = [];
        try {
            if (class_exists(\App\Models\User::class)) {
                foreach (\App\Models\User::query()->whereIn('id', $userIds)->get() as $row) {
                    $users[(int) $row->id] = $row;
                }
            }
        } catch (\Throwable) {
            $users = [];
        }
        foreach ($list as $job) {
            $uid = (int) ($job->user_id ?? 0);
            $co = $companies[$uid] ?? null;
            $job->owner_company_name = $co ? (string) ($co->name ?? '') : null;
            $user = $users[$uid] ?? null;
            if (is_object($user)) {
                $job->owner_name = (string) ($user->name ?? $user->nickname ?? '');
                $job->owner_login = (string) ($user->userid ?? $user->login ?? $user->login_id ?? $user->username ?? $user->email ?? '');
            }
        }
    }

    private function incrementViewCount(MakerJob $job): void
    {
        try {
            MakerJob::query()->where('id', $job->id)->increment('view_count');
            $job->view_count = (int) ($job->view_count ?? 0) + 1;
        } catch (\Throwable) {
        }
    }


    public function bidAllowMode(): string
    {
        return BidRules::normalizeAllow($this->setting('general.bid_allow', BidRules::ALLOW_ALL));
    }

    /**
     * @return list<string>
     */
    public function providedExtensionAllowList(): array
    {
        return SettingsRules::providedExtensionList(
            $this->setting('general.provided_extensions', implode(',', UploadRules::PROVIDED_EXTENSIONS))
        );
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

        return JobRules::collectProvidedExtensions($payload, $this->providedExtensionAllowList());
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

        if (array_key_exists('terms_agreed', $payload)) {
            $attrs['terms_agreed'] = (bool) $payload['terms_agreed'];
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
        return ['userId' => $userId, 'isAdmin' => false, 'isMember' => true, 'hasApprovedCompany' => false, 'companyKind' => null, 'isDesignated' => false];
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
        if (! in_array((string) $job->status, ['awarded', 'done'], true)) {
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

    private function defaultCreateStatus(): string
    {
        $raw = (string) $this->setting('general.default_job_status', 'quote_request');
        if ($raw === 'open') {
            return 'quote_request';
        }
        if (! in_array($raw, JobRules::LISTING_STATUSES, true)) {
            return 'quote_request';
        }

        return $raw;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('app')) {
                return app(MakerBidSettingsService::class)->getSetting($key, $default);
            }
        } catch (\Throwable) {
        }

        return $default;
    }

    private function settingBool(string $key, bool $default = false): bool
    {
        $value = $this->setting($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        return SettingsRules::boolish($value);
    }
}
