<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerBidRevision;
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

    public function createOrUpdateOwn(int $userId, int $jobId, array $payload, bool $isAdmin = false): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $this->assertCanWrite($userId, $job, $isAdmin);
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
            $fresh = $existing->fresh() ?? $existing;
            $this->snapshot($fresh, 'update');

            return ['bid' => $fresh, 'created' => false];
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
        $this->snapshot($bid, 'create');

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

    public function updateOwn(int $userId, int $jobId, int $bidId, array $payload, bool $isAdmin = false): MakerBid
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $bid = MakerBid::query()->where('job_id', $job->id)->findOrFail($bidId);
        if ((int) $bid->user_id !== $userId && ! $isAdmin) {
            throw new DomainException('본인 입찰만 수정할 수 있습니다.', 403);
        }
        $this->jobs->assertOpen($job);
        if (! BidRules::canUpdateOwn($userId, (int) $bid->user_id, true, (string) $bid->status) && ! $isAdmin) {
            throw new DomainException('수정할 수 없는 입찰입니다.', 422);
        }
        $company = $this->approvedCompany($userId);
        $this->assertEligible($userId, $job, $company, $isAdmin);
        $bid->fill($this->writeAttributes($payload, $company, true));
        $bid->save();
        $fresh = $bid->fresh() ?? $bid;
        $this->snapshot($fresh, 'update');

        return $fresh;
    }

    public function listRevisions(int $jobId, int $userId, bool $isAdmin = false): array
    {
        $this->ensureRevisionTable();
        $q = MakerBidRevision::query()->where('job_id', $jobId)->latest();
        if (! $isAdmin) {
            $q->where('user_id', $userId);
        }

        return $q->limit(50)->get()->map(fn (MakerBidRevision $row) => [
            'id' => (int) $row->id,
            'event' => (string) $row->event,
            'event_label' => $row->event === 'create' ? '초회 등록' : '수정',
            'amount' => (int) $row->amount,
            'days' => $row->days !== null ? (int) $row->days : null,
            'message' => $row->message,
            'status' => $row->status,
            'created_at' => optional($row->created_at)?->toDateTimeString(),
        ])->all();
    }

    public function listMine(int $userId): Collection
    {
        return MakerBid::query()->with('job')->where('user_id', $userId)->latest()->limit(100)->get();
    }

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

    public function findAdmin(int $id): array
    {
        return $this->presentAdmin(MakerBid::query()->with(['job', 'company'])->findOrFail($id));
    }

    public function updateAdmin(int $id, array $payload): array
    {
        $bid = MakerBid::query()->findOrFail($id);
        $allowed = [];
        foreach (['amount', 'days', 'message', 'status'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            if (($val === null || $val === '') && in_array($key, ['days', 'message'], true)) {
                continue;
            }
            $allowed[$key] = $val;
        }
        if ($allowed !== []) {
            $bid->fill($allowed);
            $bid->save();
            $this->snapshot($bid->fresh() ?? $bid, 'update');
        }

        return $this->findAdmin($id);
    }

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
        $row['revisions'] = $this->listRevisions((int) $bid->job_id, (int) $bid->user_id, true);

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
        $this->ensureRevisionTable();
        MakerBidRevision::query()->where('bid_id', $bid->id)->delete();
        $bid->delete();
    }

    private function snapshot(MakerBid $bid, string $event): void
    {
        $this->ensureRevisionTable();
        MakerBidRevision::query()->create([
            'bid_id' => (int) $bid->id,
            'job_id' => (int) $bid->job_id,
            'user_id' => (int) $bid->user_id,
            'event' => $event,
            'amount' => (int) $bid->amount,
            'days' => $bid->days,
            'message' => $bid->message,
            'status' => (string) $bid->status,
        ]);
    }

    private function ensureRevisionTable(): void
    {
        $table = (new MakerBidRevision)->getTable();
        if (Schema::hasTable($table)) {
            return;
        }
        Schema::create($table, function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('bid_id');
            $blueprint->unsignedBigInteger('job_id');
            $blueprint->unsignedBigInteger('user_id');
            $blueprint->string('event', 16)->default('update');
            $blueprint->unsignedInteger('amount');
            $blueprint->unsignedInteger('days')->nullable();
            $blueprint->text('message')->nullable();
            $blueprint->string('status', 32)->nullable();
            $blueprint->timestamps();
            $blueprint->index(['bid_id', 'created_at']);
            $blueprint->index(['job_id', 'user_id']);
        });
    }

    private function assertCanWrite(int $userId, MakerJob $job, bool $isAdmin = false): void
    {
        $this->jobs->assertOpen($job);
        if (! $isAdmin && BidRules::isOwnJob($userId, $job->user_id)) {
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
        return MakerCompany::query()->where('user_id', $userId)->where('status', 'approved')->first();
    }

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
