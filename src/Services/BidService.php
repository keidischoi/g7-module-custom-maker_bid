<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\CompanyPresenter;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobRules;

class BidService
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{bid: MakerBid, created: bool}
     */
    public function createOrUpdateOwn(int $userId, int $jobId, array $payload, bool $isAdmin = false): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $this->assertCanWrite($userId, $job);

        $company = $this->approvedCompany($userId);
        $this->assertEligible($userId, $job, $company, $isAdmin);

        $existing = MakerBid::query()
            ->where('job_id', $job->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if (! BidRules::canUpdateOwn($userId, (int) $existing->user_id, true, (string) $existing->status)) {
                throw new DomainException('수정할 수 없는 입찰입니다.', 422);
            }
            $existing->fill($this->writeAttributes($payload, $company, true));
            $existing->save();

            return ['bid' => $existing->fresh() ?? $existing, 'created' => false];
        }

        $bid = MakerBid::query()->create([
            'job_id' => $job->id,
            'user_id' => $userId,
            'company_id' => $company?->id,
            'amount' => (int) $payload['amount'],
            'days' => $payload['days'] ?? null,
            'message' => $payload['message'] ?? null,
            'status' => 'pending',
        ]);

        try {
            $market = app(MarketplaceService::class);
            $market->notify(
                (int) $job->user_id,
                'new_bid',
                '새 입찰이 등록되었습니다.',
                (string) $job->title.' · '.(int) $bid->amount.'원',
                (int) $job->id
            );
            $market->audit($userId, 'bid.create', 'bid', (int) $bid->id, ['job_id' => (int) $job->id]);
        } catch (\Throwable) {
        }

        return ['bid' => $bid, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOwn(int $userId, int $jobId, int $bidId, array $payload, bool $isAdmin = false): MakerBid
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $bid = MakerBid::query()->where('job_id', $job->id)->findOrFail($bidId);

        if ((int) $bid->user_id !== $userId) {
            throw new DomainException('본인 입찰만 수정할 수 있습니다.', 403);
        }

        $this->jobs->assertOpen($job);

        if (! BidRules::canUpdateOwn($userId, (int) $bid->user_id, true, (string) $bid->status)) {
            throw new DomainException('수정할 수 없는 입찰입니다.', 422);
        }

        $company = $this->approvedCompany($userId);
        $this->assertEligible($userId, $job, $company, $isAdmin);
        $bid->fill($this->writeAttributes($payload, $company, true));
        $bid->save();

        return $bid->fresh() ?? $bid;
    }

    /**
     * @return Collection<int, MakerBid>
     */
    public function listMine(int $userId): Collection
    {
        return MakerBid::query()
            ->with('job')
            ->where('user_id', $userId)
            ->latest()
            ->limit(100)
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdmin(Request $request): array
    {
        $q = MakerBid::query()->with(['job', 'company'])->latest();
        if ($jobId = $request->query('job_id')) {
            $q->where('job_id', (int) $jobId);
        }
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (int) $userId);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return $q->limit(200)->get()->map(fn (MakerBid $bid) => $this->presentAdmin($bid))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function findAdmin(int $id): array
    {
        return $this->presentAdmin(
            MakerBid::query()->with(['job', 'company'])->findOrFail($id)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateAdmin(int $id, array $payload): array
    {
        $bid = MakerBid::query()->findOrFail($id);
        $allowed = [];
        foreach (['amount', 'days', 'message', 'status'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            // Blank days/message on partial admin update = unchanged.
            if (($val === null || $val === '') && in_array($key, ['days', 'message'], true)) {
                continue;
            }
            $allowed[$key] = $val;
        }
        if ($allowed !== []) {
            $bid->fill($allowed);
            $bid->save();
        }

        return $this->findAdmin($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentAdmin(MakerBid $bid): array
    {
        $row = CompanyPresenter::presentBid($bid);
        $job = $bid->relationLoaded('job') ? $bid->job : null;
        $row['job'] = $job ? [
            'id' => (int) $job->id,
            'title' => (string) $job->title,
            'status' => (string) $job->status,
            'status_label' => JobRules::statusLabel((string) $job->status),
        ] : null;

        return $row;
    }

    public function destroyAdmin(int $id): void
    {
        $bid = MakerBid::query()->findOrFail($id);
        $job = $bid->job;
        if ($job && (int) $job->awarded_bid_id === (int) $bid->id) {
            $job->awarded_bid_id = null;
            if ($job->status === 'awarded') {
                $job->status = 'quote_request';
            }
            $job->save();
        }
        $bid->delete();
    }

    private function assertCanWrite(int $userId, MakerJob $job): void
    {
        $this->jobs->assertOpen($job);

        if (BidRules::isOwnJob($userId, $job->user_id)) {
            throw new DomainException('본인 의뢰에는 입찰할 수 없습니다.', 422);
        }
    }

    private function assertEligible(int $userId, MakerJob $job, ?MakerCompany $company, bool $isAdmin = false): void
    {
        $isMember = $userId > 0;
        $approved = CompanyRules::isApproved($company?->status);
        $designated = CompanyRules::isDesignated($company);
        $mode = $this->jobs->bidAllowMode();
        if (! BidRules::canBid($isMember, $approved, $mode, $isAdmin, $company?->kind, $designated)) {
            throw new DomainException(BidRules::denyMessage($mode), 403);
        }
        if (! JobRules::canBidAudience($job->audience ?? 'all', $isMember, $approved, $company?->kind, $mode, $isAdmin, $designated)) {
            throw new DomainException('이 의뢰의 공개 대상만 입찰할 수 있습니다.', 403);
        }
    }

    private function approvedCompany(int $userId): ?MakerCompany
    {
        return MakerCompany::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function writeAttributes(array $payload, ?MakerCompany $company, bool $partial = false): array
    {
        $attrs = [];
        if (array_key_exists('amount', $payload) && $payload['amount'] !== null && $payload['amount'] !== '') {
            $attrs['amount'] = (int) $payload['amount'];
        } elseif (! $partial) {
            $attrs['amount'] = (int) $payload['amount'];
        }
        if (array_key_exists('days', $payload)) {
            if (! $partial || ($payload['days'] !== null && $payload['days'] !== '')) {
                $attrs['days'] = $payload['days'];
            }
        } elseif (! $partial) {
            $attrs['days'] = null;
        }
        if (array_key_exists('message', $payload)) {
            if (! $partial || ($payload['message'] !== null && $payload['message'] !== '')) {
                $attrs['message'] = $payload['message'];
            }
        } elseif (! $partial) {
            $attrs['message'] = null;
        }
        if (! $partial) {
            $attrs['status'] = 'pending';
        } elseif (array_key_exists('status', $payload) && $payload['status'] !== null && $payload['status'] !== '') {
            $attrs['status'] = $payload['status'];
        }
        if ($company) {
            $attrs['company_id'] = $company->id;
        }

        return $attrs;
    }
}
