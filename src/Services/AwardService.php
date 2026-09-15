<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Support\Facades\DB;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerJob;
use Modules\Custom\MakerBid\Support\AwardRules;
use Modules\Custom\MakerBid\Support\DomainException;

class AwardService
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function award(int $actorId, int $jobId, int $bidId): MakerJob
    {
        return DB::transaction(function () use ($actorId, $jobId, $bidId) {
            /** @var MakerJob $job */
            $job = MakerJob::query()->lockForUpdate()->findOrFail($jobId);

            if (! AwardRules::canAward($actorId, $job->user_id)) {
                throw new DomainException('의뢰 작성자만 낙찰할 수 있습니다.', 403);
            }

            $this->jobs->assertOpen($job);

            $bid = MakerBid::query()
                ->where('job_id', $job->id)
                ->lockForUpdate()
                ->findOrFail($bidId);

            MakerBid::query()
                ->where('job_id', $job->id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => 'rejected']);

            $bid->status = 'accepted';
            $bid->save();

            $job->status = 'awarded';
            $job->awarded_bid_id = $bid->id;
            $job->save();

            return $job->fresh('bids') ?? $job;
        });
    }
}
