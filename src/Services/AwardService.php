<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Support\Facades\DB;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\AwardRules;
use Modules\Custom\MakerBids\Support\BiddingRules;
use Modules\Custom\MakerBids\Support\DomainException;

class AwardService
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function award(int $actorId, int $jobId, int $bidId, array $terms = []): MakerJob
    {
        $job = DB::transaction(function () use ($actorId, $jobId, $bidId, $terms) {
            $job = MakerJob::query()->lockForUpdate()->findOrFail($jobId);
            if (! AwardRules::canAward($actorId, $job->user_id)) {
                throw new DomainException('의뢰 작성자만 낙찰할 수 있습니다.', 403);
            }
            if (! BiddingRules::isOpen($job->bidding_status ?? BiddingRules::OPEN, (string) $job->status, $job->closes_at)) {
                throw new DomainException('입찰이 종료되어 낙찰할 수 없습니다.', 422);
            }
            $bid = MakerBid::query()->where('job_id', $job->id)->lockForUpdate()->findOrFail($bidId);
            MakerBid::query()->where('job_id', $job->id)->where('status', 'accepted')->where('id', '!=', $bid->id)->update(['status' => 'pending']);
            $bid->status = 'accepted';
            $bid->save();
            $job->status = 'awarded';
            $job->awarded_bid_id = $bid->id;
            $job->work_status = 'producing';
            $job->save();
            try {
                app(PaymentService::class)->ensureDue($job, $bid, $terms);
            } catch (\Throwable) {
            }
            return $job->fresh('bids') ?? $job;
        });
        try {
            $market = app(MarketplaceService::class);
            $market->notify((int) $job->user_id, 'award', '낙찰했습니다. 안내된 계좌로 계약금(또는 전액)을 이체해 주세요.', (string) $job->title, $jobId);
            $bid = MakerBid::query()->find($bidId);
            if ($bid) {
                $market->notify((int) $bid->user_id, 'award', '입찰이 낙찰되었습니다.', (string) $job->title, $jobId);
            }
            $market->audit($actorId, 'job.award', 'job', $jobId, ['bid_id' => $bidId]);
        } catch (\Throwable) {
        }

        return $job;
    }

    public function closeBidding(int $actorId, int $jobId, bool $isAdmin = false): MakerJob
    {
        return DB::transaction(function () use ($actorId, $jobId, $isAdmin) {
            $job = MakerJob::query()->lockForUpdate()->findOrFail($jobId);
            if (! $isAdmin && (int) $job->user_id !== $actorId) {
                throw new DomainException('의뢰 작성자만 입찰을 종료할 수 있습니다.', 403);
            }
            $job->bidding_status = BiddingRules::CLOSED;
            $job->bidding_closed_at = now();
            $job->save();

            return $job->fresh('bids') ?? $job;
        });
    }
}
