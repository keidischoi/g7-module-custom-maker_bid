<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerCompany;
use Modules\Custom\MakerBid\Models\MakerJob;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\CompanyRules;
use Modules\Custom\MakerBid\Support\DomainException;
use Modules\Custom\MakerBid\Support\JobRules;

class BidService
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{bid: MakerBid, created: bool}
     */
    public function createOrUpdateOwn(int $userId, int $jobId, array $payload): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $this->assertCanWrite($userId, $job);

        $company = $this->approvedCompany($userId);
        $this->assertEligible($userId, $job, $company);

        $existing = MakerBid::query()
            ->where('job_id', $job->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if (! BidRules::canUpdateOwn($userId, (int) $existing->user_id, true, (string) $existing->status)) {
                throw new DomainException('수정할 수 없는 입찰입니다.', 422);
            }
            $existing->fill($this->writeAttributes($payload, $company));
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

        return ['bid' => $bid, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateOwn(int $userId, int $jobId, int $bidId, array $payload): MakerBid
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
        $bid->fill($this->writeAttributes($payload, $company));
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
     * @return Collection<int, MakerBid>
     */
    public function listAdmin(Request $request): Collection
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

        return $q->limit(200)->get();
    }

    public function findAdmin(int $id): MakerBid
    {
        return MakerBid::query()->with(['job', 'company'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAdmin(int $id, array $payload): MakerBid
    {
        $bid = MakerBid::query()->findOrFail($id);
        $bid->fill($payload);
        $bid->save();

        return $bid->fresh() ?? $bid;
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

    private function assertEligible(int $userId, MakerJob $job, ?MakerCompany $company): void
    {
        $isMember = $userId > 0;
        $approved = CompanyRules::isApproved($company?->status);
        if (! BidRules::canBid($isMember, $approved)) {
            throw new DomainException('회원 또는 승인된 업체만 입찰할 수 있습니다.', 403);
        }
        if (! JobRules::canBidAudience($job->audience ?? 'all', $isMember, $approved, $company?->kind)) {
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
    private function writeAttributes(array $payload, ?MakerCompany $company): array
    {
        $attrs = [
            'amount' => (int) $payload['amount'],
            'days' => $payload['days'] ?? null,
            'message' => $payload['message'] ?? null,
            'status' => 'pending',
        ];
        if ($company) {
            $attrs['company_id'] = $company->id;
        }

        return $attrs;
    }
}
