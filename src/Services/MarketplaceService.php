<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\BiddingRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobRules;

class MarketplaceService
{
    public function closeExpired(): int
    {
        if (! Schema::hasTable('maker_jobs')) {
            return 0;
        }
        $q = MakerJob::query()
            ->whereNotNull('closes_at')
            ->where('closes_at', '<', now())
            ->whereIn('status', ['quote_request', 'request', 'open']);
        $count = 0;
        foreach ($q->get() as $job) {
            if (Schema::hasColumn($job->getTable(), 'bidding_status')) {
                $job->bidding_status = BiddingRules::CLOSED;
                $job->bidding_closed_at = now();
            }
            $job->save();
            $count++;
        }

        return $count;
    }

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?int $jobId = null): void
    {
        if ($userId < 1 || ! Schema::hasTable('maker_notices')) {
            return;
        }
        DB::table('maker_notices')->insert([
            'user_id' => $userId,
            'job_id' => $jobId,
            'type' => $type,
            'title' => mb_substr($title, 0, 200),
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function audit(?int $userId, string $action, string $type, ?int $id, array $meta = []): void
    {
        if (! Schema::hasTable('maker_audits')) {
            return;
        }
        DB::table('maker_audits')->insert([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function notices(int $userId): array
    {
        if (! Schema::hasTable('maker_notices')) {
            return [];
        }

        return DB::table('maker_notices')->where('user_id', $userId)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function markRead(int $userId, int $id): void
    {
        DB::table('maker_notices')->where('user_id', $userId)->where('id', $id)->update(['read_at' => now()]);
    }

    public function rejectBid(int $actorId, int $jobId, int $bidId): void
    {
        $job = MakerJob::query()->findOrFail($jobId);
        if ((int) $job->user_id !== $actorId) {
            throw new DomainException('의뢰 작성자만 거절할 수 있습니다.', 403);
        }
        $bid = MakerBid::query()->where('job_id', $jobId)->findOrFail($bidId);
        $bid->status = 'rejected';
        $bid->save();
        $this->notify((int) $bid->user_id, 'bid_rejected', '입찰이 거절되었습니다.', (string) $job->title, $jobId);
        $this->audit($actorId, 'bid.reject', 'bid', $bidId);
    }

    public function compare(int $jobId): array
    {
        return MakerBid::query()->where('job_id', $jobId)->orderBy('amount')->get()->map(function (MakerBid $b) {
            return [
                'id' => (int) $b->id,
                'user_id' => (int) $b->user_id,
                'amount' => (int) $b->amount,
                'days' => $b->days,
                'message' => $b->message,
                'status' => $b->status,
            ];
        })->all();
    }

    public function complete(int $userId, int $jobId, int $score, ?string $comment): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        if ((int) $job->user_id !== $userId) {
            throw new DomainException('의뢰자만 완료할 수 있습니다.', 403);
        }
        $job->status = 'done';
        $job->work_status = 'done';
        $job->save();
        $companyId = null;
        if ($job->awarded_bid_id) {
            $bid = MakerBid::query()->find($job->awarded_bid_id);
            $companyId = $bid?->company_id;
        }
        if (Schema::hasTable('maker_reviews')) {
            DB::table('maker_reviews')->updateOrInsert(
                ['job_id' => $jobId],
                [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'score' => max(1, min(5, $score)),
                    'comment' => $comment,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
        if ($companyId) {
            $avg = DB::table('maker_reviews')->where('company_id', $companyId)->avg('score');
            $cnt = DB::table('maker_reviews')->where('company_id', $companyId)->count();
            MakerCompany::query()->where('id', $companyId)->update([
                'rating_score' => round((float) $avg, 2),
                'rating_count' => $cnt,
            ]);
        }
        $this->audit($userId, 'job.complete', 'job', $jobId);

        return ['ok' => true, 'status' => 'done'];
    }

    public function setWork(int $userId, int $jobId, string $status, ?string $tracking = null, ?string $carrier = null): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            throw new DomainException('낙찰 당사자만 진행을 바꿈 수 있습니다.', 403);
        }
        $allow = ['producing', 'shipping', 'delivered', 'done'];
        if (! in_array($status, $allow, true)) {
            throw new DomainException('잘못된 진행 상태입니다.', 422);
        }
        $job->work_status = $status;
        if ($tracking !== null) {
            $job->tracking_no = $tracking;
        }
        if ($carrier !== null) {
            $job->carrier = $carrier;
        }
        $job->save();
        $this->notify((int) $job->user_id, 'work', '작업 상태: '.$status, $tracking, $jobId);

        return ['work_status' => $status, 'tracking_no' => $job->tracking_no, 'carrier' => $job->carrier];
    }

    public function postMessage(int $userId, int $jobId, string $body): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            throw new DomainException('낙찰 후에만 대화할 수 있습니다.', 403);
        }
        $id = DB::table('maker_messages')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'body' => mb_substr($body, 0, 4000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $other = (int) $job->user_id === $userId ? (int) ($winner->user_id ?? 0) : (int) $job->user_id;
        if ($other > 0) {
            $this->notify($other, 'message', '새 대화', mb_substr($body, 0, 80), $jobId);
        }

        return ['id' => $id];
    }

    public function messages(int $userId, int $jobId): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            return [];
        }

        return DB::table('maker_messages')->where('job_id', $jobId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function claim(int $userId, int $jobId, string $reason): array
    {
        $id = DB::table('maker_claims')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => mb_substr($reason, 0, 2000),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($userId, 'claim.open', 'job', $jobId);

        return ['id' => $id];
    }

    public function report(int $userId, int $jobId, string $reason): array
    {
        $id = DB::table('maker_reports')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => mb_substr($reason, 0, 2000),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($userId, 'report.open', 'job', $jobId);

        return ['id' => $id];
    }

    public function exportJob(int $jobId): array
    {
        $job = MakerJob::query()->with(['bids', 'jobType'])->findOrFail($jobId);
        $lines = [
            '의뢰 #'.$job->id,
            '제목: '.$job->title,
            '유형: '.$job->type,
            '상태: '.$job->status,
            '예산: '.$job->budget_min.' ~ '.$job->budget_max,
            '설명:',
            (string) $job->description,
        ];
        foreach ($job->bids as $bid) {
            $lines[] = '입찰 #'.$bid->id.' '.$bid->amount.'원 / '.$bid->days.'일 / '.$bid->status;
        }

        return ['filename' => 'job-'.$job->id.'.txt', 'body' => implode("\n", $lines)];
    }

    public function logDownload(?int $userId, string $hash, ?int $jobId, ?string $ip): void
    {
        if (! Schema::hasTable('maker_file_logs')) {
            return;
        }
        DB::table('maker_file_logs')->insert([
            'user_id' => $userId,
            'hash' => $hash,
            'job_id' => $jobId,
            'ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function adminBundle(): array
    {
        return [
            'claims' => Schema::hasTable('maker_claims') ? DB::table('maker_claims')->orderByDesc('id')->limit(100)->get() : [],
            'reports' => Schema::hasTable('maker_reports') ? DB::table('maker_reports')->orderByDesc('id')->limit(100)->get() : [],
            'audits' => Schema::hasTable('maker_audits') ? DB::table('maker_audits')->orderByDesc('id')->limit(100)->get() : [],
            'file_logs' => Schema::hasTable('maker_file_logs') ? DB::table('maker_file_logs')->orderByDesc('id')->limit(100)->get() : [],
            'closed' => $this->closeExpired(),
        ];
    }

    public function resolveClaim(int $id, string $status, ?string $note): void
    {
        DB::table('maker_claims')->where('id', $id)->update([
            'status' => $status === 'closed' ? 'closed' : 'open',
            'admin_note' => $note,
            'updated_at' => now(),
        ]);
    }
}
