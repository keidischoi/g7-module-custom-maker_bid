<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Http\Request;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobPresenter;
use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\SettingsRules;
use Modules\Custom\MakerBids\Support\TypeCatalog;
use Modules\Custom\MakerBids\Support\UploadRules;

class JobService
{
    public function __construct(
        private readonly JobTypeService $types,
        private readonly JobFileService $files,
    ) {}

    public function listPublic(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType', 'files'])->withCount('bids');
        $ctx = $this->viewerFromRequest($request);
        if (! $ctx['isAdmin'] && ! $ctx['isMember'] && ! $this->settingBool('general.guests_see_list', true)) {
            return [];
        }
        if (! $ctx['isAdmin']) {
            $q->where(function ($outer) use ($ctx) {
                $outer->whereNotIn('status', JobRules::HIDDEN_PUBLIC_STATUSES);
                if ($ctx['userId'] > 0) {
                    $outer->orWhere('user_id', $ctx['userId']);
                }
            });
        }
        return $q->orderByDesc('id')->limit(100)->get()
            ->map(fn (MakerJob $job) => $this->present($job, $ctx, false, true))->all();
    }

    public function listMine(int $userId): array
    {
        return MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->where('user_id', $userId)->latest()->limit(100)->get()
            ->map(fn (MakerJob $job) => $this->present($job, $this->ownerContext($userId), true, true))->all();
    }

    public function listAdmin(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        return $q->limit(200)->get()->map(fn (MakerJob $job) => $this->present($job, ['userId' => 0, 'isAdmin' => true], false, true))->all();
    }

    public function findPublic(int $id, Request $request): array
    {
        $job = MakerJob::query()->with(['bids.company', 'jobType', 'files', 'awardedBid'])->withCount('bids')->find($id);
        if ($job === null) {
            throw new DomainException('의뢰를 찾을 수 없습니다.', 404);
        }
        $ctx = $this->viewerFromRequest($request);
        return $this->present($job, $ctx, true, true);
    }

    public function findAdmin(int $id): array
    {
        $job = MakerJob::query()->with(['bids.company', 'jobType', 'files'])->withCount('bids')->findOrFail($id);
        $payload = $this->present($job, ['userId' => 0, 'isAdmin' => true], true, true);
        $payload['upload_token'] = $this->files->newUploadToken();
        return $payload;
    }

    public function findForEdit(int $userId, int $id): array
    {
        $job = MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->findOrFail($id);
        if ((int) $job->user_id !== $userId) {
            throw new DomainException('본인 의뢰만 수정할 수 있습니다.', 403);
        }
        $ctx = $this->ownerContext($userId);
        $ctx['isAdmin'] = true;
        $payload = $this->present($job, $ctx, true, true);
        $payload['upload_token'] = $this->files->newUploadToken();
        return $payload;
    }

    public function rawFind(int $id): MakerJob
    {
        return MakerJob::query()->with(['bids.company', 'jobType', 'files', 'awardedBid'])->withCount('bids')->findOrFail($id);
    }

    public function create(int $userId, array $payload): array
    {
        $type = $this->types->requireEnabled(TypeCatalog::coerceTypeInput($payload['type'] ?? null));
        $picked = JobRules::normalizeListingStatus($payload['status'] ?? '');
        $status = $picked === 'draft' ? 'draft' : $this->defaultCreateStatus();
        $job = MakerJob::query()->create([
            'user_id' => $userId,
            'type_id' => $type->id,
            'type' => (string) $type->slug,
            'title' => $payload['title'] ?? '',
            'description' => $payload['description'] ?? null,
            'status' => $status,
            'audience' => JobRules::normalizeAudience($payload['audience'] ?? 'all'),
            'budget_min' => $payload['budget_min'] ?? null,
            'budget_max' => $payload['budget_max'] ?? ($payload['budget'] ?? null),
            'budget' => $payload['budget_max'] ?? ($payload['budget'] ?? null),
            'closes_at' => $payload['closes_at'] ?? null,
            'contact_name' => $payload['contact_name'] ?? null,
            'contact_phone' => $payload['contact_phone'] ?? null,
            'contact_email' => $payload['contact_email'] ?? null,
            'contact_hours' => JobRules::contactHours($payload['contact_hours_from'] ?? null, $payload['contact_hours_to'] ?? null, $payload['contact_hours'] ?? null),
            'zipcode' => $payload['zipcode'] ?? null,
            'address' => $payload['address'] ?? null,
            'address_detail' => $payload['address_detail'] ?? null,
            'sizes' => JobRules::normalizeSizes($payload),
            'provided_extensions' => JobRules::collectProvidedExtensions($payload, $this->providedExtensionAllowList()),
            'terms_agreed' => ! empty($payload['terms_agreed']),
        ]);
        $token = (string) ($payload['upload_token'] ?? '');
        if ($token !== '') {
            $this->files->claimToken($userId, $token, (int) $job->id);
        }
        return $this->present(MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->findOrFail($job->id), $this->ownerContext($userId), true, true);
    }

