<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

    public function createOrUpdateOwn(int $userId, int $jobId, array $payload, bool $isAdmin = false): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $this->assertCanWrite($userId, $job, $isAdmin);
        $company = $this->approvedCompany($userId);
        $this->assertEligible($userId, $job, $company, $isAdmin);
        $this->allowMultipleRows();

        $had = MakerBid::query()->where('job_id', $job->id)->where('user_id', $userId)->exists();
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
                $had ? 'bid_update' : 'new_bid',
                $had ? '견적이 수정되었습니다.' : '새 입찰이 등록되었습니다.',
                (string) $job->title.' · '.(int) $bid->amount.'원',
                (int) $job->id
            );
            $market->audit($userId, $had ? 'bid.insert' : 'bid.create', 'bid', (int) $bid->id, ['job_id' => (int) $job->id]);
        } catch (\Throwable) {
        }

        return ['bid' => $bid, 'created' => ! $had];
    }

    public function updateOwn(int $userId, int $jobId, int $bidId, array $payload, bool $isAdmin = false): MakerBid
    {
        return $this->createOrUpdateOwn($userId, $jobId, $payload, $isAdmin)['bid'];
    }

    public function listRevisions(int $jobId, int $userId, bool $isAdmin = false): array
    {
        $q = MakerBid::query()->where('job_id', $jobId);
        if (! $isAdmin) {
            $q->where('user_id', $userId);
        }

        return $q->limit(50)->get()->map(fn (MakerBid $row) => [
            'id' => (int) $row->id,
            'event' => $row->created_at && $row->updated_at && $row->created_at->eq($row->updated_at) ? 'create' : 'update',
            'event_label' => '제출',
            'amount' => (int) $row->amount,
            'days' => $row->days !== null ? (int) $row->days : null,
            'message' => $row->message,
            'status' => $row->status,
            'created_at' => optional($row->created_at)?->toDateTimeString(),
        ])->all();
    }

    public function listMine(int $userId): Collection
    {
        $items = MakerBid::query()->with('job')->where('user_id', $userId)->limit(200)->get();
        $seen = [];
        $out = new Collection;
        foreach ($items as $bid) {
            $jid = (int) $bid->job_id;
            if (isset($seen[$jid])) {
                continue;
            }
            $seen[$jid] = true;
            $out->push($bid);
        }

        return $out->values();
    }

    public function listAdmin(Request $request): array
    {
        $q = MakerBid::query()->with(['job', 'company']);
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
        return $this->presentAdmin(MakerBid::withoutGlobalScopes()->with(['job', 'company'])->findOrFail($id));
    }

    public function updateAdmin(int $id, array $payload): array
    {
        $bid = MakerBid::withoutGlobalScopes()->findOrFail($id);
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
        $bid = MakerBid::withoutGlobalScopes()->findOrFail($id);
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

    private function allowMultipleRows(): void
    {
        if (! Schema::hasTable('maker_bids')) {
            return;
        }
        try {
            Schema::table('maker_bids', function (Blueprint $table) {
                $table->dropUnique('maker_bids_job_user_unique');
            });
        } catch (\Throwable) {
        }
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
}