    public function updateOwned(int $userId, int $id, array $payload): array
    {
        $job = MakerJob::query()->findOrFail($id);
        if ((int) $job->user_id !== $userId) {
            throw new DomainException('본인 의뢰만 수정할 수 있습니다.', 403);
        }
        $job->fill(array_filter([
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'status' => isset($payload['status']) ? JobRules::normalizeListingStatus($payload['status']) : null,
        ], static fn ($v) => $v !== null));
        $job->save();
        return $this->present(MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->findOrFail($job->id), $this->ownerContext($userId), true, true);
    }

    public function updateAdmin(int $id, array $payload): array
    {
        $job = MakerJob::query()->findOrFail($id);
        if (isset($payload['status']) && $payload['status'] !== '') {
            $st = (string) $payload['status'];
            $job->status = in_array($st, JobRules::STATUSES, true) ? $st : JobRules::normalizeListingStatus($st);
        }
        if (isset($payload['title'])) {
            $job->title = $payload['title'];
        }
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
        MakerBid::query()->where('job_id', $id)->delete();
        MakerJob::query()->where('id', $id)->delete();
    }

    public function viewerContext(int $userId, MakerJob $job, bool $isAdmin = false, array $ctx = []): array
    {
        $open = $job->isOpen();
        $isOwner = BidRules::isOwnJob($userId, $job->user_id);
        return [
            'authenticated' => $userId > 0,
            'is_owner' => $isOwner,
            'is_open' => $open,
            'can_bid' => $userId > 0 && ! $isOwner && $open,
            'can_award' => $isOwner && $open,
            'can_update_bid' => false,
            'can_edit' => $isOwner && JobRules::isListingStatus((string) $job->status),
            'can_view_privacy' => $isOwner || $isAdmin,
            'can_workspace' => false,
            'my_bid' => null,
            'privacy' => null,
        ];
    }

    public function viewerFromRequest(Request $request): array
    {
        $user = $request->user() ?? (function_exists('auth') ? auth('sanctum')->user() : null);
        $userId = $user ? (int) $user->id : 0;
        $company = null;
        if ($userId > 0) {
            try {
                $company = MakerCompany::query()->where('user_id', $userId)->where('status', 'approved')->first();
            } catch (\Throwable) {
                $company = null;
            }
        }
        $isAdmin = is_object($user) && (
            (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (bool) ($user->is_admin ?? false)
        );
        return [
            'userId' => $userId,
            'isAdmin' => $isAdmin,
            'isMember' => $userId > 0,
            'hasApprovedCompany' => CompanyRules::isApproved($company?->status),
            'companyKind' => $company?->kind ?? null,
            'isDesignated' => CompanyRules::isDesignated($company),
        ];
    }

    public function isAdminActor(mixed $user): bool
    {
        return is_object($user) && ((method_exists($user, 'isAdmin') && $user->isAdmin()) || (bool) ($user->is_admin ?? false));
    }

    public function providedExtensionAllowList(): array
    {
        return SettingsRules::providedExtensionList($this->setting('general.provided_extensions', implode(',', UploadRules::PROVIDED_EXTENSIONS)));
    }

    public function assertOpen(MakerJob $job): void
    {
        if (! JobRules::isOpen((string) $job->status, $job->closes_at)) {
            throw new DomainException('입찰이 마감된 의뢰입니다.', 422);
        }
    }

    private function present(MakerJob $job, array $ctx, bool $includeBids, bool $includeFiles): array
    {
        $files = $includeFiles ? ($job->relationLoaded('files') ? $job->files : $this->files->forJob((int) $job->id)) : [];
        $payload = JobPresenter::present($job, (bool) ($ctx['isAdmin'] ?? false) || (($ctx['userId'] ?? 0) === (int) $job->user_id), true, $files, $includeBids);
        $first = $payload['images'][0] ?? null;
        if (is_array($first)) {
            $payload['image_url'] = $first['thumbnail_url'] ?? $first['url'] ?? $first['download_url'] ?? null;
        }
        return $payload;
    }

    private function ownerContext(int $userId): array
    {
        return ['userId' => $userId, 'isAdmin' => false, 'isMember' => true, 'hasApprovedCompany' => false, 'companyKind' => null, 'isDesignated' => false];
    }

    private function defaultCreateStatus(): string
    {
        $raw = JobRules::normalizeListingStatus($this->setting('general.default_job_status', 'quote_request'));
        return in_array($raw, JobRules::LISTING_STATUSES, true) ? $raw : 'quote_request';
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            return app(MakerBidSettingsService::class)->getSetting($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function settingBool(string $key, bool $default = false): bool
    {
        return SettingsRules::boolish($this->setting($key, $default));
    }
}
